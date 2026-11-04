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

/** Дочерние карточки (эпики инициативы, задачи эпика). */
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

/** Новый массив карточек с переведённой в соседнюю колонку карточкой. */
export function moveCard(cards: readonly Card[], cardId: string, direction: MoveDirection): Card[] {
  return cards.map((card) => {
    if (card.id !== cardId) {
      return card;
    }

    const target = neighborColumn(card.column, direction);

    return target ? { ...card, column: target } : card;
  });
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
