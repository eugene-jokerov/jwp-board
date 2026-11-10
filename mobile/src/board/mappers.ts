/**
 * Соответствие моделей API и модели доски.
 *
 * Единственное место, где живёт перевод `direction_id` / `initiative_id` /
 * `project_id` в плоские поля карточки и обратно. Компоненты доски про API не знают,
 * `client.ts` — про модель доски: связывает их этот модуль.
 *
 * Описание карточки в модель доски не попадает: список его не отдаёт, а
 * детальное представление читает модалка редактирования напрямую.
 */

import type {
  CardColumn,
  CardType,
  Direction as ApiDirection,
  TaskDetail,
  TaskSummary,
  TaskListMeta,
} from '../api/types';
import type { Card, CardKind, ClosedTotals, ColumnId, Direction } from './types';
import { DEFAULT_CARD_PRIORITY } from './types';

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

/** Карточка API в модель доски; поля доски есть и в кратком, и в полном виде. */
export function toCard(task: TaskSummary): Card {
  return {
    id: task.id,
    kind: toCardKind(task.type),
    title: task.title,
    column: toColumnId(task.column),
    // Приоритет сервер отдаёт всегда, но клиент не полагается на это: ответ
    // без поля (старый сервер, ручная правка данных) читается как средний.
    priority: task.priority ?? DEFAULT_CARD_PRIORITY,
    color: task.color,
    slug: task.slug ?? null,
    directionId: task.direction_id,
    initiativeId: task.initiative_id,
    projectId: task.project_id,
    deadline: task.deadline,
    idea: task.idea === true,
    allowedTransitions: task.allowed_transitions.map(toColumnId),
  };
}

/**
 * Описание карточки из полного представления: пустой текст — это null.
 *
 * Сервер уже нормализует пустую строку в null (TaskDescription), но ответ мог
 * быть получен после ручной правки данных, поэтому клиент не полагается на это.
 */
export function toCardDescription(task: TaskDetail): string | null {
  const description = task.description?.trim() ?? '';

  return description === '' ? null : description;
}

/**
 * Кодовое название (slug) из полного представления: пустая строка — это null.
 *
 * Как и с описанием, клиент не полагается на нормализацию сервера: форма
 * показывает поле пустым, если метки нет.
 */
export function toCardSlug(task: TaskDetail): string | null {
  const slug = task.slug?.trim() ?? '';

  return slug === '' ? null : slug;
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
