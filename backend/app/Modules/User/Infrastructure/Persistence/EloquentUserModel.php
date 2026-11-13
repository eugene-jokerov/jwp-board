<?php

namespace App\Modules\User\Infrastructure\Persistence;

use App\Modules\User\Domain\User;
use App\Modules\User\Infrastructure\Persistence\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Пользователь для инфраструктурного слоя User.
 *
 * Доменная модель User не знает ни про фабрики, ни про админку: здесь
 * добавлены фабрика и контракты Filament, которые строят из неё адаптер
 * AdminUser. Поэтому панель не получает доменную модель напрямую.
 */
final class EloquentUserModel extends User implements FilamentUser, HasName
{
    /**
     * Таблица доменной модели.
     *
     * Имя класса адаптера Eloquent не превращает в имя таблицы (получилось бы
     * `eloquent_user_models`), поэтому таблица задана явно — та же,
     * что у доменной модели.
     */
    protected $table = 'users';

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Связанная фабрика.
     *
     * Модель живёт вне App\Models, поэтому автопоиск по namespace не работает.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Доступ в панель: только администратор.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    /**
     * Имя в топбаре и на аватаре: отдельной колонки `name` нет, это email.
     */
    public function getFilamentName(): string
    {
        return $this->email;
    }
}
