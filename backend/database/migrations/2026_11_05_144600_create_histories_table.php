<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Журнал изменений карточек и направлений.
     *
     * Пишется в той же транзакции, что и само изменение (пайп TransactionMiddleware
     * шины команд), поэтому «изменение без записи» невозможно.
     * На update — одна строка на каждое изменённое поле: field, old_value и
     * new_value заполняются только тогда; для insert/delete они null.
     * Внешних ключей на сущности нет намеренно: запись должна переживать
     * удаление карточки или направления.
     */
    public function up(): void
    {
        Schema::create('histories', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 16);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 16);
            $table->string('field', 64)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('created_at')->nullable();

            // История конкретной сущности в хронологическом порядке и действия пользователя.
            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('histories');
    }
};
