<?php

namespace App\Filament\Resources\Directions\Schemas;

use App\Filament\Resources\Directions\Support\DirectionColorOptions;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * Карточка направления (страница просмотра) в админке.
 *
 * Кроме полей направления показывается число карточек: непустое направление
 * удалить нельзя, поэтому счётчик — главный ориентир перед удалением.
 */
class DirectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.email')
                    ->label('Владелец'),
                TextEntry::make('title')
                    ->label('Название'),
                TextEntry::make('color')
                    ->label('Цвет')
                    ->formatStateUsing(fn (DirectionColor $state): ?string => DirectionColorOptions::label($state)),
                TextEntry::make('tasks_count')
                    ->label('Карточек')
                    ->state(fn (Direction $record): int => $record->tasks()->count()),
                TextEntry::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i'),
                TextEntry::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i'),
            ]);
    }
}
