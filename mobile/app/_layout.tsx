import '../global.css';

import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';

import { AuthProvider, useAuth } from '../src/auth/AuthContext';

/**
 * Корневой layout приложения (expo-router).
 * AuthProvider хранит текущую сессию и восстанавливает её из SecureStore;
 * RootNavigator пускает гостя только на экран входа, авторизованного — на главный.
 */
export default function RootLayout() {
  return (
    <AuthProvider>
      <StatusBar style="auto" />
      <RootNavigator />
    </AuthProvider>
  );
}

/**
 * Навигация с гвардом доступа.
 *
 * Экран входа — сегмент `auth`, остальные экраны — приватная часть приложения.
 * Источник правды — сессия из AuthContext: после входа/регистрации она меняется,
 * и гвард сам уводит пользователя на главный экран (ручной router.replace в
 * форме не нужен). Пока идёт первый редирект, показываем заглушку, чтобы
 * не мелькал недоступный экран.
 */
function RootNavigator() {
  const { isAuthenticated, isRestoring } = useAuth();
  const segments = useSegments();
  const router = useRouter();

  const inAuthScreen = segments[0] === 'auth';
  const needsRedirect = !isRestoring && isAuthenticated === inAuthScreen;

  useEffect(() => {
    if (needsRedirect) {
      router.replace(isAuthenticated ? '/' : '/auth');
    }
  }, [needsRedirect, isAuthenticated, router]);

  // Нужен редирект — показываем заглушку, чтобы не мелькал недоступный экран.
  // Значение производное от состояния, поэтому вычисляется на рендере,
  // а не синхронизируется через setState в эффекте.
  if (isRestoring || needsRedirect) {
    return (
      <View className="flex-1 items-center justify-center bg-surface">
        <ActivityIndicator color="#111827" />
      </View>
    );
  }

  return (
    <Stack screenOptions={{ headerTitleAlign: 'center' }}>
      <Stack.Screen name="index" options={{ title: 'JWP Board' }} />
      <Stack.Screen name="auth" options={{ headerShown: false, title: 'Вход' }} />
    </Stack>
  );
}
