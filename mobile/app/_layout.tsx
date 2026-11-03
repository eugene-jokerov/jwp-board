import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

/**
 * Корневой layout приложения (expo-router).
 * Заглушка: один экран, навигация будет добавлена по требованиям продукта.
 */
export default function RootLayout() {
  return (
    <>
      <StatusBar style="auto" />
      <Stack screenOptions={{ headerTitleAlign: 'center' }}>
        <Stack.Screen name="index" options={{ title: 'JWP Board' }} />
      </Stack>
    </>
  );
}
