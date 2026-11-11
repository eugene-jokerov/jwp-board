<?php

namespace App\Modules\Task\Application\Commands\DeleteTaskForAdmin;

/**
 * Команда удаления карточки из админки.
 *
 * `cascade` — режим модерации: удалить карточку вместе со всем поддеревом
 * (инициатива → проекты → задачи). Без каскада действует обычное правило
 * «карточку с детьми не удаляем». В API каскад не используется: там
 * DELETE /tasks/{task} по-прежнему отдаёт 409.
 */
final readonly class DeleteTaskForAdminCommand
{
    public function __construct(
        public int $ownerId,
        public int $taskId,
        public bool $cascade = false,
    ) {}
}
