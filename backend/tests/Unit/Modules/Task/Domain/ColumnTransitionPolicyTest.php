<?php

namespace Tests\Unit\Modules\Task\Domain;

use App\Modules\Task\Domain\Exceptions\ColumnChangeBlocked;
use App\Modules\Task\Domain\Exceptions\TaskHasChildren;
use App\Modules\Task\Domain\Services\ColumnTransitionPolicy;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Task\Support\InMemoryTaskRepository;

/**
 * Правила смены колонки и удаления карточки — на уровне домена, без БД.
 */
final class ColumnTransitionPolicyTest extends TestCase
{
    public function test_operational_card_is_never_blocked(): void
    {
        $repository = new InMemoryTaskRepository;
        $policy = new ColumnTransitionPolicy($repository);
        $task = InMemoryTaskRepository::task(1, 10, ['column' => 'in_progress']);

        self::assertTrue($policy->isAllowed($task, CardColumn::Complete));
        self::assertSame(CardColumn::ordered(), $policy->allowedTransitions($task));
    }

    public function test_tactical_card_is_blocked_by_children_in_progress(): void
    {
        $repository = new InMemoryTaskRepository;
        $repository->setChildren(1, 2, 3);
        $policy = new ColumnTransitionPolicy($repository);
        $task = InMemoryTaskRepository::task(1, 10, ['type' => CardType::Project->value, 'column' => 'in_progress']);

        self::assertFalse($policy->isAllowed($task, CardColumn::Backlog));
        self::assertSame([CardColumn::InProgress], $policy->allowedTransitions($task));
    }

    public function test_strategic_card_is_blocked_by_children_in_progress(): void
    {
        $repository = new InMemoryTaskRepository;
        $repository->setChildren(1, 1, 1);
        $policy = new ColumnTransitionPolicy($repository);
        $task = InMemoryTaskRepository::task(1, 10, ['type' => CardType::Initiative->value, 'column' => 'in_progress']);

        $this->expectException(ColumnChangeBlocked::class);

        $policy->ensureCanMove($task, CardColumn::Canceled);
    }

    public function test_same_column_is_always_allowed(): void
    {
        $repository = new InMemoryTaskRepository;
        $repository->setChildren(1, 5, 5);
        $policy = new ColumnTransitionPolicy($repository);
        $task = InMemoryTaskRepository::task(1, 10, ['type' => CardType::Project->value, 'column' => 'in_progress']);

        $policy->ensureCanMove($task, CardColumn::InProgress);

        self::assertTrue($policy->isAllowed($task, CardColumn::InProgress));
    }

    public function test_delete_is_blocked_by_any_children(): void
    {
        $repository = new InMemoryTaskRepository;
        $repository->setChildren(1, 0, 2);
        $policy = new ColumnTransitionPolicy($repository);
        $task = InMemoryTaskRepository::task(1, 10, ['type' => CardType::Initiative->value]);

        $this->expectException(TaskHasChildren::class);

        $policy->ensureCanDelete($task);
    }

    public function test_delete_is_allowed_without_children(): void
    {
        $policy = new ColumnTransitionPolicy(new InMemoryTaskRepository);
        $task = InMemoryTaskRepository::task(1, 10, ['type' => CardType::Project->value]);

        $policy->ensureCanDelete($task);

        // Исключения нет: карточку без детей удалять можно.
        self::assertSame(0, (new InMemoryTaskRepository)->countChildren($task));
    }

    /**
     * Список карточек считает переходы пачкой: блокировка одной карточки
     * не должна влиять на остальные.
     */
    public function test_allowed_transitions_for_batch_mix_blocked_and_free_cards(): void
    {
        $repository = new InMemoryTaskRepository;
        $repository->setChildren(1, 1, 1);
        $policy = new ColumnTransitionPolicy($repository);

        $blocked = InMemoryTaskRepository::task(1, 10, ['type' => CardType::Project->value, 'column' => 'in_progress']);
        $free = InMemoryTaskRepository::task(2, 10, ['type' => CardType::Project->value, 'column' => 'in_progress']);
        $operational = InMemoryTaskRepository::task(3, 10, ['column' => 'in_progress']);

        $rows = $policy->allowedTransitionsFor([$blocked, $free, $operational]);

        self::assertCount(3, $rows);
        self::assertSame([CardColumn::InProgress], $rows[0]['transitions']);
        self::assertSame(CardColumn::ordered(), $rows[1]['transitions']);
        self::assertSame(CardColumn::ordered(), $rows[2]['transitions']);
        self::assertSame($free, $rows[1]['task']);
    }
}
