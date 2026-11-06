<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Пользовательские настройки: пары «ключ — значение».
     *
     * Значение — текст, а не JSON-колонка: настройки разные по форме
     * (свёрнутые уровни, свёрнутые колонки), и структуру конкретной настройки
     * знает её владелец, а не схема БД. На пару (user_id, meta_key) стоит
     * уникальный индекс: у пользователя один ключ — одно значение, поэтому
     * сохранение настройки — это UPSERT.
     */
    public function up(): void
    {
        Schema::create('user_meta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('meta_key', 255);
            $table->text('meta_value')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'meta_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_meta');
    }
};
