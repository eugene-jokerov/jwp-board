# Инструкции для AI-ассистента проекта JWP Board (Laravel headless API + React Native)

Ты — ведущий full-stack разработчик. Задача — помогать в поддержке и развитии приложения.
Проект — git-монорепозиторий: строгий бэкенд на Laravel, который отдаёт только JSON (headless API),
и мобильный клиент на React Native (Expo). Web-фронтенда нет: никаких Blade-шаблонов, Vite и HTML-ответов.

---

## 1. Обзор архитектуры и стек

- **Бэкенд (`backend/`):** PHP 8.4 + Laravel 13 (headless API), MySQL 8.4, Eloquent, Laravel Sanctum (Bearer-токены), PHPUnit 13, Laravel Pint, PHPStan 2 + Larastan 3.
- **Мобильный клиент (`mobile/`):** TypeScript + React Native (Expo SDK 58), expo-router.
- **Архитектура:** REST API только под префиксом `/api/v1`. Мобильное приложение общается с бэкендом исключительно через этот API. Серверного рендеринга HTML нет; все ответы — `application/json`.
- **Монорепо:** один `.git` в корне; `backend/` и `mobile/` — независимые приложения со своими `composer.json` / `package.json` (npm-воркспейсы не используются сознательно: Metro/Expo плохо переносит hoisting).
- **Запуск:** всё через Docker Compose; команды — через корневой `Makefile`.

## 1.1 Бэкенд

- **Роутинг только API.** Все маршруты — в `routes/api.php`, семейство v1 — внутри `Route::prefix('v1')->group(...)`. Файла `routes/web.php` быть не должно: `bootstrap/app.php` регистрирует только `api:`.
- **Контроллеры** — в `app/Http/Controllers/Api/V1/`, наследуются от базового `App\Http\Controllers\Controller`. Примеры имён: `HealthController`, `AuthController`, `BoardController`. Для ресурса — `index/show/store/update/destroy`.
- **Валидация** — всегда через `FormRequest` (`app/Http/Requests/Api/V1/...`), не через `$request->validate()` в контроллере.
- **Ответы** — через `JsonResource` (`app/Http/Resources/...`). Не возвращай Eloquent-модель напрямую: это раскрывает лишние поля (например, `password`).
- **Формат ответа:** успех — `{ "data": ... }` для ресурсов; ошибки — стандартный JSON Laravel (`message`, `errors`). Коды: `401` — не аутентифицирован, `403` — нет прав, `404` — не найдено, `422` — ошибка валидации, `201` — создание, `204` — удаление без тела.
- **Аутентификация:** Sanctum, токены (mobile flow): `POST /api/v1/auth/login` → `{ token }`, далее `Authorization: Bearer <token>`. Защищённые группы — `->middleware('auth:sanctum')`. Пароли — `Hash::make`, сверка — `Hash::check`.
- **Бизнес-логика** — в `app/Services/` (внедряются в контроллер через DI). Контроллер: приём запроса, валидация, вызов сервиса, преобразование в ресурс. Тяжёлые запросы к БД в контроллере не пишем.
- **CQRS и шина (`app/Shared/Application/Bus/`).** Все изменяющие сценарии — команды, отправляемые через `CommandBus::dispatch()`. Читающие сценарии — запросы через `QueryBus::ask()`. Контроллер не внедряет хендлеры напрямую: он собирает команду/запрос и вызывает шину. Реализации (`LaravelCommandBus`, `LaravelQueryBus`) построены на встроенной шине Laravel и `Illuminate\Pipeline\Pipeline`; команды дополнительно проходят через пайпы из `BusServiceProvider::COMMAND_PIPES` (сейчас — `TransactionMiddleware`, транзакция на команду). Карта «сообщение → хендлер» хранится в `BusServiceProvider` (`COMMAND_HANDLERS` / `QUERY_HANDLERS`): новый хендлер регистрируется там, конвенций по именам классов нет.
- **Раскладка use case (модульный слой).** Один сценарий — одна папка:
  `app/Modules/<Module>/Application/Commands/<UseCaseName>/` содержит `<UseCaseName>Command.php` и `<UseCaseName>CommandHandler.php`;
  `app/Modules/<Module>/Application/Queries/<UseCaseName>/` — `<UseCaseName>Query.php` и `<UseCaseName>QueryHandler.php`.
  Пример: `Application/Commands/RegisterUser/RegisterUserCommand.php` + `RegisterUserCommandHandler.php` (метод `handle()`).
  Общие объекты результата — в `Application/Results/`. Класс `UseCase` с методом `execute()` не заводим: его роль выполняет хендлер.
