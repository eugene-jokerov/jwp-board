/**
 * Персистентное хранилище сессии мобильного клиента.
 *
 * Только для iOS/Android: токен лежит в SecureStore (Keychain/Keystore).
 * На web ничего не сохраняется — там аутентификация держится на HttpOnly-cookie
 * сессии, которую ставит сервер; JS к ней доступа не имеет (и это цель).
 *
 * Модуль не бросает исключений наружу: сбой хранилища не должен ломать
 * аутентификацию, worst case — сессия просто не переживёт перезапуск.
 */

import * as SecureStore from 'expo-secure-store';

import type { AuthPayload } from '../api/types';

/** Ключ записи сессии в хранилище. */
const SESSION_KEY = 'jwp-board.session';

/** Сохранить ответ входа/регистрации (токен и пользователя). */
export async function saveSession(payload: AuthPayload): Promise<void> {
  try {
    await SecureStore.setItemAsync(SESSION_KEY, JSON.stringify(payload));
  } catch {
    // Не удалось сохранить — сессия останется только в памяти.
  }
}

/** Восстановить сохранённый ответ API; null, если записи нет или она повреждена. */
export async function loadSession(): Promise<AuthPayload | null> {
  try {
    const raw = await SecureStore.getItemAsync(SESSION_KEY);

    if (raw === null) {
      return null;
    }

    const payload = JSON.parse(raw) as Partial<AuthPayload>;

    if (typeof payload.token !== 'string' || payload.token === '' || !payload.user) {
      return null;
    }

    return payload as AuthPayload;
  } catch {
    return null;
  }
}

/** Удалить сохранённую сессию (выход). */
export async function removeSession(): Promise<void> {
  try {
    await SecureStore.deleteItemAsync(SESSION_KEY);
  } catch {
    // Не удалилось — запись будет перезаписана при следующем входе.
  }
}
