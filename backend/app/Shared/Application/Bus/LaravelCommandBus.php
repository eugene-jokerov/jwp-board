<?php

namespace App\Shared\Application\Bus;

use Closure;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Pipeline\Pipeline;
use RuntimeException;

/**
 * Реализация CommandBus поверх встроенной шины Laravel.
 *
 * Команда проходит через пайпы (валидация, транзакция и т.п.) и передаётся
 * хендлеру. Соответствие «команда → хендлер» задаётся через Bus::map()
 * в BusServiceProvider, поэтому конвенций по именам классов нет.
 *
 * Набор пайпов тоже живёт в BusServiceProvider — картой «команда → пайпы»:
 * у команд API и команд админки разные правила (см. COMMAND_PIPES).
 */
final readonly class LaravelCommandBus implements CommandBus
{
    /**
     * @param  Pipeline  $pipeline  конвейер пайпов (Illuminate\Pipeline\Pipeline)
     * @param  Dispatcher  $dispatcher  шина Laravel: хранит маппинг команд на хендлеры
     * @param  array<class-string, array<int, class-string>>  $pipes  наборы пайпов: команда → пайпы
     * @param  array<int, class-string>  $defaultPipes  пайпы для команд, которых нет в карте
     */
    public function __construct(
        private Pipeline $pipeline,
        private Dispatcher $dispatcher,
        private array $pipes,
        private array $defaultPipes,
    ) {}

    /**
     * Отправить команду через пайпы в её хендлер.
     *
     * @throws RuntimeException если для команды не зарегистрирован хендлер
     */
    public function dispatch(object $command): mixed
    {
        return $this->pipeline
            ->send($command)
            ->through($this->pipes[$command::class] ?? $this->defaultPipes)
            ->then(function (object $command): mixed {
                return $this->invokeHandler($command);
            });
    }

    /**
     * Вызвать хендлер, закреплённый за командой.
     */
    private function invokeHandler(object $command): mixed
    {
        // Laravel возвращает false, если для команды нет записи в карте хендлеров.
        if (! $this->dispatcher->hasCommandHandler($command)) {
            throw new RuntimeException(sprintf('Для команды %s не зарегистрирован хендлер.', $command::class));
        }

        $handler = $this->dispatcher->getCommandHandler($command);

        if ($handler === false || $handler === null) {
            throw new RuntimeException(sprintf('Для команды %s не зарегистрирован хендлер.', $command::class));
        }

        // Laravel разрешает и замыкание, и объект с методом handle()/__invoke().
        if ($handler instanceof Closure) {
            return $handler($command);
        }

        $method = method_exists($handler, 'handle') ? 'handle' : '__invoke';

        return $handler->{$method}($command);
    }
}
