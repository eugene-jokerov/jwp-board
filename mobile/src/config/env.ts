/**
 * Конфигурация окружения мобильного приложения.
 *
 * Значения приходят из переменных EXPO_PUBLIC_* (файл .env или переменные
 * контейнера node в docker-compose.yml). Адреса API в коде не хардкодим.
 */

const DEFAULT_API_URL = 'http://localhost:8080';

/** Базовый URL API без завершающего слэша. */
export const API_URL: string = (process.env.EXPO_PUBLIC_API_URL ?? DEFAULT_API_URL).replace(/\/+$/, '');

/** Таймаут запросов к API, мс. */
export const API_TIMEOUT_MS: number = 15000;
