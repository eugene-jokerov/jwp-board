<?php

namespace App\Modules\User\Domain;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Пользователь — корневая сущность модуля User.
 *
 * Идентификация происходит исключительно по email и паролю: отдельного имени
 * в модели нет. Модель знает про Sanctum, поскольку токены — часть способа
 * аутентификации пользователя, а не отдельной бизнес-логики. Про админку
 * (Filament) домен не знает: контракты панели реализует адаптер
 * Infrastructure\Filament\AdminUser.
 *
 * Доступ в админку (Filament, /admin) даёт флаг is_admin. Флаг намеренно не
 * в $fillable: иначе его можно было бы поднять через регистрацию или API.
 *
 * Флаг readonly переводит учётную запись в режим «только чтение»: пользователь
 * видит доску, но не меняет её. Как и is_admin, флаг не массово заполняемый —
 * выдаётся только из админки.
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * Массово заполняемые атрибуты.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
    ];

    /**
     * Атрибуты, скрытые при сериализации.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Приведение типов атрибутов.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'readonly' => 'boolean',
        ];
    }

    /**
     * Режим «только чтение».
     *
     * Пользователь с этим флагом не может создавать, изменять и удалять данные:
     * запрет проверяют DenyReadOnlyWrites (HTTP) и DenyReadOnlyCommands (шина).
     * Явное приведение — как в canAccessPanel(): у строк, созданных до появления
     * колонки, атрибут может отсутствовать, и null здесь значит «нет».
     */
    public function isReadonly(): bool
    {
        return (bool) $this->readonly;
    }

    /**
     * Пользователь — администратор (доступ в админку).
     *
     * Панель одна (`admin`), поэтому условие общее. Явное приведение: у строк,
     * созданных до появления колонки, атрибут может отсутствовать — null здесь
     * значит «нет».
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }
}
