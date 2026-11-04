<?php

namespace App\Modules\User\Domain;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Пользователь — корневая сущность модуля User.
 *
 * Идентификация происходит исключительно по email и паролю: отдельного имени
 * в модели нет. Модель знает про Sanctum, поскольку токены — часть способа
 * аутентификации пользователя, а не отдельной бизнес-логики.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        ];
    }

    /**
     * Связанная фабрика.
     *
     * Модель живёт в доменном слое модуля (App\Modules\User\Domain), поэтому
     * автопоиск фабрики по namespace модели не работает — путь задаётся явно.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
