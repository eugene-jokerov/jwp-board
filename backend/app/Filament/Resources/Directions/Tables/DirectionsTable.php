<?php

namespace App\Filament\Resources\Directions\Tables;

use App\Filament\Resources\Directions\Support\DirectionColorOptions;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\Exceptions\DirectionHasCards;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Список направлений в админке.
 *
 * Удаление — только пустого направления: правило держит DirectionManager,
 * а здесь доменное исключение DirectionHasCards превращается в уведомление,
 * чтобы в панели не всплывала страница ошибки вместо подсказки.
 */
class DirectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user'])->withCount('tasks'))
            ->columns([
                TextColumn::make('user.email')
                    ->label('Владелец')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('color')
                    ->label('Цвет')
                    ->badge()
                    ->formatStateUsing(fn (DirectionColor $state): ?string => DirectionColorOptions::label($state))
                    ->sortable(),
                TextColumn::make('tasks_count')
                    ->label('Карточек')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Владелец')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Удалить выбранные'),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(
                fn (Direction $record): bool => ! $record->tasks()->exists(),
            );
    }

    /**
     * Уведомление о запрете удаления непустого направления.
     *
     * Метод общий для одиночного и массового удаления: правило одно, меняется
     * только способ вызова.
     */
    public static function notifyNotDeletable(DirectionHasCards $exception): void
    {
        Notification::make()
            ->danger()
            ->title('Направление не удалено')
            ->body($exception->getMessage())
            ->send();
    }
}
