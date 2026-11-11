<?php

namespace App\Modules\User\Domain;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
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
 *
 * Доступ в админку (Filament, /admin) даёт флаг is_admin. Флаг намеренно не
 * в $fillable: иначе его можно было бы поднять через регистрацию или API.
 */
class User extends Authenticatable implements FilamentUser, HasName
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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Доступ в админку Filament.
     *
     * Панель одна (`admin`), поэтому условие общее: только администратор.
     * Проверка живёт в модели, а не в панели, — решение о доступе принимает
     * домен пользователя, а не UI-слой.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Явное приведение: у пользователей, созданных до появления флага,
        // атрибут может отсутствовать в загруженной модели — null здесь значит «нет».
        return (bool) $this->is_admin;
    }

    /**
     * Имя пользователя в интерфейсе Filament (топбар, аватар).
     *
     * Колонки `name` в таблице нет — единственный идентификатор это email.
     * Без этого метода панель падает: Filament ожидает строку.
     */
    public function getFilamentName(): string
    {
        return (string) $this->email;
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
