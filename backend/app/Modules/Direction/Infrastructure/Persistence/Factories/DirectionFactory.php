<?php

namespace App\Modules\Direction\Infrastructure\Persistence\Factories;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Infrastructure\Persistence\EloquentDirectionModel;
use Database\Factories\FactoryOwners;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Фабрика направлений для тестов и сидов.
 *
 * @extends Factory<EloquentDirectionModel>
 */
class DirectionFactory extends Factory
{
    /**
     * @var class-string<EloquentDirectionModel>
     */
    protected $model = EloquentDirectionModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => FactoryOwners::user(),
            'title' => 'Направление '.fake()->unique()->numberBetween(1, 1000),
            'color' => fake()->randomElement(DirectionColor::keys()),
        ];
    }

    /**
     * Направление конкретного цвета из палитры.
     */
    public function withColor(DirectionColor $color): static
    {
        return $this->state(fn (array $attributes): array => [
            'color' => $color->value,
        ]);
    }
}