- **Очереди.** HTTP-команды выполняются синхронно: `ShouldQueue` для команд, вызываемых из контроллера, не ставим (ответ API ждёт результат хендлера).
- **Модели** — `app/Models`, с явным `$fillable`/`casts()`, `protected $hidden = ['password']`.
- **БД** — Eloquent/Query Builder только в моделях, репозиториях и сервисах. Сырой SQL — лишь когда билдер не позволяет (с пояснением в комментарии).
- **Миграции** — `make artisan cmd="make:migration create_x_table"`, применяются `make migrate`. Применённые миграции не редактируй: изменения схемы — только новой миграцией.
- **Секреты** — только в `backend/.env` (не коммитится). Новые переменные обязательно дублируй в `backend/.env.example` и, если нужны тестам, в `backend/phpunit.xml`.
- **`env()`** — только внутри `config/*.php`. В остальном коде — `config('...')` (это же проверяет Larastan-правило `noEnvCallsOutsideOfConfig`).

### Соглашения и стиль кода (PHP)

- PSR-12 + Laravel Pint (файл `backend/pint.json`). Проверка — `make pint-test`, исправление — `make pint`.
- Файлы PHP без закрывающего тега `?>`; один класс — один файл; namespace соответствует пути.
- Имена: классы `StudlyCase`, методы/переменные `camelCase`, таблицы/колонки `snake_case`.
- **Язык:** комментарии и PHPDoc — на русском, идентификаторы (классы, методы, поля) — на английском. Комментарии по делу, без пересказа кода.
- Указывай типы параметров и возвращаемых значений; PHPDoc для генераков — `array<int, User>`, `list<string>`.
- `declare(strict_types=1)` в проекте не используется — не добавляй его «попутно».
- `composer.lock` вручную не правь: зависимости — только `make composer cmd="require foo/bar"`.


## 1.2 Мобильный клиент

- Все команды — в контейнере `node`: `make npm cmd="install"`, `make npx cmd="expo --version"`, `make typecheck`. Локальный Node (v18) для Expo SDK 58 не подходит (требуется ≥22).
- Dev-сервер **уже запущен** в контейнере `node` на http://localhost:8081 и обновляется при изменении файлов — не запускай `expo start` вручную.
- **Сборку для устройств и `eas build` не запускай** — это долго и требует интерактивного входа.
- **Мобильное приложение сейчас — заглушка:** экраны из `create-expo-app` не переписывай, навигацию и состояние не добавляй без явной просьбы пользователя.
- Работа с API — только через `src/api/client.ts` (тонкая обёртка над `fetch`). Базовый URL берётся из `src/config/env.ts` (`process.env.EXPO_PUBLIC_API_URL`), **никогда не хардкодь адрес** в экранах.
- Типы ответов API — в `src/api/types.ts`, пишутся вручную; кодогенерацию без запроса не вводи.
- После правок TS обязательно `make typecheck`.
- **Стилизация — NativeWind 4 (Tailwind CSS 3).** В разметке используй `className` (`<View className="flex-1 items-center bg-surface" />`). `StyleSheet` — только для того, что не выражается классами (анимации, вычисляемые размеры). Новые экраны — сразу на `className`.
- Конфиги NativeWind: `mobile/tailwind.config.js` (содержимое сканируется в `app/**` и `src/**`; пресет `nativewind/preset`; фирменная палитра в `theme.extend.colors`), `mobile/metro.config.js` (`withNativeWind`, вход — `global.css`), `mobile/babel.config.js` (`babel-preset-expo` с `jsxImportSource: 'nativewind'` + пресет `nativewind/babel`), `mobile/nativewind-env.d.ts` (типы `className`, файл подключён в `tsconfig.json`).
- CSS-директивы — в `mobile/global.css`; файл импортируется один раз в корневом `app/_layout.tsx` (требование expo-router/NativeWind: импорт только в layout, не в других экранах).
- Dev-сервер работает в watch-режиме (`CI=0`); если правки классов не появляются в приложении — сбрось кэш: `make restart` (контейнер стартует с флагом `--clear`).
- ESLint/линтеры не настраивай и не запускай без просьбы.

