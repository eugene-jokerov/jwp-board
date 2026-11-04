// ESLint (flat config) мобильного клиента.
// База — eslint-config-expo: JSX/TS, платформенные расширения (.web/.ios/.android),
// глобальные переменные React Native и web-среды, правила react-hooks.
// Форматирование отдано Prettier (.prettierrc) — стилевые правила здесь не дублируем.
const expoConfig = require('eslint-config-expo/flat');
const { defineConfig } = require('eslint/config');

module.exports = defineConfig([
  expoConfig,
  {
    // Не линтуем сборки, кэш Expo, сгенерированные типы и файлы вне JS/TS.
    ignores: [
      'node_modules/**',
      '.expo/**',
      'dist/**',
      'web-build/**',
      'expo-env.d.ts',
      'nativewind-env.d.ts',
      'global.css',
    ],
  },
  {
    // Alias `@/*` объявлен в tsconfig.json: без явного project
    // import/no-unresolved не разрешает такие пути.
    settings: {
      'import/resolver': {
        typescript: { project: './tsconfig.json' },
      },
    },
    rules: {
      // Фронт один: приложение собирается и для native, и для web (react-native-web).
      // Ветвление по Platform.OS — штатный выбор транспорта (Bearer-токен vs cookie),
      // поэтому правила о «недостижимых web-ветках» здесь не включаем.
      '@typescript-eslint/no-unnecessary-condition': 'off',
      'import/no-unresolved': ['error', { ignore: ['\\.css$'] }],
    },
  },
]);
