<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * Форма пользователя в админке.
 *
 * Набор полей повторяет реальную схему таблицы users: имени у пользователя нет,
 * идентификация — по email. Пароль обязателен при создании и необязателен при
 * редактировании (пустое поле означает «оставить прежний»); хеш считается
 * вручную, чтобы форма не зависела от каста `hashed` в модели.
 */
class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->revealable()
                    ->maxLength(255)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
                Toggle::make('is_admin')
                    ->label('Администратор')
                    ->helperText('Доступ в админку (/admin).')
                    ->default(false),
                Toggle::make('readonly')
                    ->label('Только чтение')
                    ->helperText('Пользователь видит доску, но не может создавать, изменять и удалять данные.')
                    ->default(false),
                DateTimePicker::make('email_verified_at')
                    ->label('Email подтверждён')
                    ->seconds(false),
            ]);
    }
}
