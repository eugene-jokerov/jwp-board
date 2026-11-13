<?php

namespace App\Modules\Task\Infrastructure\Persistence\Factories;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskModel;
use App\Modules\User\Domain\User;
use Carbon\CarbonInterface;
use Database\Factories\FactoryOwners;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Фабрика карточек для тестов.
 *
 * По умолчанию создаёт задачу без проекта. Состояния initiative(), project()
 * и task() собирают непротиворечивую иерархию и корректную денормализацию
 * direction_id — так же, как это делает доменный сервис.
 *
 * @extends Factory<EloquentTaskModel>
 */
class TaskFactory extends Factory
{
    /**
     * @var class-string<EloquentTaskModel>
     */
    protected $model = EloquentTaskModel::class;

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
            'user_id' => FactoryOwners::user(),
            'direction_id' => null,
            'initiative_id' => null,
            'project_id' => null,
            'type' => CardType::Task->value,
            'column' => CardColumn::Backlog->value,
            'priority' => CardPriority::Medium->value,
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
     * Карточка конкретного приоритета.
     */
    public function priority(CardPriority $priority): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => $priority->value,
        ]);
    }

    /**
     * Направление для карточки, у которой своё не передано.
     *
     * Направление создаётся через доменный порт: фабрика соседнего модуля
     * не видна, а репозиторий кладёт строку в ту же таблицу.
     */
    private static function createDirection(int $userId): int
    {
        $directions = app(DirectionRepository::class);

        return (int) $directions->create(
            $userId,
            DirectionTitle::fromString('Направление '.self::nextTitleNumber()),
            DirectionColor::Blue,
        )->id;
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
                : self::createDirection($userId);

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
