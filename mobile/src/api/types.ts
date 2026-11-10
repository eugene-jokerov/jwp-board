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

/**
 * Счётчики списка направлений (`meta` рядом с `data`).
 *
 * `limit` — сколько направлений можно завести одному пользователю; приходит с
 * сервера, чтобы клиент показывал «Направления n из k» и не дублировал число
 * у себя. Лимит проверяет сервер (422 при превышении), клиент лишь показывает
 * счётчик и гасит кнопку создания.
 */
export interface DirectionListMeta {
  limit: number;
}

/**
 * Ответ списка направлений: данные и лимит рядом.
 *
 * Формат совпадает со списком карточек (`data` + `meta`): лимит — такая же
 * служебная информация, как счётчики колонок у `/tasks`.
 */
export interface DirectionListPayload {
  data: Direction[];
  meta: DirectionListMeta;
}

/**
 * Карточка доски в списке (GET /api/v1/tasks).
 *
 * Описания в списке нет: сервер отдаёт его только детальным представлением
 * (`GET /api/v1/tasks/{id}`), чтобы не тащить длинные тексты в выборку из
 * сотен карточек. Дедлайн, наоборот, есть и в списке — доска рисует дату
 * прямо в плитке карточки.
 */
export interface TaskSummary {
  id: number;
  type: CardType;
  column: CardColumn;
  title: string;
  /**
   * Кодовое название (slug): необязательная метка до 16 символов без
   * ограничений по символам; null — метки нет. Поле есть и в списке: оно
   * короткое, и форма редактирования берёт из него начальное значение.
   */
  slug: string | null;
  direction_id: number | null;
  initiative_id: number | null;
  project_id: number | null;
  /** Цвет направления карточки; null — направления нет. */
  color: string | null;
  /** Срок карточки в ISO-8601 (время — начало дня); null — срока нет. */
  deadline: string | null;
  /** Колонки, в которые карточку можно перевести сейчас. */
  allowed_transitions: CardColumn[];
  /**
   * Степень проработки: true — карточка ещё идея, false — готова к реализации.
   * Поле приходит всегда; смысл имеет в беклоге (там доска ставит идеи ниже
   * и рисует бейдж), а при смене колонки значение не сбрасывается.
   */
  idea: boolean;
  created_at: string | null;
  updated_at: string | null;
}

/**
 * Полное представление карточки: детальный просмотр и ответы создания/изменения.
 * Отличается от элемента списка описанием.
 */
export interface TaskDetail extends TaskSummary {
  description: string | null;
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
  /**
   * Степень проработки: true — идея, false — готова к реализации. Без поля
   * карточка создаётся готовой к реализации; вне беклога сервер флаг обнуляет.
   */
  idea?: boolean;
  /** Кодовое название (slug): необязательная метка до 16 символов; null — нет метки. */
  slug?: string | null;
  description?: string | null;
  /** Срок карточки: дата в формате `YYYY-MM-DD`; null — срока нет. */
  deadline?: string | null;
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
  data: TaskSummary[];
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
