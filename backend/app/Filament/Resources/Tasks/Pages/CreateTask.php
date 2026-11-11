<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Modules\Task\Application\Commands\CreateTask\CreateTaskCommand;
use App\Modules\Task\Application\Commands\CreateTaskForAdmin\CreateTaskForAdminCommand;
use App\Modules\Task\Domain\Exceptions\InvalidHierarchy;
use App\Modules\Task\Domain\Exceptions\TaskNotFound;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskDeadline;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskSlug;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use App\Shared\Application\Bus\CommandBus;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Создание карточки в админке.
 *
 * Карточка создаётся командой CreateTaskForAdmin: тот же сценарий, что в API,
 * поэтому иерархия (TaskPlacement), журнал истории и транзакция работают
 * одинаково. Нарушение иерархии блокирует сохранение и объясняется в форме,
 * а не превращается в страницу ошибки.
 */
class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $ownerId = (int) $data['user_id'];

        $command = new CreateTaskForAdminCommand($ownerId, new CreateTaskCommand(
            type: CardType::from((string) $data['type']),
            column: CardColumn::from((string) $data['column']),
            title: TaskTitle::fromString((string) $data['title']),
            description: TaskDescription::fromNullableString($this->optional($data, 'description')),
            directionId: $this->optionalInt($data, 'direction_id'),
            initiativeId: $this->optionalInt($data, 'initiative_id'),
            projectId: $this->optionalInt($data, 'project_id'),
            deadline: TaskDeadline::fromNullableString($this->optional($data, 'deadline')),
            slug: TaskSlug::fromNullableString($this->optional($data, 'slug')),
            idea: (bool) ($data['idea'] ?? false),
            priority: CardPriority::from((string) ($data['priority'] ?? CardPriority::default()->value)),
        ));

        try {
            $result = app(CommandBus::class)->dispatch($command);
        } catch (InvalidHierarchy|TaskNotFound $exception) {
            Notification::make()
                ->danger()
                ->title('Карточка не создана')
                ->body($exception->getMessage())
                ->send();

            $this->halt();
        }

        return TaskResource::getModel()::query()->findOrFail((int) $result->id);
    }

    /**
     * Строковое значение необязательного поля или null, если поля нет/оно пустое.
     *
     * @param  array<string, mixed>  $data
     */
    private function optional(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Идентификатор необязательной ссылки: пустая строка формы — это null.
     *
     * @param  array<string, mixed>  $data
     */
    private function optionalInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
