/**
 * Чистые функции над состоянием доски.
 *
 * Здесь нет React: вычисления и проверки живут отдельно от рендера,
 * поэтому их можно свободно вызывать в теле компонента.
 *
 * Правила смены колонки на клиенте не дублируются: сервер отдаёт у каждой
 * карточки `allowedTransitions`, и интерфейс показывает переходы только по нему.
 */

import { parentOf } from './mappers';
import { COLUMNS, type Card, type CardKind, type ClosedTotals, type ColumnId, type Direction } from './types';

/**
 * Закрытые колонки: карточки в них только накапливаются, а список на
 * первичной загрузке урезан — полное число приходит отдельно, в `closedTotals`.
 */
const CLOSED_COLUMNS: readonly ColumnId[] = ['complete', 'canceled'];

/** Закрыта ли колонка: у закрытых счётчик берётся не из загруженного списка. */
export function isClosedColumn(column: ColumnId): boolean {
  return CLOSED_COLUMNS.includes(column);
}

/**
 * Число карточек колонки уровня для бейджа.
 *
 * Открытые колонки приходят целиком — считаем загруженные карточки. В «Готово»
 * и «Отмене» их только порция (сервер режет пару «уровень + колонка»), поэтому
 * бейдж берёт полное число из `closedTotals`; если счётчик ещё не пришёл или
 * отстал от выборки, показываем не меньше загруженного — список не может быть
 * длиннее счётчика.
 */
export function columnCount(loaded: number, column: ColumnId, kind: CardKind, totals: ClosedTotals): number {
  if (!isClosedColumn(column)) {
    return loaded;
  }

  const total = totals[column]?.[kind] ?? 0;

  return total > loaded ? total : loaded;
}

/**
 * Сколько карточек пары «уровень + колонка» уже загружено в состояние доски.
 *
 * Считается по массиву карточек, а не по счётчику `closedTotals`: счётчик
 * говорит, сколько карточек пары есть на сервере, а для страницы догрузки
 * нужно именно смещение — число строк, которые клиент уже получил и нарисовал.
 */
export function closedLoadedCount(cards: readonly Card[], column: ColumnId, kind: CardKind): number {
  return cards.filter((card) => card.column === column && card.kind === kind).length;
}

/**
 * Есть ли у пары «уровень + колонка» ещё карточки, кроме загруженных.
 *
 * Первый признак — полное число из `meta.closed_totals`: закрытые колонки на
 * первичной загрузке приходят порцией, и пока счётчик пары больше списка, под
 * списком показывается «Показать ещё».
 *
 * Второй — последняя догрузка вернула полную страницу: значит за ней заведомо
 * может быть продолжение, и кнопку нельзя убирать, даже если счётчик пары
 * отстал (например, страница пришла из `meta` с фильтрами и число успело
 * измениться). Иначе лента обрывалась бы на ровной границе страницы.
 */
export function closedHasMore(
  cards: readonly Card[],
  column: ColumnId,
  kind: CardKind,
  totals: ClosedTotals,
  pages: ClosedPages = {},
): boolean {
  if (closedPageOf(pages, column, kind).fullPage) {
    return true;
  }

  const total = totals[column]?.[kind] ?? 0;

  return total > closedLoadedCount(cards, column, kind);
}

/**
 * Состояние догрузки одной пары «колонка + уровень»: идёт запрос или он упал.
 *
 * Хранится по парам, потому что ленты «Показать ещё» независимы: догрузка
 * «Готово» на тактическом уровне не блокирует кнопку в «Отмене» на
 * стратегическом.
 */
export interface ClosedPageState {
  /** true — запрос страницы в полёте: повторный клик не должен его дублировать. */
  loading: boolean;
  /** Текст ошибки последней догрузки; null — ошибки нет. */
  error: string | null;
  /**
   * Последняя догрузка вернула полную страницу: продолжение возможно даже при
   * счётчике, который ещё не обновился.
   */
  fullPage: boolean;
}

/** Состояние догрузки по парам: колонка → тип карточки → состояние. */
export type ClosedPages = Partial<Record<ColumnId, Partial<Record<CardKind, ClosedPageState>>>>;

/** Состояние пары; пара ещё не догружалась — `loading`/`error`/`fullPage` сброшены. */
export function closedPageOf(pages: ClosedPages, column: ColumnId, kind: CardKind): ClosedPageState {
  return pages[column]?.[kind] ?? { loading: false, error: null, fullPage: false };
}

/** Новое состояние догрузки с заменой одной пары (остальные пары не меняются). */
export function withClosedPage(
  pages: ClosedPages,
  column: ColumnId,
  kind: CardKind,
  state: ClosedPageState,
): ClosedPages {
  return { ...pages, [column]: { ...pages[column], [kind]: state } };
}

