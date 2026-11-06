<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Сиды локальной разработки.
 *
 * Наполняет базу доской одного тестового пользователя (test@jwp-board.local),
 * с которой удобно входить в приложение и руками проверять интерфейс.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Заполнить базу демо-данными.
     */
    public function run(): void
    {
        $this->call(UserBoardSeeder::class);
    }
}
