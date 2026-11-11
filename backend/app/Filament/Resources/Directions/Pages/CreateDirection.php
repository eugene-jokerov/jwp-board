<?php

namespace App\Filament\Resources\Directions\Pages;

use App\Filament\Resources\Directions\DirectionResource;
use App\Modules\Direction\Application\Commands\CreateDirectionForAdmin\CreateDirectionForAdminCommand;
use App\Modules\Direction\Domain\Exceptions\DirectionLimitReached;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use App\Shared\Application\Bus\CommandBus;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Создание направления в админке.
 *
 * Запись делается командой, а не через модель: направление проверяется
 * доменным правилом лимита (DirectionManager) и попадает в журнал истории.
 * Ошибка лимита превращается в уведомление формы, а не в страницу исключения.
 */
class CreateDirection extends CreateRecord
{
    protected static string $resource = DirectionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $command = new CreateDirectionForAdminCommand(
            (int) $data['user_id'],
            DirectionTitle::fromString((string) $data['title']),
            DirectionColor::fromKey((string) $data['color']),
        );

        try {
            $result = app(CommandBus::class)->dispatch($command);
        } catch (DirectionLimitReached $exception) {
            Notification::make()
                ->danger()
                ->title('Направление не создано')
                ->body($exception->getMessage())
                ->send();

            $this->halt();
        }

        return DirectionResource::getModel()::query()->findOrFail((int) $result->id);
    }
}
