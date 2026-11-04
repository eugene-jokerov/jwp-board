/**
 * Хранилище текущей сессии мобильного клиента.
 *
 * Два канала:
 *  - iOS/Android: Bearer-токен живёт в памяти и дублируется в SecureStore,
 *    чтобы вход переживал перезапуск приложения;
 *  - web: ничего не сохраняется — сессию держит HttpOnly-cookie на сервере,
 *    поэтому восстановление идёт запросом GET /api/v1/auth/web/me.
 *
 * Модуль — единственная точка чтения/записи сессии, экраны работают с ним
 * через AuthContext.
 */

import { Platform } from 'react-native';

import { webMe } from '../api/client';
import type { AuthPayload, TokenType, User } from '../api/types';
import { loadSession, removeSession, saveSession } from './storage';

/** Текущая сессия: токен доступа и данные пользователя. */
export interface Session {
  token: string;
  tokenType: TokenType;
  user: User;
}

type Listener = (session: Session | null) => void;

/** Веб-клиент аутентифицируется cookie, токена у него нет. */
const IS_WEB = Platform.OS === 'web';

let current: Session | null = null;
const listeners = new Set<Listener>();

function notify(): void {
  listeners.forEach((listener) => listener(current));
}

function toSession(payload: AuthPayload): Session {
  return {
    token: payload.token,
    tokenType: payload.token_type,
    user: payload.user,
  };
}

/** Сохранить сессию после входа или регистрации (мобильный клиент). */
export function setSession(payload: AuthPayload): Session {
  current = toSession(payload);

  // У веб-сессии токена нет (аутентификация держится на cookie) — SecureStore
  // тогда не трогаем: сохранять в нём нечего.
  if (payload.token !== '') {
    // Запись асинхронная и не нужна для UI — не дожидаемся её.
    void saveSession(payload);
  }

  notify();

  return current;
}

/** Текущая сессия или null для гостя. */
export function getSession(): Session | null {
  return current;
}

/** Токен доступа (null, если пользователь не аутентифицирован или это web). */
export function getToken(): string | null {
  return current?.token ?? null;
}

/**
 * Восстановить сессию при старте приложения.
 *
 * На web единственный признак входа — сессионная cookie: спрашиваем сервер.
 * На iOS/Android читаем сохранённый Bearer-токен из SecureStore.
 */
export async function restoreSession(): Promise<Session | null> {
  if (IS_WEB) {
    try {
      const user = await webMe();

      // У веб-сессии нет токена: он нужен только мобильному клиенту
      // для заголовка Authorization.
      current = { token: '', tokenType: 'Bearer', user };
      notify();
    } catch {
      // 401 — сессии нет (или истекла): пользователь остаётся гостем.
      current = null;
      notify();
    }

    return current;
  }

  const payload = await loadSession();

  if (payload === null) {
    return null;
  }

  current = toSession(payload);
  notify();

  return current;
}

/** Очистить сессию (выход или истёкший токен). */
export function clearSession(): void {
  current = null;

  if (!IS_WEB) {
    void removeSession();
  }

  notify();
}

/** Подписка на изменения сессии; возвращает функцию отписки. */
export function subscribe(listener: Listener): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

