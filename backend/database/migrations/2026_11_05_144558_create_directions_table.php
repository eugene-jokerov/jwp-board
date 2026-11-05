<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Направления движения по жизни — корень иерархии доски.
     *
     * Цвет — строка, а не ENUM СУБД: список значений держит PHP-enum
     * (Domain\ValueObjects\DirectionColor). Так палитру можно расширить
     * миграцией данных, не перестраивая таблицу.
     * Уникальности названия нет: одинаковые направления у разных людей норма.
     */
    public function up(): void
    {
        Schema::create('directions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('color', 8);
            $table->string('title', 255);
            $table->timestamps();

            // Основная выборка: направления конкретного пользователя.
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('directions');
    }
};
