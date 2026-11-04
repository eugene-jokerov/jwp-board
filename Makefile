# Единая точка входа для команд монорепозитория JWP Board.
# Все команды выполняются в docker-контейнерах: на хосте PHP 8.3 и Node 18,
# тогда как проект требует PHP 8.4 (Laravel 13) и Node 22+ (Expo SDK 58).
#
# Справка: make help

COMPOSE ?= docker compose

# UID/GID хоста передаются в образ, чтобы файлы backend/ принадлежали вам.
app_uid ?= $(shell id -u)
app_gid ?= $(shell id -g)
build_args = --build-arg APP_UID=$(app_uid) --build-arg APP_GID=$(app_gid)

php_service = php
node_service = node
mysql_service = mysql
mysql_test_service = mysql-test

# Произвольная команда: make php cmd="php artisan about"
cmd ?=
# Произвольная команда в контейнере node: make npm cmd="install"
cmd_node ?=

.DEFAULT_GOAL := help

.PHONY: help init-envs dirs build rebuild up start stop down restart ps logs \
        install install-backend install-mobile \
        sh php-shell artisan composer migrate migrate-fresh seed test test-filter \
        pint pint-test phpstan phpstan-baseline phpstan-clear check route-list \
        node-shell npm npx expo-start expo-web expo-install typecheck \
        lint lint-fix format format-check check-mobile \
        db-shell db-test-shell clean

help:
	@echo "JWP Board — команды разработки"
	@echo ""
	@echo "  make init-envs        скопировать .env.example -> .env (если ещё нет)"
	@echo "  make install          полная установка с нуля: сборка, старт, composer, migrate, npm"
	@echo "  make dirs             создать каталоги Laravel (bootstrap/cache, storage/*)"
	@echo "  make build            собрать docker-образы (php)"
	@echo "  make rebuild          пересобрать образы без кэша"
	@echo "  make up / down        поднять / остановить контейнеры"
	@echo "  make ps / logs        статус контейнеров / логи (follow)"
	@echo "  make restart          перезапустить контейнеры"
	@echo ""
	@echo "  make sh               bash в контейнере php"
	@echo "  make php cmd=\"...\"     произвольная команда в php"
	@echo "  make artisan cmd=\"...\" php artisan <cmd>"
	@echo "  make composer cmd=\"...\"  composer <cmd>"
	@echo "  make migrate          миграции основной БД"
	@echo "  make migrate-test     миграции тестовой БД"
	@echo "  make migrate-fresh    откатить и заново применить миграции + сиды (dev)"
	@echo "  make seed             сиды основной БД"
	@echo ""
	@echo "  make test             PHPUnit (sqlite :memory:, окружение testing)"
	@echo "  make test-filter filter=HealthTest  один тест"
	@echo "  make pint             автоформатирование (Laravel Pint)"
	@echo "  make pint-test        проверка стиля без изменений"
	@echo "  make phpstan          статический анализ (PHPStan + Larastan)"
	@echo "  make phpstan-baseline перегенерировать phpstan-baseline.neon"
	@echo "  make phpstan-clear    очистить кэш PHPStan"
	@echo "  make check            всё вместе: test + pint-test + phpstan"
	@echo "  make route-list       список маршрутов API"
	@echo ""
	@echo "  make node-shell       sh в контейнере node"
	@echo "  make npm cmd=\"...\"     npm <cmd> в контейнере node"
	@echo "  make expo-install     npm install в mobile"
	@echo "  make expo-start       expo dev server (запущен сервисом node)"
	@echo "  make typecheck        tsc --noEmit в mobile"
	@echo "  make lint             ESLint в mobile (без изменений)"
	@echo "  make lint-fix         ESLint в mobile с автоисправлением"
	@echo "  make format           Prettier: форматирование mobile"
	@echo "  make format-check     Prettier: проверка форматирования mobile"
	@echo "  make check-mobile     typecheck + lint + format-check"
	@echo ""
	@echo "  make db-shell         mysql-клиент основной БД"
	@echo "  make db-test-shell    mysql-клиент тестовой БД"

init-envs:
	@test -f .env || cp .env.example .env
	@test -f backend/.env || cp backend/.env.example backend/.env
	@echo "Файлы окружения готовы."

