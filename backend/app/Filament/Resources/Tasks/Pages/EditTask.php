<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use App\Modules\Task\Application\Commands\DeleteTaskForAdmin\DeleteTaskForAdminCommand;
use App\Modules\Task\Application\Commands\UpdateTaskForAdmin\UpdateTaskForAdminCommand;
use App\Modules\Task\Domain\Exceptions\ColumnChangeBlocked;
use App\Modules\Task\Domain\Exceptions\InvalidHierarchy;
use App\Modules\Task\Domain\Exceptions\TaskHasChildren;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use App\Shared\Application\Bus\CommandBus;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Редактирование карточки в админке.
 *
 * Сохранение идёт командой UpdateTaskForAdmin: доменные правила (иерархия,
 * запрет смены колонки при детях в работе) не обходятся админкой. Удаление —
 * через DeleteTaskForAdmin с каскадом: чистка поддерева нужна модерации.
 *
 * Тип и владелец в форме только для чтения: они задают уровень карточки и её
 * доску, изменить их задним числом нельзя (как и через API).
 */
class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->using(function (Task $record): bool {
                    try {
                        $deleted = app(CommandBus::class)->dispatch(
                            new DeleteTaskForAdminCommand((int) $record->user_id, (int) $record->id, cascade: true),
                        );
                    } catch (TaskHasChildren $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Карточка не удалена')
                            ->body($exception->getMessage())
                            ->send();

                        return false;
                    }

                    Notification::make()
                        ->success()
                        ->title('Удалено карточек: '.$deleted)
                        ->send();

                    return true;
                }),
        ];
    }

    /**
     * Сохранение через команду с проверкой доменных правил.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Task $record */
        $changes = ['title' => TaskTitle::fromString((string) $data['title'])];

        foreach (['description', 'slug', 'deadline'] as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = is_scalar($data[$field]) ? (string) $data[$field] : null;
            }
        }

        if (isset($data['column'])) {
            $changes['column'] = CardColumn::from((string) $data['column']);
        }

        if (isset($data['priority'])) {
            $changes['priority'] = CardPriority::from((string) $data['priority']);
        }

        if (array_key_exists('idea', $data)) {
            $changes['idea'] = (bool) $data['idea'];
        }

        // Ссылки передаются только теми ключами, что показаны в форме: у
        // инициативы это направление, у проекта — направление и инициатива,
        // у задачи — проект. Остальные ключи команда трактует как «не менять».
        $links = [];

        foreach (['direction_id', 'initiative_id', 'project_id'] as $link) {
            if (array_key_exists($link, $data)) {
                $links[$link] = is_numeric($data[$link]) ? (int) $data[$link] : null;
            }
        }

        try {
            app(CommandBus::class)->dispatch(
                new UpdateTaskForAdminCommand((int) $record->user_id, (int) $record->id, $links, $changes),
            );
        } catch (InvalidHierarchy|ColumnChangeBlocked $exception) {
            Notification::make()
                ->danger()
                ->title('Карточка не сохранена')
                ->body($exception->getMessage())
                ->send();

            $this->halt();
        }

        return $record->refresh();
    }
}
