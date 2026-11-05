<?php

namespace Database\Factories;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\User\Domain\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Фабрика направлений для тестов и сидов.
 *
 * @extends Factory<Direction>
 */
class DirectionFactory extends Factory
{
    /**
     * @var class-string<Direction>
     */
    protected $model = Direction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
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
