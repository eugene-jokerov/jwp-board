/**
 * Типы ответов backend API (/api/v1).
 * Описываются вручную и синхронизируются с Laravel JsonResource.
 */

/** Обёртка успешного ответа с ресурсом({ data: ... }). */
export interface DataResponse<T> {
  data: T;
}

/** GET /api/v1/health */
export interface HealthPayload {
  status: string;
  environment: string;
  timestamp: string;
}

/** Пользователь в ответах API. */
export interface User {
  id: number;
  email: string;
  created_at: string | null;
}

/** Тип токена, который выдаёт API (заголовок Authorization). */
export type TokenType = 'Bearer';

/** Ответ входа и регистрации: токен доступа и пользователь. */
export interface AuthPayload {
  token: string;
  token_type: TokenType;
  user: User;
}

/** Ответ веб-аутентификации: только пользователь (доказательство — cookie). */
export interface UserPayload {
  user: User;
}

/** POST /api/v1/auth/login */
export type LoginPayload = AuthPayload;

/** Данные регистрации: POST /api/v1/user/register */
export interface RegisterInput {
  email: string;
  password: string;
  password_confirmation: string;
}

/** Тело ошибки валидации/авторизации. */
export interface ApiErrorPayload {
  message?: string;
  errors?: Record<string, string[]>;
}

/** Колонка канбан-доски. */
export type CardColumn = 'backlog' | 'in_progress' | 'complete' | 'canceled';

/** Тип карточки: имя узла в иерархии доски. */
export type CardType = 'initiative' | 'project' | 'task';

/** Направление движения по жизни (GET /api/v1/directions). */
export interface Direction {
  id: number;
  title: string;
  /** Ключ палитры направлений (`c1`…`c19`). */
  color: string;
}

/** Карточка доски (GET /api/v1/tasks). */
export interface Task {
  id: number;
  type: CardType;
  column: CardColumn;
  title: string;
  description: string | null;
  direction_id: number | null;
  initiative_id: number | null;
  project_id: number | null;
  /** Цвет направления карточки; null — направления нет. */
  color: string | null;
  /** Колонки, в которые карточку можно перевести сейчас. */
  allowed_transitions: CardColumn[];
  created_at: string | null;
  updated_at: string | null;
}

/** Тело создания направления. */
export interface DirectionInput {
  title: string;
  color: string;
}

/** Тело создания карточки. */
export interface TaskCreateInput {
  type: CardType;
  column: CardColumn;
  title: string;
  description?: string | null;
  direction_id?: number | null;
  initiative_id?: number | null;
  project_id?: number | null;
}

/** Тело изменения карточки: любое подмножество полей, включая колонку. */
export type TaskUpdateInput = Partial<TaskCreateInput>;

/**
 * Пользовательская настройка интерфейса (GET /api/v1/user/settings).
 *
 * Сервер хранит значение строкой и отдаёт расшифрованный JSON, поэтому тип
 * значения на клиенте — неизвестное: конкретную форму знает тот, кто настройку
 * читает (см. `BoardSettings` в src/board/settings.ts).
 */
export interface UserSettingEntry {
  /** Ключ настройки в пространстве имён приложения, например `board.collapsed_levels`. */
  key: string;
  value: unknown;
}

/** Набор настроек для сохранения: ключ => значение (уйдёт на сервер как JSON). */
export type UserSettingsInput = Record<string, unknown>;

/**
 * Счётчики списка карточек (`meta` рядом с `data`).
 *
 * `columns` — сколько карточек под фильтром в каждой колонке, `closed_totals` —
 * полное число карточек по паре «уровень + закрытая колонка». В закрытых
 * колонках сам список на первичной загрузке урезан (`data` содержит не больше
 * 10 карточек пары), поэтому полное число берётся из `closed_totals`, а не из
 * длины полученного массива.
 */
export interface TaskListMeta {
  /** Всего карточек под фильтром. */
  total: number;
  /** Число карточек каждой колонки под фильтром. */
  columns: Record<CardColumn, number>;
  /**
   * Закрытые колонки: «Готово» и «Отмена» → «тип карточки → всего карточек».
   * Ключи всегда присутствуют (сервер подставляет нули), тип — все три.
   */
  closed_totals: Record<'complete' | 'canceled', Partial<Record<CardType, number>>>;
}

/** Ответ списка карточек: данные и счётчики рядом. */
export interface TaskListPayload {
  data: Task[];
  meta: TaskListMeta;
}

/** Фильтры списка карточек. */
export interface TaskListFilters {
  type?: CardType;
  column?: CardColumn;
  direction_id?: number;
  initiative_id?: number;
  project_id?: number;
  /**
   * Размер страницы: так догружаются следующие карточки закрытой колонки
   * («Показать ещё», CLOSED_PAGE_SIZE). Передаётся только вместе с `column` —
   * закрытые колонки адресуются парой «уровень + колонка».
   */
  limit?: number;
  /** Смещение страницы: число уже загруженных карточек пары. Только с `limit`. */
  offset?: number;
  /** Направление сортировки карточек по идентификатору. */
  order?: 'asc' | 'desc';
}
