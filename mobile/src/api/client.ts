/**
 * Единственная точка обращения мобильного клиента к backend API.
 * Экраны не должны вызывать fetch напрямую — только функции из этого модуля.
 *
 * Два канала аутентификации:
 *  - iOS/Android: Bearer-токен в заголовке Authorization (SecureStore);
 *  - web: HttpOnly-cookie сессии. Она ставится сервером и уходит автоматически
 *    (credentials: 'include'), а JS дополнительно отправляет CSRF-заголовок.
 */

import { Platform } from 'react-native';

import { API_TIMEOUT_MS, API_URL } from '../config/env';
import type {
  ApiErrorPayload,
  AuthPayload,
  DataResponse,
  Direction,
  DirectionInput,
  DirectionListPayload,
  HealthPayload,
  RegisterInput,
  TaskCreateInput,
  TaskDetail,
  TaskListFilters,
  TaskListPayload,
  TaskUpdateInput,
  User,
  UserPayload,
  UserSettingEntry,
  UserSettingsInput,
} from './types';

/** Веб-клиент аутентифицируется сессионной cookie, а не токеном. */
const IS_WEB = Platform.OS === 'web';

/** Имя cookie с CSRF-токеном: её ставит Laravel, читает — наш JS. */
const CSRF_COOKIE = 'XSRF-TOKEN';

/** HTTP-методы, для которых Laravel требует CSRF-токен. */
const CSRF_PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

/** Ошибка запроса к API с HTTP-статусом и телом ответа. */
export class ApiError extends Error {
  public readonly status: number;

  public readonly payload: ApiErrorPayload;

  constructor(status: number, payload: ApiErrorPayload) {
    super(payload.message ?? `Request failed with status ${status}`);
    this.name = 'ApiError';
    this.status = status;
    this.payload = payload;
  }
}

/**
 * Ответ 401 означает, что сессия больше не действует: Bearer-токен истёк
 * (срок жизни — год) или отозван, cookie-сессия завершилась.
 *
 * Канал закрывается в единственном месте — здесь, а не в экранах: любой запрос
 * (доска, настройки, карточка) может получить 401, и все они должны привести
 * к одному результату — гостю на экране входа. Подписчики (AuthContext)
 * получают null и уводят пользователя на /auth.
 *
 * Слушатель регистрируется из AuthContext, чтобы у этого модуля не было
 * циклической зависимости с хранилищем сессии.
 */
type UnauthorizedListener = () => void;

const unauthorizedListeners = new Set<UnauthorizedListener>();
let unauthorizedNotified = false;

/** Сколько проверок сессии на входе сейчас подавляют уведомления о 401. */
let unauthenticatedChecks = 0;

/**
 * Подписка на «сессия истекла»; возвращает функцию отписки.
 */
export function onUnauthorized(listener: UnauthorizedListener): () => void {
  unauthorizedListeners.add(listener);

  return () => {
    unauthorizedListeners.delete(listener);
  };
}

/**
 * Сбросить признак «уже сообщили» — вызывается при новом входе.
 */
export function resetUnauthorizedNotice(): void {
  unauthorizedNotified = false;
}

/**
 * Включить ручной режим уведомлений на время проверки сессии.
 *
 * При старте приложения `restoreSession()` намеренно проверяет токен запросом
 * `GET /auth/me`, и 401 здесь — ожидаемый ответ «сессии нет», а не событие
 * «сессия истекла посреди работы». На время проверки уведомления подавляются,
 * чтобы гость не получал лишний сигнал; после — снова включаются.
 *
 * Возвращает функцию, которая возвращает прежнее поведение.
 */
export function suppressUnauthorizedNotice(): () => void {
  unauthenticatedChecks += 1;

  return () => {
    unauthenticatedChecks = Math.max(0, unauthenticatedChecks - 1);
  };
}

/**
 * Сообщить о 401 подписчикам.
 *
 * Несколько параллельных запросов получат 401 одновременно (загрузка доски и
 * настроек), а экран входа нужен один: повторные уведомления подавляются до
 * следующего входа.
 */
function notifyUnauthorized(): void {
  // Идёт проверка сессии на входе — 401 ожидаем, это не «истекла посреди работы».
  if (unauthorizedNotified || unauthenticatedChecks > 0) {
    return;
  }

  unauthorizedNotified = true;

  unauthorizedListeners.forEach((listener) => listener());
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  token?: string | null;
}

/**
 * CSRF-токен для stateful-запроса.
 *
 * Sanctum ставит cookie `XSRF-TOKEN` (не HttpOnly — её и должен прочитать JS).
 * Значение отправляется в заголовке как есть: расшифровкой занимается Laravel.
 */