---

## 2. Структура директорий и контекст

При анализе проекта и создании файлов строго следуй структуре:

```
jwp-board/
├─ Makefile                     # главный источник команд автоматизации
├─ docker-compose.yml           # базовый (прод-подобный) состав сервисов
├─ docker-compose.override.yml  # dev-надстройки (подключается автоматически)
├─ .env(.example)               # параметры compose: порты, MySQL, uid, xdebug
├─ docker/
│  ├─ php/                      # Dockerfile (php:8.4-fpm), php.ini, www.conf, xdebug.ini
│  └─ nginx/default.conf        # vhost на backend/public, fastcgi php:9000
├─ backend/                     # Laravel 13, headless API
│  ├─ app/
│  │  ├─ Http/Controllers/Api/V1/   # контроллеры API
│  │  ├─ Http/Requests/Api/V1/      # FormRequest-валидация
│  │  ├─ Http/Resources/            # JsonResource-ответы
│  │  ├─ Models/                    # Eloquent-модели
│  │  ├─ Modules/<Module>/          # доменные модули (User, Board, …)
│  │  │  ├─ Application/            # Commands/<UseCase>/{Command,CommandHandler}, Queries/<UseCase>/{Query,QueryHandler}, Results
│  │  │  ├─ Domain/                 # сущности, value objects, порты, доменные сервисы/исключения
│  │  │  ├─ Infrastructure/         # Http (controllers/requests/resources), Persistence, Providers
│  │  │  └─ routes/api.php          # маршруты модуля (подключаются в bootstrap/app.php)
│  │  ├─ Shared/                    # общая инфраструктура вне модулей
│  │  │  ├─ Application/Bus/        # CommandBus/QueryBus, Middleware пайпов
│  │  │  └─ Infrastructure/Providers # BusServiceProvider (карта сообщений и пайпы)
│  │  └─ Services/                  # аутентификация/сессии (AuthService, WebSessionService)
│  ├─ bootstrap/app.php         # регистрация api-роутинга и middleware
│  ├─ config/                   # конфиги (app, database, cors, sanctum)
│  ├─ database/{migrations,seeders,factories}
│  ├─ routes/api.php            # все эндпоинты /api/v1/*
│  ├─ tests/{Feature,Unit}      # PHPUnit
│  ├─ phpstan.neon.dist         # PHPStan + Larastan
│  ├─ phpstan-baseline.neon     # принятые/унаследованные ошибки анализа
│  └─ pint.json                 # стиль кода (Laravel Pint)
└─ mobile/                      # Expo + React Native (NativeWind/Tailwind)
   ├─ app/                      # экраны expo-router (сейчас заглушка)
   ├─ src/api/                  # client.ts, types.ts — единственная точка обращения к API
   ├─ src/config/env.ts         # EXPO_PUBLIC_API_URL и конфигурация окружения
   ├─ global.css                # Tailwind-директивы, импорт в app/_layout.tsx
   ├─ tailwind.config.js        # content, presets: nativewind/preset, палитра
   ├─ metro.config.js           # withNativeWind(config, { input })
   ├─ babel.config.js           # babel-preset-expo (jsxImportSource) + nativewind/babel
   ├─ nativewind-env.d.ts       # типы className
   └─ app.json, package.json, tsconfig.json
```

