<?php

namespace App\Filament\Resources\Directions\Schemas;

use App\Filament\Resources\Directions\Support\DirectionColorOptions;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Форма направления в админке.
 *
 * Поля повторяют схему таблицы directions: владелец (user_id), название
 * (непустое, ≤255 символов) и ключ палитры цвета. Значения проверяются
 * value object'ами при сохранении команды, здесь — только форма: типы,
 * обязательность и допустимые ключи палитры (c0 направлению не выбирается).
 */
class DirectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Владелец')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Направление виден только на доске своего владельца.'),
                TextInput::make('title')
                    ->label('Название')
                    ->required()
                    ->maxLength(DirectionTitle::MAX_LENGTH),
                Select::make('color')
                    ->label('Цвет')
                    ->options(DirectionColorOptions::labels())
                    ->required()
                    ->in(DirectionColor::keys())
                    ->helperText('Ключ палитры (c1…c19). Цвет проверяется при сохранении.'),
            ]);
    }
}
