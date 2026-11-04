import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Text, View } from 'react-native';

import { fetchHealth } from '../src/api/client';
import type { HealthPayload } from '../src/api/types';
import { useAuth } from '../src/auth/AuthContext';
import { PrimaryButton } from '../src/components/PrimaryButton';
import { API_URL } from '../src/config/env';

/**
 * Главный экран приложения (доступен только авторизованному пользователю —
 * гостя уводит на форму входа гвард из app/_layout.tsx).
 *
 * Сейчас это заглушка: показывает текущего пользователя и проверяет
 * доступность backend API.
 */
export default function HomeScreen() {
  const { session, signOut } = useAuth();

  const [health, setHealth] = useState<HealthPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [signingOut, setSigningOut] = useState(false);

  const check = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      setHealth(await fetchHealth());
    } catch (e) {
      setHealth(null);
      setError(e instanceof Error ? e.message : 'unknown error');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    // Первичная загрузка без синхронного setLoading(true): состояние уже
    // инициализировано как loading, а вызывать setState прямо в теле эффекта
    // нельзя — это каскадные рендеры. Флаг нужен, чтобы ответ не пришёл
    // в уже размонтированный компонент.
    let active = true;

    void fetchHealth()
      .then((payload) => {
        if (active) {
          setHealth(payload);
        }
      })
      .catch((e: unknown) => {
        if (active) {
          setHealth(null);
          setError(e instanceof Error ? e.message : 'unknown error');
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false);
        }
      });

    return () => {
      active = false;
    };
  }, []);

  const handleSignOut = async () => {
    setSigningOut(true);

    try {
      await signOut();
    } finally {
      // Навигацию на экран входа выполнит гвард, состояние формы сбросится при монтировании.
      setSigningOut(false);
    }
  };

  return (
    <View className="flex-1 bg-surface px-6 py-8">
      <View className="gap-1">
        <Text className="text-2xl font-bold text-app">JWP Board</Text>
        <Text className="text-sm text-muted">Mobile client (stub)</Text>
      </View>

      <View className="mt-6 gap-4">
        <View className="gap-1 rounded-xl bg-card p-4">
          <Text className="text-xs text-subtle">Вы вошли как</Text>
          <Text className="text-base font-medium text-app">{session?.user.email ?? '—'}</Text>
        </View>

        <View className="gap-1 rounded-xl bg-card p-4">
          <Text className="text-xs text-subtle">API</Text>
          <Text className="text-sm text-app">{API_URL}</Text>

          {loading ? (
            <View className="mt-2">
              <ActivityIndicator />
            </View>
          ) : null}

          {!loading && health ? (
            <Text className="mt-2 rounded-lg bg-ok-bg px-2.5 py-1.5 text-sm text-ok-fg">Status: {health.status}</Text>
          ) : null}

          {!loading && !health ? (
            <Text className="mt-2 rounded-lg bg-err-bg px-2.5 py-1.5 text-sm text-err-fg">No connection: {error}</Text>
          ) : null}
        </View>
      </View>

      <View className="mt-6 gap-3">
        <PrimaryButton label="Проверить снова" onPress={() => void check()} />
        <PrimaryButton label="Выйти" onPress={() => void handleSignOut()} loading={signingOut} />
      </View>
    </View>
  );
}