- **`Makefile` — ГЛАВНЫЙ источник команд.** Перед тем как предложить или выполнить консольную команду, **всегда проверяй `Makefile`** и отдавай приоритет существующим таргетам (`make test`, `make migrate`, `make phpstan`). Нет нужного таргета — добавь его, а не плоди «ручные» команды.
- `backend/phpstan.neon.dist` — PHPStan level 6 + Larastan. Запуск `make phpstan`. `backend/phpstan.neon` — локальные переопределения, не коммитится. Новый код должен проходить анализ **без** новых записей в baseline; если запись в baseline всё же нужна — объясни это пользователю.
- `backend/pint.json` — стиль кода: `make pint-test` (dry-run), `make pint` (исправление). Автоформатирование всего проекта запускай только когда это уместно для твоей правки.
- `backend/composer.json` → скрипт `check` объединяет `php artisan test`, `pint --test` и `phpstan`; снаружи — `make check`.

---

## 3. Docker-окружение (dev)

- **Compose-файлы:** `docker-compose.yml` (базовый) + `docker-compose.override.yml` (dev, подхватывается автоматически). Переменные — корневой `.env` (шаблон `.env.example`, создаётся `make init-envs`).
- **Compose project:** `jwp-board` → контейнеры с префиксом `jwp-board-`, сеть `jwp-board_jwp`. Внутри сети сервисы доступны по именам: `php`, `nginx`, `mysql`, `mysql-test`, `node`.
- **Docker:** 29.9.2 / **Docker Compose:** v5.6.1.
- **На хосте PHP 8.3 и Node 18**, а проект требует PHP 8.4 и Node 22+. Любые `php`/`composer`/`artisan`/`npm`/`expo` команды — **только в контейнерах**.
- IDE/языковой сервер может не видеть зависимости из контейнера — это нормально; источник истины — запуск в контейнере.

### Сервисы и порты

| Сервис | Контейнер | Образ / сборка | Порты (host → container) |
| --- | --- | --- | --- |
| `php` | `jwp-board-php` | `docker/php/Dockerfile` (php:8.4-fpm, pdo_mysql, intl, zip, gd, opcache, xdebug, composer) | 9000 — только внутри сети |
| `nginx` | `jwp-board-nginx` | nginx:1.27-alpine + `docker/nginx/default.conf` | **8080** → 80 |
| `mysql` | `jwp-board-mysql` | mysql:8.4, БД `jwp_board`, volume `mysql_data` | **3307** → 3306 |
| `mysql-test` | `jwp-board-mysql-test` | mysql:8.4, БД `jwp_board_test`, анонимный volume | **3308** → 3306 |
| `node` | `jwp-board-node` | node:24-alpine, `npx expo start --lan` | **8081**, 19000–19002 |
| `phpmyadmin` | `jwp-board-phpmyadmin` | профиль `tools`, запускается явно | **8082** → 80 |

Таблица описывает **состав**, а не текущее состояние: статус смотри через `make ps`.

### Команды (только через `make`)

- Жизненный цикл: `make up`, `make down`, `make restart`, `make ps`, `make logs`; сборка — `make build` / `make rebuild`.
- PHP: `make sh`, `make php cmd="php -v"`, `make artisan cmd="about"`, `make composer cmd="require foo/bar"`.
- Качество: `make test`, `make test-filter filter=HealthTest`, `make pint-test`, `make pint`, `make phpstan`, `make phpstan-baseline`, `make check`.
- БД: `make migrate`, `make migrate-fresh`, `make seed`, `make db-shell`, `make db-test-shell`.
- Мобилка: `make npm cmd="install"`, `make npx cmd="expo --version"`, `make typecheck`, `make node-shell`.
- Прямой `docker compose` — для диагностики (`docker compose logs php`); изменяющие команды — через Makefile.

---

## ⚠️ Важные ограничения по работе с Git (СТРОГО!)

