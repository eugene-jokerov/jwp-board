<?php

namespace App\Modules\User\Infrastructure\Persistence\Factories;

use App\Modules\User\Domain\User;
use App\Modules\User\Infrastructure\Persistence\EloquentUserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<EloquentUserModel>
 */
class UserFactory extends Factory
{
    /**
     * Модель, которую создаёт фабрика.
     *
     * Модель живёт в доменном слое модуля User, а не в App\Models, поэтому
     * путь задаётся явно.
     *
     * @var class-string<EloquentUserModel>
     */
    protected $model = EloquentUserModel::class;

    /**
     * Текущий пароль, чтобы не хешировать одно и то же значение в каждом вызове.
     */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    /**
     * Пользователь с неподтверждённым email.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Администратор: единственный, кому доступна админка (/admin).
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_admin' => true,
        ]);
    }

    /**
     * Учётная запись в режиме «только чтение»: данные видны, изменения запрещены.
     */
    public function readonly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'readonly' => true,
        ]);
    }
}
