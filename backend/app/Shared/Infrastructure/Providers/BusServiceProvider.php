<?php

namespace App\Shared\Infrastructure\Providers;

use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Bus\DependencyInjection\BusRegistry;
use App\Shared\Application\Bus\LaravelCommandBus;
use App\Shared\Application\Bus\LaravelQueryBus;
use App\Shared\Application\Bus\Middleware\DenyReadOnlyCommands;
use App\Shared\Application\Bus\Middleware\TransactionMiddleware;
use App\Shared\Application\Bus\QueryBus;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\ServiceProvider;

/**
 * Шина приложения: привязки CommandBus/QueryBus и карта «сообщение → хендлер».
 *
 * Карту сценариев объявляют сами модули через порт BusRegistry: провайдер
 * берёт список модулей из config('bus.registries') и собирает из них общую
 * карту. Общая инфраструктура не импортирует классы модулей, а модулю не
 * нужно знать о шине — он лишь отвечает, какие сценарии обслуживает.
 */
final class BusServiceProvider extends ServiceProvider
{
    /**
     * Пайпы команд из API: транзакция и запрет записи в режиме «только чтение».
     *
     * Порядок важен: транзакция идёт первой, чтобы проверка права на запись
     * выполнялась внутри неё и отказ откатывал запрос атомарно.
     *
     * Этот же набор получают все команды, которые модуль не пометил как
     * «plain»: безопасное поведение по умолчанию — транзакция плюс проверка прав.
     *
     * @var array<int, class-string>
     */
    private const array WRITE_PIPES = [
        TransactionMiddleware::class,
        DenyReadOnlyCommands::class,
    ];

    /**
     * Пайпы без проверки права на запись: только транзакция.
     *
     * @var array<int, class-string>
     */
    private const array PLAIN_PIPES = [
        TransactionMiddleware::class,
    ];

    /**
     * Регистрация шин в контейнере.
     */
    public function register(): void
    {
        $this->app->bind(CommandBus::class, fn ($app): LaravelCommandBus => new LaravelCommandBus(
            // Конвейер создаётся на каждый dispatch: Pipeline хранит состояние вызова.
            $app->make(Pipeline::class),
            $app->make(DispatcherContract::class),
            $this->pipes(),
            self::WRITE_PIPES,
        ));

        // Запросы только читают данные: транзакционного пайпа у них нет.
        $this->app->bind(QueryBus::class, fn ($app): LaravelQueryBus => new LaravelQueryBus(
            $app->make(Pipeline::class),
            $this->queryHandlers(),
        ));
    }

    /**
     * Регистрация карты команд: Laravel ищет хендлер по ней, без конвенций имён.
     */
    public function boot(): void
    {
        Bus::map($this->commandHandlers());
    }

    /**
     * Пайпы команд: команда → набор пайпов.
     *
     * Исключения из правила «транзакция плюс запрет записи» объявляет модуль:
     *  - команды админки (/admin) — только транзакция: администратор правит
     *    доски read-only пользователей из панели, и флаг их учётки не помеха;
     *  - регистрация и вход — тоже только транзакция: новой учётной записи
     *    ещё нет, а гость под флагом предыдущей сессии не должен блокировать
     *    регистрацию и вход.
     *
     * @return array<class-string, array<int, class-string>>
     */
    private function pipes(): array
    {
        $pipes = [];
        foreach ($this->registries() as $registry) {
            foreach ($registry->plainCommands() as $command) {
                $pipes[$command] = self::PLAIN_PIPES;
            }
        }

        return $pipes;
    }

    /**
     * Общая карта команд всех модулей.
     *
     * @return array<class-string, class-string>
     */
    private function commandHandlers(): array
    {
        $handlers = [];
        foreach ($this->registries() as $registry) {
            $handlers += $registry->commands();
        }

        return $handlers;
    }

    /**
     * Общая карта запросов всех модулей.
     *
     * @return array<class-string, class-string>
     */
    private function queryHandlers(): array
    {
        $handlers = [];
        foreach ($this->registries() as $registry) {
            $handlers += $registry->queries();
        }

        return $handlers;
    }

    /**
     * Реестры шины модулей из config('bus.registries').
     *
     * @return list<BusRegistry>
     */
    private function registries(): array
    {
        $registries = [];
        /** @var list<class-string<BusRegistry>> $classes */
        $classes = config('bus.registries', []);
        foreach ($classes as $class) {
            $registry = $this->app->make($class);
            if ($registry instanceof BusRegistry) {
                $registries[] = $registry;
            }
        }

        return $registries;
    }
}
