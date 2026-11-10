<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Кодовое название карточки (slug) — необязательная короткая метка.
     *
     * Уникальность не проверяется и индекс не нужен: по slug ничего не ищут,
     * совпадения допустимы. Ограничение длины 16 символов дублирует
     * TaskSlug::MAX_LENGTH — там же проверяется и длина значения.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('slug', 16)->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
