<?php

namespace App\Filament\Resources\Directions\Pages;

use App\Filament\Resources\Directions\DirectionResource;
use App\Filament\Resources\Directions\Tables\DirectionsTable;
use App\Modules\Direction\Application\Commands\DeleteDirectionForAdmin\DeleteDirectionForAdminCommand;
use App\Modules\Direction\Application\Commands\UpdateDirectionForAdmin\UpdateDirectionForAdminCommand;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\Exceptions\DirectionHasCards;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use App\Shared\Application\Bus\CommandBus;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Редактирование направления в админке.
 *
 * Владелец не меняется: карточки ссылаются на направление, и перенос к другому
 * пользователю увёл бы их с чужой доски. Поэтому user_id из формы отбрасывается
 * перед сохранением, а изменяются только название и цвет.
 */
class EditDirection extends EditRecord
{
    protected static string $resource = DirectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->using(function (Direction $record): bool {
                    try {
                        app(CommandBus::class)->dispatch(
                            new DeleteDirectionForAdminCommand((int) $record->user_id, (int) $record->id),
                        );
                    } catch (DirectionHasCards $exception) {
                        DirectionsTable::notifyNotDeletable($exception);

                        return false;
                    }

                    return true;
                }),
        ];
    }

    /**
     * Владелец направления неизменяем.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['user_id']);

        return $data;
    }

    /**
     * Сохранение через команду: цвет и название проверяют value object'ы,
     * изменение попадает в журнал истории.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        app(CommandBus::class)->dispatch(new UpdateDirectionForAdminCommand(
            (int) $record->user_id,
            (int) $record->id,
            isset($data['title']) ? DirectionTitle::fromString((string) $data['title']) : null,
            isset($data['color']) ? DirectionColor::fromKey((string) $data['color']) : null,
        ));

        return $record->refresh();
    }
}
