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
