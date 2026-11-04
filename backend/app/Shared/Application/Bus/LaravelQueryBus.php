<?php

namespace App\Shared\Application\Bus;

use Illuminate\Pipeline\Pipeline;
use RuntimeException;

/**
 * Реализация QueryBus поверх встроенной шины Laravel.
 *
 * Отличие от LaravelCommandBus: пайпы пустые (запросы не меняют состояние
 * и не нуждаются в транзакции), а карта «запрос → хендлер» хранится прямо
 * в шине: Bus::map() в Laravel описывает только команды.
 */
final readonly class LaravelQueryBus implements QueryBus
{
    /**
     * @param  Pipeline  $pipeline  конвейер пайпов (сейчас пустой, оставлен для развития)
     * @param  array<class-string, class-string>  $handlers  карта «запрос → хендлер»
     * @param  array<int, class-string>  $pipes  пайпы, применяемые ко всем запросам
     */
    public function __construct(
        private Pipeline $pipeline,
        private array $handlers,
        private array $pipes = [],
    ) {}

    /**
     * Выполнить запрос через пайпы в его хендлер.
     *
     * @throws RuntimeException если для запроса не зарегистрирован хендлер
     */
    public function ask(object $query): mixed
    {
        return $this->pipeline
            ->send($query)
            ->through($this->pipes)
            ->then(function (object $query): mixed {
                return $this->invokeHandler($query);
            });
    }

    /**
     * Найти и вызвать хендлер запроса.
     */
    private function invokeHandler(object $query): mixed
    {
        $handlerClass = $this->handlers[$query::class] ?? null;

        if ($handlerClass === null) {
            throw new RuntimeException(sprintf('Для запроса %s не зарегистрирован хендлер.', $query::class));
        }

        $handler = app($handlerClass);

        if (! is_object($handler)) {
            throw new RuntimeException(sprintf('Хендлер %s не является объектом.', $handlerClass));
        }

        $method = method_exists($handler, 'handle') ? 'handle' : '__invoke';

        return $handler->{$method}($query);
    }
}