function readCsrfToken(): string | null {
  if (!IS_WEB || typeof document === 'undefined') {
    return null;
  }

  const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${CSRF_COOKIE}=([^;]*)`));

  return match ? decodeURIComponent(match[1]) : null;
}

/**
 * Получить CSRF-cookie перед первым изменяющим запросом.
 *
 * Laravel ставит XSRF-TOKEN на любом stateful-ответе. Любой GET (включая 401)
 * его выдаёт, поэтому перед POST без токена достаточно «пустого» запроса —
 * иначе первый же вход вернул бы 419.
 */
async function ensureCsrfToken(): Promise<void> {
  if (readCsrfToken() !== null) {
    return;
  }

  // Токен из cookie нужен только stateful-запросам (веб). Если cookie нет,
  // её выдаёт любой ответ Laravel на этот адрес; достаточно GET, у которого
  // 401 ожидаем. Сеть могла упасть — тогда POST уйдёт без заголовка и вернёт
  // 419, как и раньше: это не повод закрывать сессию.
  try {
    await fetch(`${API_URL}/api/v1/auth/web/me`, {
      method: 'GET',
      credentials: IS_WEB ? 'include' : 'same-origin',
      headers: { Accept: 'application/json' },
    });
  } catch {
    // Set-Cookie не пришёл — важен только следующий запрос.
  }
}

/**
 * Базовый запрос к API с обработкой JSON и таймаута.
 */
async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), API_TIMEOUT_MS);
  const method = options.method ?? 'GET';
  const needsCsrf = CSRF_PROTECTED_METHODS.includes(method);

  // Первый POST из веба: cookie XSRF-TOKEN ещё нет — получаем её отдельным GET.
  if (IS_WEB && needsCsrf) {
    await ensureCsrfToken();
  }

  const csrfToken = needsCsrf ? readCsrfToken() : null;

  try {
    const response = await fetch(`${API_URL}/api/v1${path}`, {
      method,
      // На web обязателен: иначе браузер не приложит cookie сессии.
      credentials: IS_WEB ? 'include' : 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(options.body === undefined ? {} : { 'Content-Type': 'application/json' }),
        ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
        ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
      },
      body: options.body === undefined ? null : JSON.stringify(options.body),
      signal: controller.signal,
    });

    if (response.status === 204) {
      return undefined as T;
    }

    const payload = (await response.json().catch(() => null)) as T | ApiErrorPayload | null;

    if (!response.ok) {
      // 401 по защищённому адресу — сессия истекла: сообщаем подписчикам,
      // чтобы приложение вернулось на экран входа. Проверки сессии на входе
      // (`ensureCsrfToken`) сюда не попадают — они ходят через fetch напрямую.
      if (response.status === 401) {
        notifyUnauthorized();
      }

      throw new ApiError(response.status, (payload ?? {}) as ApiErrorPayload);
    }

    return payload as T;
  } finally {
    clearTimeout(timeout);
  }
}

/** Проверка доступности API. */
export async function fetchHealth(): Promise<HealthPayload> {
  const response = await request<DataResponse<HealthPayload>>('/health');

  return response.data;
}

/** Вход по email и паролю, возвращает токен Sanctum. */
export async function login(email: string, password: string, deviceName = 'mobile'): Promise<AuthPayload> {
  const response = await request<DataResponse<AuthPayload>>('/auth/login', {
    method: 'POST',
    body: { email, password, device_name: deviceName },
  });

  return response.data;
}

/** Регистрация по email и паролю (модуль User), возвращает токен Sanctum. */
export async function register(input: RegisterInput, deviceName = 'mobile'): Promise<AuthPayload> {
  const response = await request<DataResponse<AuthPayload>>('/user/register', {
    method: 'POST',
    body: { ...input, device_name: deviceName },
  });

  return response.data;
}

/** Данные текущего пользователя по токену. */
export async function fetchMe(token: string): Promise<User> {
  const response = await request<DataResponse<User>>('/auth/me', { token });

  return response.data;
}

/** Отзыв текущего токена. */
export async function logout(token: string): Promise<void> {
  await request<void>('/auth/logout', { method: 'POST', token });
}

/** Вход веб-клиента: сервер открывает сессию и ставит HttpOnly-cookie. */
export async function webLogin(email: string, password: string): Promise<User> {
  const response = await request<DataResponse<UserPayload>>('/auth/web/login', {
    method: 'POST',
    body: { email, password },
  });

  return response.data.user;
}

/** Регистрация веб-клиента: пользователь сразу авторизован (cookie в ответе). */
export async function webRegister(input: RegisterInput): Promise<User> {
  const response = await request<DataResponse<UserPayload>>('/auth/web/register', {
    method: 'POST',
    body: input,
  });

  return response.data.user;
}

/** Текущий пользователь по сессионной cookie (восстановление после перезагрузки). */
export async function webMe(): Promise<User> {
  const response = await request<DataResponse<User>>('/auth/web/me');

  return response.data;
}

/** Выход веб-клиента: сервер уничтожает сессию и гасит cookie. */
export async function webLogout(): Promise<void> {
  await request<void>('/auth/web/logout', { method: 'POST' });
}

/**
 * Заголовок авторизации для запросов доски.
 *
 * Тот же принцип, что у аутентификации: на native — Bearer-токен,
 * на web — сессионная cookie (её прикладывает браузер).
 */
function authHeaders(token: string | null): { token: string | null } {
  return { token: IS_WEB ? null : token };
}

/**
 * Список направлений пользователя вместе с лимитом (`meta.limit`).
 *
 * Лимит едет в ответе, чтобы счётчик «Направления n из k» брал число с сервера:
 * на клиенте константа лимита не дублируется.
 */
export async function fetchDirections(token: string | null): Promise<DirectionListPayload> {
  return request<DirectionListPayload>('/directions', authHeaders(token));
}

/** Создание направления. */
export async function createDirection(token: string | null, input: DirectionInput): Promise<Direction> {
  const response = await request<DataResponse<Direction>>('/directions', {
    method: 'POST',
    body: input,
    ...authHeaders(token),
  });

  return response.data;
}

/** Изменение направления: переданные поля заменяются. */
export async function updateDirection(
  token: string | null,
  directionId: number,
  input: Partial<DirectionInput>,
): Promise<Direction> {
  const response = await request<DataResponse<Direction>>(`/directions/${directionId}`, {
    method: 'PATCH',
    body: input,
    ...authHeaders(token),
  });

  return response.data;
}

/** Удаление направления; 409 — в направлении есть карточки. */
export async function deleteDirection(token: string | null, directionId: number): Promise<void> {
  await request<void>(`/directions/${directionId}`, { method: 'DELETE', ...authHeaders(token) });
}

/**
 * Список карточек с фильтрами доски — вместе со счётчиками `meta`.
 *
 * Карточки закрытых колонок на первичной загрузке урезаны, поэтому их полное
 * число нужно брать из `meta.closed_totals`, а не из длины `data`.
 */
export async function fetchTasks(token: string | null, filters: TaskListFilters = {}): Promise<TaskListPayload> {
  const query = Object.entries(filters)
    .filter(([, value]) => value !== undefined)
    .map(([key, value]) => `${key}=${encodeURIComponent(String(value))}`)
    .join('&');

  return request<TaskListPayload>(`/tasks${query ? `?${query}` : ''}`, authHeaders(token));
}

/**
 * Одна карточка в полном виде — с описанием.
 *
 * Список карточек описания не содержит, поэтому модалка редактирования
 * запрашивает карточку отдельно при каждом открытии.
 */
export async function fetchTask(token: string | null, taskId: number): Promise<TaskDetail> {
  const response = await request<DataResponse<TaskDetail>>(`/tasks/${taskId}`, authHeaders(token));

  return response.data;
}

/** Создание карточки; ответ — полное представление, включая описание. */
export async function createTask(token: string | null, input: TaskCreateInput): Promise<TaskDetail> {
  const response = await request<DataResponse<TaskDetail>>('/tasks', {
    method: 'POST',
    body: input,
    ...authHeaders(token),
  });

  return response.data;
}

/** Изменение карточки, включая смену колонки (409 — смену блокируют дети в работе). */
export async function updateTask(token: string | null, taskId: number, input: TaskUpdateInput): Promise<TaskDetail> {
  const response = await request<DataResponse<TaskDetail>>(`/tasks/${taskId}`, {
    method: 'PATCH',
    body: input,
    ...authHeaders(token),
  });

  return response.data;
}

/** Удаление карточки; 409 — есть дочерние карточки. */
export async function deleteTask(token: string | null, taskId: number): Promise<void> {
  await request<void>(`/tasks/${taskId}`, { method: 'DELETE', ...authHeaders(token) });
}

/**
 * Настройки пользователя списком пар «ключ — значение».
 *
 * Значения сервер отдаёт расшифрованным JSON, поэтому клиент получает объекты
 * (например, объект свёрнутых уровней), а не строки.
 */
export async function fetchUserSettings(token: string | null): Promise<UserSettingEntry[]> {
  const response = await request<DataResponse<UserSettingEntry[]>>('/user/settings', authHeaders(token));

  return response.data;
}

/**
 * Сохранение настроек: переданные ключи создаются или перезаписываются (UPSERT).
 *
 * Клиент шлёт только изменившиеся настройки; значение на сервере хранится
 * как JSON-текст, а в ответ приходит полное состояние пользователя.
 */
export async function updateUserSettings(
  token: string | null,
  settings: UserSettingsInput,
): Promise<UserSettingEntry[]> {
  const response = await request<DataResponse<UserSettingEntry[]>>('/user/settings', {
    method: 'PUT',
    body: { settings: serializeSettings(settings) },
    ...authHeaders(token),
  });

  return response.data;
}

/**
 * Сбросить настройку — вернуть её к значению по умолчанию.
 *
 * Сервер удаляет пару целиком (204); повторный запрос безопасен.
 */
export async function deleteUserSetting(token: string | null, key: string): Promise<void> {
  await request<void>(`/user/settings/${encodeURIComponent(key)}`, {
    method: 'DELETE',
    ...authHeaders(token),
  });
}

/**
 * Настройки → тело запроса.
 *
 * Сервер хранит значение текстом, поэтому каждый объект кодируется в JSON-строку
 * здесь, в единственной точке обращения к API, а не в экранах.
 */
function serializeSettings(settings: UserSettingsInput): Record<string, string> {
  return Object.fromEntries(Object.entries(settings).map(([key, value]) => [key, JSON.stringify(value)]));
}
