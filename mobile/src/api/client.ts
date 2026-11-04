/**
 * Единственная точка обращения мобильного клиента к backend API.
 * Экраны не должны вызывать fetch напрямую — только функции из этого модуля.
 */

import { API_TIMEOUT_MS, API_URL } from '../config/env';
import type {
  ApiErrorPayload,
  AuthPayload,
  DataResponse,
  HealthPayload,
  RegisterInput,
  User,
} from './types';

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
 * Базовый запрос к API с обработкой JSON и таймаута.
 */
async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), API_TIMEOUT_MS);

  try {
    const response = await fetch(`${API_URL}/api/v1${path}`, {
      method: options.method ?? 'GET',
      headers: {
        Accept: 'application/json',
        ...(options.body === undefined ? {} : { 'Content-Type': 'application/json' }),
        ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
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
