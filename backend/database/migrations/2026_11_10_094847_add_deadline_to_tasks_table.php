<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Дедлайн карточки — необязательная дата окончания.
     *
     * Тип dateTime, а не timestamp: время выбирается только вместе с датой,
     * а у timestamp в MySQL есть ограничения по умолчанию и диапазону, которые
     * для простой даты дедлайна ни к чему. В интерфейсе пользователь выбирает
     * день, поэтому время хранится как 00:00:00 выбранной даты.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dateTime('deadline')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('deadline');
        });
    }
};
