# JWP Board

Монорепозиторий проекта **JWP Board**: headless API на Laravel, мобильный клиент на React Native (Expo)
и публичный сайт с веб-версией приложения.

- **`backend/`** — Laravel 13: JSON API (`/api/v1/*`), аутентификация по токенам Sanctum, а также лендинг по `/`.
- **`mobile/`** — Expo SDK 58 + React Native (TypeScript); собирается для iOS/Android и для web.
- **Веб-версия** — тот же клиент, собранный для web: `http://localhost:8080/board/`.
- **Docker** — PHP 8.4 (php-fpm) + nginx + MySQL 8.4 + Node 24.

Подробные правила разработки (архитектура, соглашения, команды, ограничения) — в [`AGENTS.md`](./AGENTS.md).

---

## Требования

- Docker 29+ и Docker Compose v2.30+ (`docker compose`).
- Свободные порты на хосте: `8080` (API), `8081` (Metro/Expo), `3307` (MySQL), `3308` (MySQL test), `8082` (phpMyAdmin, опционально), `19000–19002` (Expo devtools).
- Локальные PHP/Node для разработки **не нужны**: на хосте должен быть только `make` и `docker`.

## Первый запуск

Репозиторий содержит только исходный код: образы, PHP-зависимости (`backend/vendor`) и
npm-зависимости (`mobile/node_modules`) скачиваются при первом запуске.

```bash
# 1. Файлы окружения
make init-envs

# 2. Сборка образа php (php:8.4-fpm + расширения + composer + xdebug)
make build

# 3. Поднять контейнеры (mysql, mysql-test, php, nginx, node)
make up

# 4. Зависимости бэкенда + ключ приложения + миграции
make composer cmd="install"
make artisan cmd="key:generate"
make migrate

# 5. Зависимости мобильного клиента
make expo-install

# 6. Проверка
curl -s http://localhost:8080/api/v1/health
```

Всё вместе выполняет `make install`.

### Если сеть медленная

Скачивание образов и пакетов можно растянуть по времени — команды идемпотентны:

```bash
docker pull php:8.4-fpm          # базовый образ, ~150 МБ
docker pull node:24-alpine       # ~140 МБ
docker pull mysql:8.4            # ~250 МБ
docker pull nginx:1.27-alpine    # ~75 МБ
docker compose build php         # сборка образа php (pecl xdebug, расширения)
make composer cmd="install"      # ~120 МБ пакетов
make npm cmd="install"           # зависимости Expo (~500 МБ)
```

Если `composer install` прервался — просто запустите его снова, кэш
(`~/.cache/composer`) не потеряется.

Ожидаемый ответ `GET /api/v1/health`:

```json
{"data":{"status":"ok","environment":"local","timestamp":"2026-11-03T10:00:00+03:00"}}
```

## Порты

| Сервис | Порт | Адрес |
| --- | --- | --- |
| API (nginx) | 8080 | http://localhost:8080/api/v1/health |
| Лендинг | 8080 | http://localhost:8080/ |
| Веб-версия приложения | 8080 | http://localhost:8080/board/ (`make web-build`) |
| Expo / Metro | 8081 | http://localhost:8081 |
| MySQL | 3307 | `127.0.0.1:3307`, БД `jwp_board`, пользователь `jwp`/`jwp` |
| MySQL (tests) | 3308 | `127.0.0.1:3308`, БД `jwp_board_test` |
| phpMyAdmin | 8082 | http://localhost:8082 — `make phpmyadmin` (профиль `tools`); серверы `mysql` и `mysql-test` (`?server=2`) |

## Основные команды

Полный список — `make help`.

```bash
make up                 # поднять контейнеры
make down               # остановить и удалить контейнеры
make sh                 # bash в контейнере php
make phpmyadmin         # phpMyAdmin на http://localhost:8082 (основная и тестовая БД)
make artisan cmd="migrate:status"
make test               # PHPUnit
make pint-test          # проверка стиля (Laravel Pint)
make phpstan            # статический анализ (PHPStan + Larastan)
make check              # test + pint-test + phpstan
make typecheck          # проверка типов мобильного приложения
make lint               # ESLint мобильного приложения
make format-check       # проверка форматирования (Prettier)
make check-mobile       # typecheck + lint + format-check
```

## Работа с мобильным клиентом

Dev-сервер Expo запускается сервисом `node` и доступен на http://localhost:8081.

1. Установите зависимости: `make expo-install` (или `make npm cmd="install"`).
2. Откройте http://localhost:8081 — QR-код для Expo Go / браузер со списком платформ.
3. Для запуска на физическом устройстве API должен быть доступен по IP хоста в локальной сети:

```bash
# в корневом .env
EXPO_PUBLIC_API_URL=http://192.168.1.10:8080
```

после чего `make restart`.

> Приложение на Android-эмуляторе обращается к хосту по адресу `10.0.2.2`, поэтому
> `EXPO_PUBLIC_API_URL=http://10.0.2.2:8080`.

### Стили: NativeWind (Tailwind CSS)

Мобильный клиент стилизуется через NativeWind 4 — Tailwind-классы в `className`:

```tsx
<View className="flex-1 items-center justify-center bg-surface p-6">
  <Text className="text-xl font-bold text-app">JWP Board</Text>
</View>
```

