<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Исправление опечатки в имени ссылки на инициативу: iniciative_id → initiative_id.
     *
     * В create_tasks_table колонка называлась с опечаткой; в проекте ещё нет
     * данных, которые нельзя было бы сохранить, поэтому колонка, её индекс
     * и внешний ключ переименовываются, а значения остаются на месте.
     *
     * Порядок шагов важен: MySQL не даёт сослаться на переименованную колонку
     * в том же ALTER TABLE, поэтому снятие индекса и ключа, переименование
     * и их создание заново выполняются отдельными операциями.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['iniciative_id']);
            $table->dropIndex(['iniciative_id', 'column']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('iniciative_id', 'initiative_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('initiative_id')->references('id')->on('tasks')->nullOnDelete();
            $table->index(['initiative_id', 'column']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['initiative_id']);
            $table->dropIndex(['initiative_id', 'column']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->renameColumn('initiative_id', 'iniciative_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('iniciative_id')->references('id')->on('tasks')->nullOnDelete();
            $table->index(['iniciative_id', 'column']);
        });
    }
};
