<?php

namespace Database\Seeders;

use App\Modules\User\Domain\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Демо-пользователь для локальной разработки.
 * Вход: demo@jwp-board.local / password
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Заполнить базу демо-данными.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'demo@jwp-board.local'],
            [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
