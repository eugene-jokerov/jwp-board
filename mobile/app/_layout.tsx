import '../global.css';

import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';

import { AuthProvider } from '../src/auth/AuthContext';

/**
 * Корневой layout приложения (expo-router).
 * AuthProvider хранит текущую сессию; экраны читают её через useAuth().
 */
export default function RootLayout() {
  return (
    <AuthProvider>
      <StatusBar style="auto" />
      <Stack screenOptions={{ headerTitleAlign: 'center' }}>
        <Stack.Screen name="index" options={{ title: 'JWP Board' }} />
        <Stack.Screen name="auth" options={{ headerShown: false, title: 'Вход' }} />
      </Stack>
    </AuthProvider>
  );
}
