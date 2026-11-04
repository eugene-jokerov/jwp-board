<?php

namespace App\Shared\Application\Bus;

/**
 * Шина команд CQRS: единственная точка входа для изменяющих сценариев.
 *
 * Контроллеры и другие адаптеры не зависят от конкретного хендлера: они
 * собирают команду и отправляют её в шину. Реализация (LaravelCommandBus)
 * прогоняет команду через пайпы и передаёт хендлеру.
 */
interface CommandBus
{
    /**
     * Отправить команду в её хендлер и вернуть результат обработки.
     *
     * @param  object  $command  команда прикладного слоя
     * @return mixed результат хендлера (обычно объект из Application/Results)
     */
    public function dispatch(object $command): mixed;
}
