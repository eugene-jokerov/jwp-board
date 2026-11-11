<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Признак администратора: единственное условие доступа в админку (/admin).
     *
     * default(false) — все существующие пользователи остаются обычными
     * клиентами, админ-доступ выдаётся точечно (tinker или админка).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('email_verified_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_admin']);
            $table->dropColumn('is_admin');
        });
    }
};
