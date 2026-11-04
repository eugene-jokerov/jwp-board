/**
 * Контекст аутентификации: вход, регистрация и выход.
 *
 * Экраны не вызывают API напрямую — они используют useAuth().
 * Канал выбирается по платформе:
 *  - веб: эндпоинты /auth/web/* и сессионная HttpOnly-cookie;
 *  - iOS/Android: /auth/login и /user/register, на выходе — Bearer-токен.
 *
 * При старте приложения сессия восстанавливается (SecureStore или cookie):
 * пока это происходит, isRestoring = true, и навигация ждёт, чтобы не показать
 * гостю экран входа авторизованному пользователю (и наоборот).
 */

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { Platform } from 'react-native';

import {
  login as loginRequest,
  logout as logoutRequest,
  register as registerRequest,
  webLogin,
  webLogout,
  webRegister,
} from '../api/client';
import type { Session } from './session';
import { clearSession, getSession, restoreSession, setSession, subscribe } from './session';

interface AuthContextValue {
  session: Session | null;
  isAuthenticated: boolean;
  /** true, пока сессия восстанавливается при старте приложения. */
  isRestoring: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (email: string, password: string, passwordConfirmation: string) => Promise<void>;
  signOut: () => Promise<void>;
}

/** Веб-клиент аутентифицируется сессионной cookie, а не токеном. */
const IS_WEB = Platform.OS === 'web';

const AuthContext = createContext<AuthContextValue | null>(null);

/** Записать в контекст пользователя веб-сессии (токена у неё нет). */
function applyWebUser(user: { id: number; email: string; created_at: string | null }): void {
  setSession({ token: '', token_type: 'Bearer', user });
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSessionState] = useState<Session | null>(() => getSession());
  const [isRestoring, setIsRestoring] = useState(true);

  useEffect(() => {
    const unsubscribe = subscribe(setSessionState);

    let active = true;

    void restoreSession().finally(() => {
      if (active) {
        setIsRestoring(false);
      }
    });

    return () => {
      active = false;
      unsubscribe();
    };
  }, []);

  const signIn = useCallback(async (email: string, password: string): Promise<void> => {
    if (IS_WEB) {
      applyWebUser(await webLogin(email.trim(), password));

      return;
    }

    const payload = await loginRequest(email.trim(), password);
    setSession(payload);
  }, []);

  const signUp = useCallback(
    async (email: string, password: string, passwordConfirmation: string): Promise<void> => {
      const input = {
        email: email.trim(),
        password,
        password_confirmation: passwordConfirmation,
      };

      if (IS_WEB) {
        // Регистрация на сайте сразу авторизует: сервер открывает сессию.
        applyWebUser(await webRegister(input));

        return;
      }

      setSession(await registerRequest(input));
    },
    [],
  );

  const signOut = useCallback(async (): Promise<void> => {
    if (IS_WEB) {
      await webLogout().catch(() => undefined);
      clearSession();

      return;
    }

    const token = getSession()?.token;

    // Токен мог уже истечь или быть отозван — локальную сессию очищаем в любом случае.
    if (token) {
      await logoutRequest(token).catch(() => undefined);
    }

    clearSession();
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      session,
      isAuthenticated: session !== null,
      isRestoring,
      signIn,
      signUp,
      signOut,
    }),
    [session, isRestoring, signIn, signUp, signOut],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

/** Доступ к контексту аутентификации. */
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (context === null) {
    throw new Error('useAuth должен использоваться внутри AuthProvider');
  }

  return context;
}
