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
  HealthPayload,
  RegisterInput,
  User,
  UserPayload,
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

  try {
    await request<void>('/auth/web/me');
  } catch {
    // 401 здесь ожидаем: важен только Set-Cookie в ответе.
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

    const payload = (await response.json()) as T | ApiErrorPayload;

    if (!response.ok) {
      throw new ApiError(response.status, payload as ApiErrorPayload);
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
