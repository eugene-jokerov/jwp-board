/**
 * Модель данных канбан-доски.
 *
 * Доска трёхуровневая: стратегический (инициативы по направлениям),
 * тактический (проекты по инициативам в работе) и операционный (задачи по проектам в работе).
 * Колонки на всех уровнях фиксированные.
 *
 * Словарь совпадает с API: тип `project`, колонка `complete`, а ссылки на
 * родителя — `directionId`, `initiativeId`, `projectId`. Клиент не вводит своих
 * синонимов, поэтому маппинг API → доска (src/board/mappers.ts) остаётся тонким.
 */

/** Колонки доски — одинаковый набор на всех уровнях. */
export type ColumnId = 'backlog' | 'in_progress' | 'complete' | 'canceled';

/** Уровни доски. */
export type LevelId = 'strategic' | 'tactical' | 'operational';

/** Тип карточки: имя узла в иерархии доски. */
export type CardKind = 'initiative' | 'project' | 'task';

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
  id: number;
  kind: CardKind;
  title: string;
  column: ColumnId;
  /**
   * Цвет наследуется от родителя. У карточки с родителем это ключ палитры
   * направлений (`c1`…`c19`) — то же значение, что хранится в базе; null —
   * карточка без направления (проект без инициативы, задача без проекта).
   */
  color: string | null;
  /** Направление карточки на любом уровне: null — карточка вне направлений. */
  directionId: number | null;
  /** Инициатива проекта: null — проекта нет (у задачи) или он не выбран. */
  initiativeId: number | null;
  /** Проект задачи: null — задача без проекта. */
  projectId: number | null;
  /**
   * Колонки, в которые карточку можно перевести прямо сейчас. Список считает
   * доменная политика на сервере: клиент только отображает доступные переходы
   * и не дублирует правило «есть зависимые в работе».
   */
  allowedTransitions: ColumnId[];
}

/** Направление деятельности: родитель инициативы на стратегическом уровне. */
export interface Direction {
  id: number;
  title: string;
  /**
   * Ключ цвета из палитры направлений (`c1`…`c19`) — универсальное значение,
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
  { id: 'complete', label: 'Готово' },
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
  project: 'Проект',
  task: 'Задача',
};

/** Подпись родителя в форме создания: у инициативы родитель — направление. */
export const PARENT_LABEL: Record<CardKind, string> = {
  initiative: 'Направление',
  project: 'Инициатива',
  task: 'Проект',
};

/** Тип родительской карточки для каждого уровня: у стратегического родителя нет. */
export const PARENT_KIND: Record<LevelId, CardKind | null> = {
  strategic: null,
  tactical: 'initiative',
  operational: 'project',
};

/** Уровень, к которому относится карточка указанного типа. */
export const KIND_LEVEL: Record<CardKind, LevelId> = {
  initiative: 'strategic',
  project: 'tactical',
  task: 'operational',
};