/**
 * Записать полное число карточек одной пары «уровень + колонка», не трогая прочие.
 *
 * Ответ страницы догрузки считает `meta` по своим фильтрам («колонка + уровень»),
 * поэтому по остальным парам там нули. Заменять счётчики целиком нельзя: у пар,
 * которые не догружали, число обнулилось бы и «Показать ещё» пропало бы у всех
 * колонок. `null` (сервер не прислал число) оставляет прежнее значение.
 */
export function setClosedTotal(
  totals: ClosedTotals,
  column: ColumnId,
  kind: CardKind,
  total: number | null | undefined,
): ClosedTotals {
  if (total === null || total === undefined) {
    return totals;
  }

  return { ...totals, [column]: { ...totals[column], [kind]: total } };
}

/**
 * Счётчики закрытых колонок после изменения одной карточки.
 *
 * Доска не перечитывается на каждое действие: ответ API содержит только саму
 * карточку, а `meta` в нём нет. Поэтому счётчики правятся локально на ту же
 * единицу, на которую изменилась пара: при смене колонки — убывает в старой
 * паре и растёт в новой, создание только прибавляет, удаление только убавляет.
 * Открытые колонки в счётчиках не участвуют — там карточки считаются по массиву.
 *
 * @param from колонка, где карточка была (для создания — та же, что `to`)
 * @param to колонка, где карточка оказалась (для удаления — та же, что `from`)
 */
export function shiftClosedTotals(
  totals: ClosedTotals,
  kind: CardKind,
  from: ColumnId | null,
  to: ColumnId | null,
): ClosedTotals {
  const next = { ...totals };

  const shift = (column: ColumnId | null, delta: number) => {
    if (column === null || !isClosedColumn(column)) {
      return;
    }

    const byKind = next[column] ?? {};
    const value = (byKind[kind] ?? 0) + delta;

    // Счётчик не уходит в минус: отрицательное число означало бы, что на сервере
    // карточек пары меньше, чем клиент успел нарисовать.
    next[column] = { ...byKind, [kind]: value > 0 ? value : 0 };
  };

  if (from !== to) {
    shift(from, -1);
    shift(to, 1);
  }

  return next;
}

/** Индекс колонки в фиксированном порядке доски. */
function columnIndex(column: ColumnId): number {
  return COLUMNS.findIndex((item) => item.id === column);
}

/** Карточки, у которых указанная карточка — родитель: проекты инициативы, задачи проекта. */
export function childrenOf(cards: readonly Card[], card: Card): Card[] {
  return cards.filter((item) =>
    card.kind === 'initiative' ? item.initiativeId === card.id : item.projectId === card.id,
  );
}

/** Карточки направления на любом уровне: инициативы, их проекты и задачи. */
export function directionCards(cards: readonly Card[], directionId: number): Card[] {
  return cards.filter((card) => card.directionId === directionId);
}

export interface MoveCheck {
  allowed: boolean;
  /** Текст причины запрета; null — переход разрешён. */
  reason: string | null;
}

/**
 * Можно ли перевести карточку в указанную колонку.
 *
 * Решение принимает сервер: `allowedTransitions` содержит колонки, доступные
 * прямо сейчас, плюс текущую. Если целевой колонки там нет — смену блокируют
 * зависимые карточки в работе.
 */
export function checkColumnChange(card: Card, target: ColumnId): MoveCheck {
  if (target === card.column || card.allowedTransitions.includes(target)) {
    return { allowed: true, reason: null };
  }

  return {
    allowed: false,
    reason: `Нельзя сменить статус «${card.title}»: у неё есть зависимые карточки в работе. Сначала завершите или отмените их.`,
  };
}

/** Действие на карточке: перевод в следующую колонку. */
export interface CardAction {
  /** Колонка, в которую перейдёт карточка. */
  target: ColumnId;
  /** Название действия для подписи иконки: «Взять в работу», «Завершить задачу». */
  label: string;
  /** Причина запрета; null — переход разрешён. */
  blockReason: string | null;
}

/** Подпись действия перехода: зависит от целевой колонки. */
function actionLabel(target: ColumnId): string {
  return target === 'in_progress' ? 'Взять в работу' : 'Завершить задачу';
}

/**
 * Действие иконки на карточке: следующий шаг по порядку колонок.
 *
 * В «Готово» и «Отмене» действий нет. Если следующий шаг запрещён
 * (`allowedTransitions` содержит только текущую колонку), иконка остаётся
 * и нажатие объясняет причину: на web Alert не работает, поэтому решение
 * «где показать текст» принимает экран.
 */
