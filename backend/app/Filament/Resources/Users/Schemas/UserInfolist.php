<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

/**
 * Карточка пользователя (страница просмотра) в админке.
 */
class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('email')
                    ->label('Email'),
                IconEntry::make('is_admin')
                    ->label('Администратор')
                    ->boolean(),
                IconEntry::make('readonly')
                    ->label('Только чтение')
                    ->boolean(),
                IconEntry::make('email_verified_at')
                    ->label('Email подтверждён')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->email_verified_at !== null),
                TextEntry::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i'),
                TextEntry::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y H:i'),
            ]);
    }
}
