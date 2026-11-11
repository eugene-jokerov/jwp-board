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

import { ApiError, fetchMe, logout, suppressUnauthorizedNotice, webLogout, webMe } from '../api/client';
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

/**
 * Сохранить сессию после входа или регистрации (мобильный клиент).
 *
 * Возвращает сессию и управляет хранилищем; при повторном входе подписка
 * вызывается заново, поэтому экраны не кэшируют пользователя между входами.
 */
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

/**
 * Сбросить сессию на сервере: отозвать Bearer-токен или закрыть cookie-сессию.
 *
 * Нужен там, где сессия уже недействительна (401): повторный запрос вернёт
 * 401, и отказ проглатывается — для клиента это не ошибка, а уборка.
 */
export async function revokeServerSession(): Promise<void> {
  if (current === null) {
    return;
  }

  const token = current.token;

  try {
    if (IS_WEB) {
      await webLogout();
    } else if (token !== '') {
      await logout(token);
    }
  } catch {
    // Токен уже просрочен или отозван — серверу сказать нечего.
  }

  // Cookie сессии ставит сервер, и после `webLogout` она гасится только на
  // текущем домене. На web дополнительная уборка не нужна: следующий запрос
  // уйдёт с той же cookie, сервер ответит 401, а гвард всё равно покажет вход.
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
 *
 * В обоих каналах сессия проверяется запросом к серверу: сохранённый токен
 * мог истечь (срок жизни — год) или быть отозван, и тогда приложение должно
 * открыться как гость, а не показывать доску с ошибками загрузки.
 */
export async function restoreSession(): Promise<Session | null> {
  if (IS_WEB) {
    // 401 здесь — ожидаемый ответ «сессии нет», а не «сессия истекла посреди
    // работы»: уведомление подавляем на время проверки.
    const resume = suppressUnauthorizedNotice();

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
    } finally {
      resume();
    }

    return current;
  }

  const payload = await loadSession();

  if (payload === null) {
    return null;
  }

  // Так же при проверке сохранённого токена: истёкший токен — не событие,
  // о котором посреди экрана стоит сообщать.
  const resume = suppressUnauthorizedNotice();

  try {
    // Токен проверяется им же: `GET /auth/me` отвечает 401, если срок истёк.
    const user = await fetchMe(payload.token);

    current = toSession({ ...payload, user });
    // Обновлённые данные пользователя кладём и в хранилище: они могли
    // измениться на сервере, пока приложение было закрыто.
    void saveSession({ ...payload, user });
    notify();
  } catch (error) {
    // Сеть недоступна — не выкидываем пользователя: сессия могла быть живой.
    // 401 — токен больше не действует, локальную копию удаляем.
    if (error instanceof ApiError && error.status === 401) {
      await removeSession();

      return null;
    }

    current = toSession(payload);
    notify();
  } finally {
    resume();
  }

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