- `mobile/tailwind.config.js` — сканируемые файлы (`app/**`, `src/**`), пресет `nativewind/preset`, палитра (`app`, `surface`, `card`, `muted`, `subtle`, `ok`, `err`).
- `mobile/global.css` — директивы Tailwind; импортируется один раз в `mobile/app/_layout.tsx`.
- `mobile/metro.config.js` / `mobile/babel.config.js` — обвязка NativeWind; менять без необходимости не нужно.
- Контейнер `node` работает в watch-режиме (`CI=0`) и стартует с флагом `--clear`, поэтому правки классов подхватываются сразу; полный сброс кэша — `make restart`.

### Линтеры и форматирование

- **ESLint** — flat config `mobile/eslint.config.js` на базе `eslint-config-expo`: `make lint` (проверка), `make lint-fix` (автоисправление).
- **Prettier** — `mobile/.prettierrc` с `prettier-plugin-tailwindcss` (сортирует классы в `className`): `make format` (запись), `make format-check` (проверка).
- Всё вместе с типами: `make check-mobile`.

### Web-платформа

Фронт один: тот же код клиента собирается для iOS/Android и для web через `react-native-web`
(`make expo-web` — dev, `make web-build` — production). Отдельного web-фронтенда нет; сервер
остаётся headless JSON API. Ветвление по `Platform.OS` в `src/api/client.ts` и `src/auth/session.ts` —
выбор канала аутентификации (Bearer-токен против HttpOnly-cookie), а не второй фронтенд.

В dev-режиме Expo открывается по корню (`http://localhost:8081/`), а собранная веб-версия —
по `/board/`: подпуть задан `experiments.baseUrl: "/board"` в `mobile/app.json`, поэтому все
маршруты expo-router и ассеты бандла строятся от `/board/`.

### Веб-версия и лендинг

| Адрес | Что отдаётся |
| --- | --- |
| `/` | лендинг — Blade-шаблон `backend/resources/views/landing.blade.php` (вне веб-корня) |
| `/privacy`, `/terms`, `/cookies` | юридические страницы — Blade-шаблоны `backend/resources/views/legal/*.blade.php`, стили `backend/public/css/legal.css` |
| `/board/` | веб-версия приложения — сборка Expo (`web.output: "single"`, SPA) |
| `/api/v1/*` | JSON API |

```bash
make web-build      # expo export --platform web -> mobile/dist -> backend/public/board
```

Что важно знать:

- Сборка кладётся в `backend/public/board/` и **в git не попадает** (`backend/.gitignore`).
- Лендинг — Blade-шаблон `backend/resources/views/landing.blade.php` вне веб-корня, поэтому `/`
  единственный адрес страницы (`/landing/` и `/landing/index.html` редиректят на `/`).
- Юридические страницы (`/privacy`, `/terms`, `/cookies`) — Blade-шаблоны
  `backend/resources/views/legal/*.blade.php`. Общий каркас — компонент `x-legal.layout`, реквизиты
  оператора — `x-legal.operators`, значения приходят из `config/legal.php` (переменные `LEGAL_*` в
  `backend/.env`, шаблон — `backend/.env.example`); пока они пусты, на страницах выводится
  пометка «не указан» — перед публикацией их нужно заполнить. Blade подключён провайдером
  `Illuminate\View\ViewServiceProvider` в `bootstrap/providers.php`.
- Маршрутизацию задаёт веб-сервер: локально — `docker/nginx/default.conf`, на виртуальном хостинге —
  `.htaccess` (нужен включённый `mod_rewrite`):
  - `backend/public/.htaccess` — `/board/**` отдаётся из сборки, остальное уходит в Laravel;
  - `.htaccess` самой сборки (`mobile/public/.htaccess`, копируется `expo export`) — SPA-фолбэк на `index.html`.
- Cookie сессии и CSRF работают, когда API и веб-версия на одном домене (так и есть: `/` и `/board/`
  на одном origin). Для локальной сборки в `backend/.env` добавлены `localhost:8080` и
  `127.0.0.1:8080` в `SANCTUM_STATEFUL_DOMAINS` и `CORS_ALLOWED_ORIGINS`.

## Конфигурация

Параметры compose — корневой `.env` (шаблон `.env.example`). Переменные Laravel — `backend/.env` (шаблон `backend/.env.example`, создаётся `make init-envs`).

Отладка Xdebug выключена по умолчанию. Чтобы включить:

```env
PHP_XDEBUG_MODE=debug
```

и перезапустить: `make restart`.

## Структура репозитория

```
.
├── AGENTS.md                     # правила разработки для людей и AI-агентов
├── Makefile                      # все команды проекта
├── docker-compose.yml            # базовый состав сервисов
├── docker-compose.override.yml   # dev-надстройки
├── docker/php, docker/nginx      # образы и конфиги
├── backend/                      # Laravel 13 (headless API + лендинг)
└── mobile/                       # Expo + React Native
```

## Статический анализ

PHPStan работает с Larastan 3 на уровне 6 (`backend/phpstan.neon.dist`):

- `make phpstan` — анализ;
- `make phpstan-baseline` — перегенерация `backend/phpstan-baseline.neon`;
- `make phpstan-clear` — сброс кэша результатов.
