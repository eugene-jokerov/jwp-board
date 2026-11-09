/**
 * Соответствие моделей API и модели доски.
 *
 * Единственное место, где живёт перевод `direction_id` / `initiative_id` /
 * `project_id` в плоские поля карточки и обратно. Компоненты доски про API не знают,
 * `client.ts` — про модель доски: связывает их этот модуль.
 */

import type { CardColumn, CardType, Direction as ApiDirection, Task as ApiTask, TaskListMeta } from '../api/types';
import type { Card, CardKind, ClosedTotals, ColumnId, Direction } from './types';

/**
 * Типы карточки API = типы карточки доски: словарь общий.
 * Функция-проверка страхует от расхождения, если API добавит новый тип.
 */
function toCardKind(type: CardType): CardKind {
  return type;
}

/** Колонка API = колонка доски: словарь общий. */
export function toColumnId(column: CardColumn): ColumnId {
  return column;
}

/** Карточка API в модель доски. */
export function toCard(task: ApiTask): Card {
  return {
    id: task.id,
    kind: toCardKind(task.type),
    title: task.title,
    column: toColumnId(task.column),
    color: task.color,
    directionId: task.direction_id,
    initiativeId: task.initiative_id,
    projectId: task.project_id,
    allowedTransitions: task.allowed_transitions.map(toColumnId),
  };
}

/** Направление API в модель доски. */
export function toDirection(direction: ApiDirection): Direction {
  return { id: direction.id, title: direction.title, color: direction.color };
}

/**
 * Счётчики закрытых колонок из `meta` ответа списка карточек.
 *
 * В модели доски колонки и уровни называются так же, как в API, поэтому перевод
 * остаётся копированием: из `closed_totals` берутся только закрытые колонки,
 * остальные в модели не хранятся — их счётчик считается по массиву карточек.
 */
export function toClosedTotals(meta: TaskListMeta): ClosedTotals {
  const totals: ClosedTotals = {};

  for (const [column, byKind] of Object.entries(meta.closed_totals)) {
    totals[toColumnId(column as CardColumn)] = { ...byKind };
  }

  return totals;
}

/** Ссылка на родителя карточки: карточка другого уровня или направление инициативы. */
export interface CardParentRef {
  kind: CardKind | 'direction';
  id: number;
}

/**
 * Родитель карточки по иерархии уровня.
 *
 * - инициатива живёт в направлении: родитель — направление (`directionId`);
 * - проект — родитель инициатива (`initiativeId`);
 * - задача — родитель проект (`projectId`).
 */
export function parentOf(card: Card): CardParentRef | null {
  if (card.kind === 'initiative') {
    return card.directionId === null ? null : { kind: 'direction', id: card.directionId };
  }

  if (card.kind === 'project') {
    return card.initiativeId === null ? null : { kind: 'initiative', id: card.initiativeId };
  }

  return card.projectId === null ? null : { kind: 'project', id: card.projectId };
}

/**
 * Есть ли у карточки родитель.
 *
 * Отдельная функция, а не `parentOf(card) !== null`: у карточки на каждом уровне
 * свой родитель (у инициативы — направление), и подпись «Без проекта» на
 * инициативе появляться не должна.
 */
export function hasParent(card: Card): boolean {
  return parentOf(card) !== null;
}

/** Поле иерархии, в которое кладётся родитель при создании карточки. */
export function parentFieldOf(kind: CardKind): 'direction_id' | 'initiative_id' | 'project_id' {
  if (kind === 'initiative') {
    return 'direction_id';
  }

  return kind === 'project' ? 'initiative_id' : 'project_id';
}