# Laravel не создаёт эти каталоги сам: без bootstrap/cache падает
# `artisan package:discover` в post-autoload-dump, без storage/* — логи и кэш.
dirs:
	@mkdir -p backend/bootstrap/cache \
	          backend/storage/app/public \
	          backend/storage/framework/cache/data \
	          backend/storage/framework/sessions \
	          backend/storage/framework/views \
	          backend/storage/logs
	@echo "Каталоги Laravel готовы (bootstrap/cache, storage/*)."

build: init-envs dirs
	$(COMPOSE) build $(build_args)

rebuild: init-envs
	$(COMPOSE) build --no-cache $(build_args)

up: init-envs
	$(COMPOSE) up -d --remove-orphans

start:
	$(COMPOSE) start

stop:
	$(COMPOSE) stop

down:
	$(COMPOSE) down --remove-orphans

restart:
	$(COMPOSE) restart

ps:
	$(COMPOSE) ps

logs:
	$(COMPOSE) logs -f --tail=100

install: build up
	$(COMPOSE) exec -T $(php_service) composer install --no-interaction
	$(COMPOSE) exec -T $(php_service) php artisan key:generate --ansi
	$(MAKE) migrate
	$(COMPOSE) run --rm $(node_service) npm install
	@echo ""
	@echo "Готово. API: http://localhost:8080/api/v1/health"

sh:
	$(COMPOSE) exec $(php_service) bash

php:
	$(COMPOSE) exec $(php_service) sh -lc '$(cmd)'

artisan:
	$(COMPOSE) exec $(php_service) php artisan $(cmd)

composer:
	$(COMPOSE) exec $(php_service) composer $(cmd)

migrate:
	$(COMPOSE) exec -T $(php_service) php artisan migrate --force

migrate-fresh:
	$(COMPOSE) exec -T $(php_service) php artisan migrate:fresh --seed --force

seed:
	$(COMPOSE) exec -T $(php_service) php artisan db:seed --force

test:
	$(COMPOSE) exec -T $(php_service) php artisan test $(test_args)

test-filter:
	$(COMPOSE) exec -T $(php_service) php artisan test --filter=$(filter)

pint:
	$(COMPOSE) exec -T $(php_service) ./vendor/bin/pint

pint-test:
	$(COMPOSE) exec -T $(php_service) ./vendor/bin/pint --test

phpstan:
	$(COMPOSE) exec -T $(php_service) ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress $(phpstan_args)

phpstan-baseline:
	$(COMPOSE) exec -T $(php_service) ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress --generate-baseline

phpstan-clear:
	$(COMPOSE) exec -T $(php_service) ./vendor/bin/phpstan clear-result-cache

check:
	$(COMPOSE) exec -T $(php_service) composer check

route-list:
	$(COMPOSE) exec -T $(php_service) php artisan route:list --except-vendor

node-shell:
	$(COMPOSE) exec $(node_service) sh

npm:
	$(COMPOSE) run --rm $(node_service) npm $(cmd)

npx:
	$(COMPOSE) run --rm $(node_service) npx $(cmd)

expo-install:
	$(COMPOSE) run --rm $(node_service) npm install

expo-start:
	$(COMPOSE) run --rm --service-ports $(node_service) npx expo start --lan

expo-web:
	$(COMPOSE) run --rm --service-ports $(node_service) npx expo start --web

typecheck:
	$(COMPOSE) run --rm $(node_service) npm run typecheck

lint:
	$(COMPOSE) run --rm $(node_service) npm run lint

lint-fix:
	$(COMPOSE) run --rm $(node_service) npm run lint:fix

format:
	$(COMPOSE) run --rm $(node_service) npm run format

format-check:
	$(COMPOSE) run --rm $(node_service) npm run format:check

# Полная проверка мобильного клиента — аналог make check для бэкенда.
check-mobile:
	$(COMPOSE) run --rm $(node_service) npm run typecheck
	$(COMPOSE) run --rm $(node_service) npm run lint
	$(COMPOSE) run --rm $(node_service) npm run format:check

db-shell:
	$(COMPOSE) exec $(mysql_service) sh -lc 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

db-test-shell:
	$(COMPOSE) exec $(mysql_test_service) sh -lc 'mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

clean:
	@echo "Удаляет контейнеры и данные БД (volume mysql_data). Файлы проекта не трогаются."
	$(COMPOSE) down -v --remove-orphans
