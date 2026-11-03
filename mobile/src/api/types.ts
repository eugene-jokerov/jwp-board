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
  name: string;
  email: string;
  created_at: string | null;
}

/** POST /api/v1/auth/login */
export interface LoginPayload {
  token: string;
  user: User;
}

/** Тело ошибки валидации/авторизации. */
export interface ApiErrorPayload {
  message?: string;
  errors?: Record<string, string[]>;
}
