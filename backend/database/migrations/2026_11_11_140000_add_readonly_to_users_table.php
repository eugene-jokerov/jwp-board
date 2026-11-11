<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Признак «только чтение»: пользователь видит данные, но не меняет их.
     *
     * default(false) — все существующие пользователи сохраняют полный доступ,
     * режим выдаётся точечно (админка или tinker), как и is_admin.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('readonly')->default(false)->after('is_admin')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['readonly']);
            $table->dropColumn('readonly');
        });
    }
};
