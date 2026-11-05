<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Карточки канбан-доски трёх уровней: инициативы, проекты и задачи.
     *
     * Иерархия описана тремя ссылками на саму себя и на направление:
     *  - direction_id — денормализация: направление карточки на любом уровне,
     *    чтобы доска и правила смены колонки не собирали цепочку join'ов;
     *  - iniciative_id — инициатива проекта (а также карточки операционного уровня);
     *    опечатка в имени зафиксирована исторически: колонка переименована
     *    в initiative_id миграцией 2026_11_05_153742_rename_iniciative_id_to_initiative_id_in_tasks;
     *  - project_id — проект задачи.
     * Ссылки самозамыкающиеся, поэтому FK не каскадные по детям: удаление
     * карточки с детьми запрещено бизнес-правилом, а не молчаливым удалением поддерева.
     *
     * `column` и `type` — зарезервированные слова SQL, но Laravel квотирует
     * имена колонок автоматически (обратные кавычки в MySQL).
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('direction_id')->nullable()->constrained('directions')->nullOnDelete();
            $table->foreignId('iniciative_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('type', 16);
            $table->string('column', 16);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamps();

            // Выборка доски: карточки пользователя нужного типа в нужной колонке.
            $table->index(['user_id', 'type', 'column']);
            // Проверка «есть ли дети в работе» при смене колонки и удалении.
            $table->index(['project_id', 'column']);
            $table->index(['iniciative_id', 'column']);
            // Запрет удаления направления, пока в нём есть карточки.
            $table->index(['direction_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
