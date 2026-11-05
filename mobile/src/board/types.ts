/**
 * Модель данных канбан-доски (пока только визуал, без backend).
 *
 * Доска трёхуровневая: стратегический (инициативы по направлениям),
 * тактический (проекты по инициативам в работе) и операционный (задачи по проектам в работе).
 * Колонки на всех уровнях фиксированные.
 */

/** Колонки доски — одинаковый набор на всех уровнях. */
export type ColumnId = 'backlog' | 'in_progress' | 'done' | 'canceled';

/** Уровни доски. */
export type LevelId = 'strategic' | 'tactical' | 'operational';

/** Тип карточки: имя узла в иерархии доски. */
export type CardKind = 'initiative' | 'epic' | 'task';

/** Направление перевода карточки между соседними колонками. */
export type MoveDirection = 'prev' | 'next';

export interface BoardColumn {
  id: ColumnId;
  label: string;
}

export interface BoardLevel {
  id: LevelId;
  label: string;
}

export interface Card {
  id: string;
  kind: CardKind;
  title: string;
  column: ColumnId;
  /**
   * Цвет наследуется от родителя. У карточки с родителем это ключ палитры
   * направлений (`c1`…`c20`) — то же значение, что хранится в базе; null —
   * карточка без цвета (задача без проекта).
   */
  color: string | null;
  /** Родительская карточка: инициатива для проекта, проект для задачи. */
  parentId: string | null;
}

/** Направление деятельности: родитель инициативы на стратегическом уровне. */
export interface Direction {
  id: string;
  title: string;
  /**
   * Ключ цвета из палитры направлений (`c1`…`c20`) — универсальное значение,
   * которое хранится в базе данных. Оттенок для интерфейса даёт
   * `directionColorHex` из `directionColors.ts`.
   */
  color: string;
}

/** Родитель, которого можно выбрать в форме: карточка или направление. */
export type ParentOptionModel = Card | Direction;

/** Порядок колонок слева направо: по нему считаются соседние колонки. */
export const COLUMNS: readonly BoardColumn[] = [
  { id: 'backlog', label: 'Беклог' },
  { id: 'in_progress', label: 'В работе' },
  { id: 'done', label: 'Готово' },
  { id: 'canceled', label: 'Отмена' },
];

export const LEVELS: readonly BoardLevel[] = [
  { id: 'strategic', label: 'Стратегический уровень' },
  { id: 'tactical', label: 'Тактический уровень' },
  { id: 'operational', label: 'Операционный уровень' },
];

/** Подпись типа карточки в единственном числе (для кнопок создания). */
export const KIND_LABEL: Record<CardKind, string> = {
  initiative: 'Инициатива',
  epic: 'Проект',
  task: 'Задача',
};

/** Подпись родителя в форме создания: у инициативы родитель — направление. */
export const PARENT_LABEL: Record<CardKind, string> = {
  initiative: 'Направление',
  epic: 'Инициатива',
  task: 'Проект',
};

/** Тип родительской карточки для каждого уровня: у стратегического родителя нет. */
export const PARENT_KIND: Record<LevelId, CardKind | null> = {
  strategic: null,
  tactical: 'initiative',
  operational: 'epic',
};

/** Уровень, к которому относится карточка указанного типа. */
export const KIND_LEVEL: Record<CardKind, LevelId> = {
  initiative: 'strategic',
  epic: 'tactical',
  task: 'operational',
};
