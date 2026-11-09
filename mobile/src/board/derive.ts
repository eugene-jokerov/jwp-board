/**
 * Чистые функции над состоянием доски.
 *
 * Здесь нет React: вычисления и проверки живут отдельно от рендера,
 * поэтому их можно свободно вызывать в теле компонента.
 *
 * Правила смены колонки на клиенте не дублируются: сервер отдаёт у каждой
 * карточки `allowedTransitions`, и интерфейс показывает переходы только по нему.
 */

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
