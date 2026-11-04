/**
 * Контекст аутентификации: вход, регистрация и выход.
 *
 * Экраны не вызывают API напрямую — они используют useAuth().
 */

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

import { login as loginRequest, logout as logoutRequest, register as registerRequest } from '../api/client';
import type { Session } from './session';
import { clearSession, getSession, setSession, subscribe } from './session';

interface AuthContextValue {
  session: Session | null;
  isAuthenticated: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (email: string, password: string, passwordConfirmation: string) => Promise<void>;
  signOut: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSessionState] = useState<Session | null>(() => getSession());

  useEffect(() => subscribe(setSessionState), []);

  const signIn = useCallback(async (email: string, password: string): Promise<void> => {
    const payload = await loginRequest(email.trim(), password);
    setSession(payload);
  }, []);

  const signUp = useCallback(
    async (email: string, password: string, passwordConfirmation: string): Promise<void> => {
      const payload = await registerRequest({
        email: email.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });
      setSession(payload);
    },
    [],
  );

  const signOut = useCallback(async (): Promise<void> => {
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
      signIn,
      signUp,
      signOut,
    }),
    [session, signIn, signUp, signOut],
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
