<?php

namespace Database\Factories;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\User\Domain\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Фабрика карточек для тестов.
 *
 * По умолчанию создаёт задачу без проекта. Состояния initiative(), project()
 * и task() собирают непротиворечивую иерархию и корректную денормализацию
 * direction_id — так же, как это делает доменный сервис.
 *
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @var class-string<Task>
     */
    protected $model = Task::class;

    /**
     * Счётчик заголовков: даёт уникальное имя карточке без генератора случайных чисел.
     */
    private static int $titleCounter = 0;

    /**
     * Следующий номер для заголовка карточки.
     */
    private static function nextTitleNumber(): int
    {
        return ++self::$titleCounter;
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'direction_id' => null,
            'initiative_id' => null,
            'project_id' => null,
            'type' => CardType::Task->value,
            'column' => CardColumn::Backlog->value,
            'idea' => false,
            // Заголовок уникален в пределах вызова fake(): суффикс из
            // счётчика не зависит от генератора случайных чисел. `unique()`
            // здесь не годится — при исчерпании значений фейкер кидает
            // OverflowException, и фабрика молча теряет карточку, из-за чего
            // count(N) создаёт N-1 запись.
            'title' => 'Карточка '.self::nextTitleNumber(),
            'slug' => null,
            'description' => null,
            'deadline' => null,
        ];
    }

    /**
     * Карточка с дедлайном.
     */
    public function withDeadline(?CarbonInterface $deadline): static
    {
        return $this->state(fn (array $attributes): array => [
            'deadline' => $deadline,
        ]);
    }

    /**
     * Карточка конкретной колонки.
     */
    public function inColumn(CardColumn $column): static
    {
        return $this->state(fn (array $attributes): array => [
            'column' => $column->value,
        ]);
    }

    /**
     * Карточка-идея: степень проработки «ещё идея», а не «на реализацию».
     */
    public function idea(): static
    {
        return $this->state(fn (array $attributes): array => [
            'idea' => true,
        ]);
    }

    /**
     * Инициатива со своим направлением.
     */
    public function initiative(?Direction $direction = null): static
    {
        return $this->state(function (array $attributes) use ($direction): array {
            $userId = $attributes['user_id'];

            if ($userId instanceof User) {
                $userId = (int) $userId->id;
            }

            $directionId = $direction !== null
                ? (int) $direction->id
                : (int) Direction::factory()->create(['user_id' => $userId])->id;

            return [
                'type' => CardType::Initiative->value,
                'direction_id' => $directionId,
                'initiative_id' => null,
                'project_id' => null,
            ];
        });
    }

    /**
     * Проект: с инициативой или без неё (тогда и без направления).
     */
    public function project(?Task $initiative = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CardType::Project->value,
            'direction_id' => $initiative?->direction_id,
            'initiative_id' => $initiative === null ? null : (int) $initiative->id,
            'project_id' => null,
        ]);
    }

    /**
     * Задача под проектом: наследует инициативу и направление проекта.
     */
    public function task(?Task $project = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CardType::Task->value,
            'direction_id' => $project?->direction_id,
            'initiative_id' => $project?->initiative_id,
            'project_id' => $project === null ? null : (int) $project->id,
        ]);
    }
}
