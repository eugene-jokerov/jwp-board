/**
 * Хранилище текущей сессии мобильного клиента.
 *
 * Токен живёт в памяти процесса: внешние хранилища (SecureStore/AsyncStorage)
 * не подключены. Модуль — единственная точка чтения/записи токена, экраны
 * работают с ним через AuthContext.
 */

import type { AuthPayload, TokenType, User } from '../api/types';

/** Текущая сессия: токен доступа и данные пользователя. */
export interface Session {
  token: string;
  tokenType: TokenType;
  user: User;
}

type Listener = (session: Session | null) => void;

let current: Session | null = null;
const listeners = new Set<Listener>();

function notify(): void {
  listeners.forEach((listener) => listener(current));
}

/** Сохранить сессию после входа или регистрации. */
export function setSession(payload: AuthPayload): Session {
  current = {
    token: payload.token,
    tokenType: payload.token_type,
    user: payload.user,
  };
  notify();

  return current;
}

/** Текущая сессия или null для гостя. */
export function getSession(): Session | null {
  return current;
}

/** Токен доступа (null, если пользователь не аутентифицирован). */
export function getToken(): string | null {
  return current?.token ?? null;
}

/** Очистить сессию (выход или истёкший токен). */
export function clearSession(): void {
  current = null;
  notify();
}

/** Подписка на изменения сессии; возвращает функцию отписки. */
export function subscribe(listener: Listener): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}
