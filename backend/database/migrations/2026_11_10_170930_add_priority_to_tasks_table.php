<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Приоритет карточки: низкий, средний или высокий.
     *
     * Как и `column` с `type`, значение хранится строкой, а не SQL-ENUM:
     * набор допустимых значений держит php-перечисление CardPriority, которое
     * проверяет валидация и приводит Eloquent (cast). Добавить приоритет тогда
     * не сложнее, чем поправить enum.
     *
     * По умолчанию `medium`: существующие карточки становятся «средними»,
     * поведение доски не меняется, а новым точкам создания не нужно помнить
     * про значение — его подставляет сама база.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority', 8)->default('medium')->after('column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
