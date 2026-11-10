<?php

namespace App\Modules\Task\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Task\Application\Commands\UpdateTask\UpdateTaskCommand;
use App\Modules\Task\Application\Results\TaskResult;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\TaskDeadline;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskSlug;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use App\Modules\Task\Infrastructure\Http\Requests\UpdateTaskRequest;
use App\Modules\Task\Infrastructure\Http\Resources\TaskResource;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\CommandBus;

/**
 * Изменение карточки, включая смену колонки.
 *
 * Перевод строковых значений запроса в value object'ы и перечисления —
 * обязанность входного адаптера: прикладной слой получает готовые типы.
 */
final class UpdateTaskController extends Controller
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * PATCH /api/v1/tasks/{task}
     */
    public function __invoke(UpdateTaskRequest $request, int $task): TaskResource
    {
        /** @var User $user */
        $user = $request->user();

        $fields = $request->toFieldPayload();
        $changes = [];

        if (array_key_exists('title', $fields)) {
            $changes['title'] = TaskTitle::fromString($fields['title'])->value();
        }

        if (array_key_exists('slug', $fields)) {
            $changes['slug'] = TaskSlug::fromNullableString($fields['slug'])->value();
        }

        if (array_key_exists('description', $fields)) {
            $changes['description'] = TaskDescription::fromNullableString($fields['description'])->value();
        }

        // Пустая строка дедлайна — «срок убрали»: value object отдаёт null.
        if (array_key_exists('deadline', $fields)) {
            $changes['deadline'] = TaskDeadline::fromNullableString($fields['deadline'])->value();
        }

        if (array_key_exists('column', $fields)) {
            $changes['column'] = CardColumn::from($fields['column']);
        }

        // Степень проработки меняется отдельным полем; правила «только беклог»
        // нет — флаг живёт в данных при любой колонке, интерфейс лишь не
        // показывает выбор вне беклога.
        if (array_key_exists('idea', $fields)) {
            $changes['idea'] = $fields['idea'] === true;
        }

        /** @var TaskResult $result */
        $result = $this->bus->dispatch(new UpdateTaskCommand(
            (int) $user->getKey(),
            $task,
            $request->toLinkPayload(),
            $changes,
        ));

        return new TaskResource($result);
    }
}
