/**
 * Демо-состояние доски.
 *
 * Backend ещё не подключён, поэтому данные статичные: три направления
 * (красный, синий, зелёный), инициативы в разных колонках, эпики под инициативами
 * «в работе» и задачи под эпиками «в работе». Отдельно добавлены эпики без инициативы
 * и задачи без эпика — так видно карточки без цвета.
 */

import type { Card, CardKind, ColumnId } from './types';

/** Три направления деятельности стратегического уровня. */
export const DIRECTIONS = [
  { id: 'dir-1', title: 'Направление 1', color: '#ef4444' },
  { id: 'dir-2', title: 'Направление 2', color: '#3b82f6' },
  { id: 'dir-3', title: 'Направление 3', color: '#22c55e' },
] as const;

type CardSeed = [id: string, title: string, column: ColumnId, parentId: string | null];

/** Карточка из сида: id, название, колонка и родитель. */
function makeCard(kind: CardKind, color: string | null, seed: CardSeed): Card {
  const [id, title, column, parentId] = seed;

  return { id, kind, title, column, color, parentId };
}

/** Набор карточек уровня с цветом, вычисленным по родителю. */
function makeCards(kind: CardKind, colorOf: (seed: CardSeed) => string | null, seeds: readonly CardSeed[]): Card[] {
  return seeds.map((seed) => makeCard(kind, colorOf(seed), seed));
}

const INITIATIVE_SEEDS: readonly CardSeed[] = [
  ['init-1', 'Инициатива 1.1', 'in_progress', 'dir-1'],
  ['init-2', 'Инициатива 1.2', 'backlog', 'dir-1'],
  ['init-3', 'Инициатива 1.3', 'done', 'dir-1'],
  ['init-4', 'Инициатива 2.1', 'in_progress', 'dir-2'],
  ['init-5', 'Инициатива 2.2', 'in_progress', 'dir-2'],
  ['init-6', 'Инициатива 2.3', 'canceled', 'dir-2'],
  ['init-7', 'Инициатива 3.1', 'in_progress', 'dir-3'],
  ['init-8', 'Инициатива 3.2', 'backlog', 'dir-3'],
];

const EPIC_SEEDS: readonly CardSeed[] = [
  ['epic-1', 'Эпик 1.1.1', 'in_progress', 'init-1'],
  ['epic-2', 'Эпик 1.1.2', 'done', 'init-1'],
  ['epic-3', 'Эпик 2.1.1', 'in_progress', 'init-4'],
  ['epic-4', 'Эпик 2.2.1', 'backlog', 'init-5'],
  ['epic-5', 'Эпик 3.1.1', 'in_progress', 'init-7'],
  ['epic-6', 'Эпик 3.1.2', 'in_progress', 'init-7'],
  ['epic-7', 'Эпик без инициативы 1', 'backlog', null],
  ['epic-8', 'Эпик без инициативы 2', 'in_progress', null],
];

const TASK_SEEDS: readonly CardSeed[] = [
  ['task-1', 'Задача 1.1.1.1', 'in_progress', 'epic-1'],
  ['task-2', 'Задача 1.1.1.2', 'backlog', 'epic-1'],
  ['task-3', 'Задача 2.1.1.1', 'done', 'epic-3'],
  ['task-4', 'Задача 3.1.1.1', 'in_progress', 'epic-5'],
  ['task-5', 'Задача 3.1.2.1', 'backlog', 'epic-6'],
  ['task-6', 'Задача без эпика 1', 'in_progress', null],
  ['task-7', 'Задача без эпика 2', 'backlog', null],
  ['task-8', 'Задача без эпика 3', 'canceled', null],
];

/** Дочерние карточки наследуют цвет родителя; у карточки без родителя цвета нет. */
function inheritedColor(cards: readonly Card[], seed: CardSeed): string | null {
  const [, , , parentId] = seed;
  const parent = cards.find((card) => card.id === parentId);

  return parent ? parent.color : null;
}

const initiativeColor = (seed: CardSeed): string | null => {
  const [, , , parentId] = seed;

  return DIRECTIONS.find((direction) => direction.id === parentId)?.color ?? null;
};

const INITIATIVES = makeCards('initiative', initiativeColor, INITIATIVE_SEEDS);
const EPICS = makeCards('epic', (seed) => inheritedColor(INITIATIVES, seed), EPIC_SEEDS);
const TASKS = makeCards('task', (seed) => inheritedColor(EPICS, seed), TASK_SEEDS);

/** Стартовый набор карточек доски. */
export const INITIAL_CARDS: readonly Card[] = [...INITIATIVES, ...EPICS, ...TASKS];
