import '../global.css';

import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { ActivityIndicator, Platform, View } from 'react-native';

import { AuthProvider, useAuth } from '../src/auth/AuthContext';
import { BRAND_NAME, BrandTitle } from '../src/components/BrandTitle';

/**
 * Лого и слоган в заголовке: вместо одной строки «JWP Board» — название и под
 * ним слоган. Размеры совпадают со стандартным заголовком стека (web — 18 px
 * medium), а `overflow` — половина строки слогана: центр заголовка ограничен
 * высотой хедера, и без смещения слоган обрезался бы снизу.
 */
const HEADER_TITLE_FONT_SIZE = 18;
const HEADER_TITLE_OVERFLOW = 8;

function headerTitle() {
  return (
    <BrandTitle overflow={HEADER_TITLE_OVERFLOW} color="#111827" fontSize={HEADER_TITLE_FONT_SIZE} fontWeight="500" />
  );
}

/**
 * Заголовок экрана.
 *
 * На web заголовок рисуется обычными элементами, и туда передаётся кастомный
 * `headerTitle` с лого и слоганом. На native заголовок — системный, кастомный
 * компонент в нём не поддерживается: там остаётся название продукта одной
 * строкой, а слоган виден сразу в шапке экрана и на форме входа.
 */
const SCREEN_OPTIONS = {
  headerTitleAlign: 'center' as const,
  ...(Platform.OS === 'web' ? { headerTitle } : null),
};

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
    <Stack screenOptions={SCREEN_OPTIONS}>
      <Stack.Screen name="index" options={{ title: BRAND_NAME }} />
      <Stack.Screen name="auth" options={{ headerShown: false, title: 'Вход' }} />
    </Stack>
  );
}