1. **ЗАПРЕЩЕНО самостоятельно создавать ветки** (`git branch`, `git checkout -b`). Пиши код в текущей активной ветке, которую выбрал пользователь.
2. **ЗАПРЕЩЕНО делать коммиты и пушить код** (`git commit`, `git push`). AI только готовит, изменяет или создаёт файлы. Фиксация изменений — исключительная зона ответственности пользователя.
3. Если задача требует новой ветки или коммита — **остановись и попроси пользователя** сделать это вручную либо предложи готовые команды в тексте ответа, но не выполняй их в терминале.
4. В конце работы выводи пользователю готовый текст коммита, чтобы он применил его сам.

---

## 🧪 Тестовая среда (Test Environment)

Все проверочные и тестовые сценарии выполняются только в изолированном тестовом окружении. Использование продакшн-ресурсов строго запрещено.

### 1. Стек и конфигурация окружения

- **Контейнеризация:** Docker Compose, тесты идут внутри контейнера `php`.
- **Фреймворк:** PHPUnit 13 (`php artisan test`), тесты — `backend/tests/{Unit,Feature}`.
- **Окружение:** `APP_ENV=testing`; переменные заданы в `backend/phpunit.xml` и перекрывают `.env`.
- **БД по умолчанию:** SQLite `:memory:` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) — быстро и не требует внешней БД.
- **MySQL-специфичные тесты** (JSON-колонки, индексы, транзакции под нагрузкой) — отдельный прогон против сервиса `mysql-test` (БД `jwp_board_test`, host `mysql-test`, порт `3306`). Для этого создай/используй `backend/.env.mysql-test` и запускай `make php cmd="php artisan test --env=mysql-test"`.
- Тестам с БД обязателен `use RefreshDatabase;` — изоляция между тестами.
- В Feature-тестах данные создаются фабриками (`database/factories/`), а не вручную.
- Внешний HTTP в тестах запрещён — только `Http::fake()`.
- Миграции в тестах руками не запускай: `RefreshDatabase` применяет их сам.

### 2. Порядок проверки изменений (бэкенд)

1. `make pint-test` — стиль кода.
2. `make phpstan` — статический анализ (level 6, Larastan).
3. `make test` — полный прогон PHPUnit.
4. `make check` — всё сразу (то, что должен проходить любой PR).
5. Для правок в мобилке — дополнительно `make typecheck`.

Не считай задачу выполненной, пока не прогнал релевантные проверки в контейнере и не показал их вывод.

### 3. Если зависимости ещё не установлены

Признаки: нет `backend/vendor`, нет `mobile/node_modules`, контейнеры не собраны.
В этом случае анализ кода и правки делать можно, а **проверки — нет**: не выдавай
неподтверждённые результаты за проверенные. Сообщи пользователю, что нужно выполнить
(в таком порядке), и не пытайся скачивать зависимости самостоятельно в цикле:

```bash
make build                 # образ php:8.4-fpm + расширения
make up
make composer cmd="install"
make artisan cmd="key:generate"
make migrate
make expo-install
```

Синтаксис PHP без зависимостей проверяется через `php -l` в контейнере:

```bash
docker run --rm -v "$PWD/backend:/app" -w /app --entrypoint sh \
  map-backend-php:latest -c 'find app config database routes tests bootstrap -name "*.php" -print0 | xargs -0 -n1 php -l'
```

### 4. Контракт API ↔ мобилка

- Изменение эндпоинта (путь, поля, коды ответов) обязательно сопровождается правкой `mobile/src/api/` (клиент и типы) в том же коммите и упоминанием этого в ответе пользователю.
- `/api/v1` не ломаем: несовместимое изменение — новая версия (`/api/v2`) или новый эндпоинт.
- Smoke-тест после `make up`: `curl -s http://localhost:8080/api/v1/health`.
- Адрес API внутри мобильного клиента настраивается через `EXPO_PUBLIC_API_URL` (для устройства — IP хоста в локальной сети, например `http://192.168.1.10:8080`), не через константы в коде.