export function cardAction(card: Card): CardAction | null {
  const index = columnIndex(card.column);
  const next = COLUMNS[index + 1];

  if (index < 0 || next === undefined || next.id === 'canceled') {
    return null;
  }

  const allowed = card.allowedTransitions.includes(next.id);

  return {
    target: next.id,
    label: actionLabel(next.id),
    blockReason: allowed ? null : checkColumnChange(card, next.id).reason,
  };
}

/**
 * Можно ли удалить направление.
 *
 * Удаление запрещено, если в направлении есть карточки в любой колонке —
 * иначе инициативы остались бы без родителя. Карточки в «Отмене» и «Готово»
 * тоже считаются: они принадлежат направлению и отображаются в его свимлайне.
 * Финальную проверку повторяет сервер (409 при непустом направлении).
 */
export function checkDirectionDelete(cards: readonly Card[], direction: Direction): MoveCheck {
  const total = directionCards(cards, direction.id).length;

  if (total > 0) {
    return {
      allowed: false,
      reason: `В направлении «${direction.title}» есть карточки (${total}). Удаление запрещено: сначала перенесите или удалите их.`,
    };
  }

  return { allowed: true, reason: null };
}

/** Карточки, которые могут быть родителем на уровне: только те, что в работе. */
export function parentCandidates(cards: readonly Card[], kind: CardKind): Card[] {
  return cards.filter((card) => card.kind === kind && card.column === 'in_progress');
}

/**
 * Сегмент крошек карточки: родительская карточка уровня.
 *
 * Направление в путь не входит — крошки показывают только карточки иерархии
 * (инициатива, проект), поэтому на стратегическом уровне крошек нет вовсе.
 */
export interface CardBreadcrumb {
  /** id родительской карточки. */
  id: number;
  /** Тип родительской карточки: `initiative` или `project`. */
  kind: CardKind;
  /** Подпись сегмента: кодовое название родителя, а если метки нет — название. */
  label: string;
}

/**
 * Максимальная глубина пути: инициатива → проект → задача.
 *
 * Ограничение защищает от зацикливания, если данные придут с некорректными
 * ссылками иерархии: карточных уровней всего три, длиннее путь быть не может.
 */
const BREADCRUMB_MAX_DEPTH = 3;

/**
 * Путь карточки по карточкам иерархии: от корня к родителю, без самой карточки.
 *
 * Крошки рисуются в плитке над названием, поэтому сам узел в путь не входит —
 * он и так стоит строкой выше. Метка сегмента — кодовое название родителя
 * (`slug`), а если метки нет, то его название: крошки не должны исчезать у
 * карточек, созданных до появления slug.
 *
 * Путь строится по тем же правилам, что и свимлайны (см. `parentOf`): у
 * проекта родитель — инициатива, у задачи — проект, а дальше — инициатива.
 * У инициативы родитель — направление, и на нём путь заканчивается: направление
 * в крошки не попадает, поэтому у инициатив крошек нет.
 *
 * Родителя ищут по загруженным карточкам, поэтому у карточки, чей родитель не
 * пришёл в выборку (например, удалён), путь обрывается на последнем известном
 * сегменте — вверх от «дыры» ничего не угадываем.
 */
export function cardBreadcrumbs(card: Card, cards: readonly Card[]): CardBreadcrumb[] {
  const path: CardBreadcrumb[] = [];

  let parent = parentOf(card);
  let depth = 0;

  while (parent !== null && depth < BREADCRUMB_MAX_DEPTH) {
    // У инициативы родитель — направление: его в крошки не берём и на нём
    // заканчиваем путь, выше направления узлов нет.
    if (parent.kind === 'direction') {
      break;
    }

    // Снимок звена: в замыкании `.find()` TypeScript не сужает `parent`, а
    // следующее звено пути уже вычисляется от найденной карточки-родителя.
    const link = parent;
    const parentCard = cards.find((item) => item.id === link.id);

    if (parentCard === undefined) {
      break;
    }

    path.push({ id: parentCard.id, kind: parentCard.kind, label: breadcrumbLabel(parentCard) });
    parent = parentOf(parentCard);
    depth += 1;
  }

  // Собирали от карточки вверх, а крошки читаются от корня: инициатива слева.
  return path.reverse();
}

/**
 * Подпись сегмента крошек: кодовое название родителя, если оно задано, иначе
 * название карточки.
 *
 * Метка проверяется на пустоту после `trim()`: в базе могли остаться значения
 * из старых данных (пустая строка, одни пробелы), и такой сегмент показал бы
 * пустоту вместо названия — путь в плитке стал бы нечитаемым. Само значение не
 * нормализуем: `slug` отдаётся сервером как есть, а клиент лишь выбирает, что
 * показать.
 */
function breadcrumbLabel(card: Card): string {
  const slug = card.slug?.trim() ?? '';

  return slug === '' ? card.title : slug;
}
