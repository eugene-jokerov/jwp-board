<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Степень проработки карточки: идея или готова к реализации.
     *
     * Флаг хранится у карточки любого уровня и любой колонки (при смене колонки
     * значение не сбрасывается), но смысл имеет только в беклоге: там идеи
     * стоят ниже карточек, готовых к реализации. По умолчанию `false` —
     * существующие карточки считаются готовыми к реализации, поведение доски
     * не меняется.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('idea')->default(false)->after('column');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('idea');
        });
    }
};
