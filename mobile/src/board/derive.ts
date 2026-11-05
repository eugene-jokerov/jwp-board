/**
 * Чистые функции над состоянием доски.
 *
 * Здесь нет React: вычисления и проверки правил живут отдельно от рендера,
 * поэтому их можно свободно вызывать в теле компонента.
 */

import { COLUMNS, type Card, type CardKind, type ColumnId, type MoveDirection } from './types';

/** Индекс колонки в фиксированном порядке доски. */
function columnIndex(column: ColumnId): number {
  return COLUMNS.findIndex((item) => item.id === column);
}

/** Соседняя колонка в указанном направлении; null — за краем доски. */
export function neighborColumn(column: ColumnId, direction: MoveDirection): ColumnId | null {
  const next = COLUMNS[columnIndex(column) + (direction === 'prev' ? -1 : 1)];

  return next ? next.id : null;
}

/** Дочерние карточки (проекты инициативы, задачи проекта). */
export function childrenOf(cards: readonly Card[], cardId: string): Card[] {
  return cards.filter((card) => card.parentId === cardId);
}

/** Дочерние карточки, которые сейчас в работе — они блокируют перенос родителя. */
export function activeChildren(cards: readonly Card[], cardId: string): Card[] {
  return childrenOf(cards, cardId).filter((card) => card.column === 'in_progress');
}

export interface MoveCheck {
  allowed: boolean;
  /** Текст причины запрета; null — стрелка просто недоступна на краю доски. */
  reason: string | null;
}

/**
 * Можно ли перевести карточку в соседнюю колонку.
 *
 * Правила:
 * - если у карточки есть дети в «В работе», перенос запрещён в любую колонку;
 * - из «Беклога» влево и из «Отмены» вправо двигаться некуда.
 */
export function checkMove(cards: readonly Card[], card: Card, direction: MoveDirection): MoveCheck {
  const active = activeChildren(cards, card.id);

  if (active.length > 0) {
    return {
      allowed: false,
      reason: `Нельзя переместить «${card.title}»: у неё есть зависимые карточки в работе (${active.length}). Сначала завершите или отмените их.`,
    };
  }

  return { allowed: neighborColumn(card.column, direction) !== null, reason: null };
}

/**
 * Можно ли сменить колонку карточки из формы редактирования.
 *
 * Правило то же, что у стрелок: карточка с детьми в «В работе» заперта в своей
 * колонке. Переход в текущую колонку — это не смена статуса, он разрешён всегда.
 */
export function checkColumnChange(cards: readonly Card[], card: Card, target: ColumnId): MoveCheck {
  if (target === card.column) {
    return { allowed: true, reason: null };
  }

  const active = activeChildren(cards, card.id);

  if (active.length > 0) {
    return {
      allowed: false,
      reason: `Нельзя сменить статус «${card.title}»: у неё есть зависимые карточки в работе (${active.length}). Сначала завершите или отмените их.`,
    };
  }

  return { allowed: true, reason: null };
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

/** Действие иконки по текущей колонке карточки: «Беклог» и «В работе» — с переходом. */
const NEXT_COLUMN_ACTION: Partial<Record<ColumnId, { target: ColumnId; label: string }>> = {
  backlog: { target: 'in_progress', label: 'Взять в работу' },
  in_progress: { target: 'done', label: 'Завершить задачу' },
};

/**
 * Действие карточки по её текущей колонке.
 *
 * Из «Беклога» — «Взять в работу» (в «В работе»), из «В работе» — «Завершить
 * задачу» (в «Готово»). В «Готово» и «Отмене» действий нет — вернётся null.
 * Доступность перехода проверяется тем же правилом, что и у формы редактирования.
 */
export function cardAction(cards: readonly Card[], card: Card): CardAction | null {
  const action = NEXT_COLUMN_ACTION[card.column];

  if (action === undefined) {
    return null;
  }

  return { ...action, blockReason: checkMove(cards, card, 'next').reason };
}

/** Новый массив карточек с обновлённым названием и/или колонкой. */
export function updateCard(cards: readonly Card[], cardId: string, patch: Pick<Card, 'title' | 'column'>): Card[] {
  return cards.map((card) => (card.id === cardId ? { ...card, ...patch } : card));
}

/** Карточки свимлайна: у родителя — его дети, у корневого свимлайна — все карточки типа. */
export function laneCards(cards: readonly Card[], kind: CardKind, laneId: string | null): Card[] {
  return cards.filter((card) => (laneId === null ? card.kind === kind : card.parentId === laneId));
}

/** Только карточки указанной колонки (в исходном порядке). */
export function cardsInColumn(cards: readonly Card[], column: ColumnId): Card[] {
  return cards.filter((card) => card.column === column);
}

/** Карточки, которые могут быть родителем на уровне: только те, что в работе. */
export function parentCandidates(cards: readonly Card[], kind: CardKind): Card[] {
  return cards.filter((card) => card.kind === kind && card.column === 'in_progress');
}

/** Количество карточек по колонкам — для счётчиков в заголовках. */
export function countByColumn(cards: readonly Card[]): Record<ColumnId, number> {
  const result: Record<ColumnId, number> = { backlog: 0, in_progress: 0, done: 0, canceled: 0 };

  cards.forEach((card) => {
    result[card.column] += 1;
  });

  return result;
}
