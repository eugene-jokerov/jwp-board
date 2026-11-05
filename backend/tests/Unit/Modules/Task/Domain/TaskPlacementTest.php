<?php

namespace Tests\Unit\Modules\Task\Domain;

use App\Modules\Task\Domain\Exceptions\InvalidHierarchy;
use App\Modules\Task\Domain\Services\TaskPlacement;
use App\Modules\Task\Domain\ValueObjects\CardType;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Task\Support\InMemoryTaskRepository;

/**
 * Инварианты иерархии карточки: какие ссылки допустимы на каждом уровне
 * и как наследуется денормализованный direction_id.
 */
final class TaskPlacementTest extends TestCase
{
    /**
     * Пользователь 10: направление 1, инициатива 5 в нём, проект 6 под инициативой.
     */
    private function repository(): InMemoryTaskRepository
    {
        $repository = new InMemoryTaskRepository;
        $repository->addDirection(1, 10);
        $repository->add(InMemoryTaskRepository::task(5, 10, [
            'type' => CardType::Initiative->value,
            'direction_id' => 1,
        ]));
        $repository->add(InMemoryTaskRepository::task(6, 10, [
            'type' => CardType::Project->value,
            'direction_id' => 1,
            'iniciative_id' => 5,
        ]));

        return $repository;
    }

    public function test_initiative_requires_own_direction(): void
    {
        $placement = new TaskPlacement($this->repository());

        $this->expectException(InvalidHierarchy::class);

        $placement->resolve(CardType::Initiative, 10, 99, null, null);
    }

    public function test_project_inherits_direction_from_initiative(): void
    {
        $placement = new TaskPlacement($this->repository());

        $result = $placement->resolve(CardType::Project, 10, null, 5, null);

        self::assertSame(['direction_id' => 1, 'iniciative_id' => 5, 'project_id' => null], $result);
    }

    public function test_project_rejects_mismatched_direction(): void
    {
        $repository = $this->repository();
        $repository->addDirection(2, 10);

        $placement = new TaskPlacement($repository);

        $this->expectException(InvalidHierarchy::class);

        $placement->resolve(CardType::Project, 10, 2, 5, null);
    }

    public function test_project_without_initiative_has_no_direction(): void
    {
        $placement = new TaskPlacement($this->repository());

        $result = $placement->resolve(CardType::Project, 10, null, null, null);

        self::assertSame(['direction_id' => null, 'iniciative_id' => null, 'project_id' => null], $result);
    }

    public function test_task_inherits_whole_chain_from_project(): void
    {
        $placement = new TaskPlacement($this->repository());

        $result = $placement->resolve(CardType::Task, 10, null, null, 6);

        self::assertSame(['direction_id' => 1, 'iniciative_id' => 5, 'project_id' => 6], $result);
    }

    public function test_foreign_parent_is_rejected(): void
    {
        $placement = new TaskPlacement($this->repository());

        $this->expectException(InvalidHierarchy::class);

        $placement->resolve(CardType::Project, 11, null, 5, null);
    }

    public function test_update_rejects_self_reference(): void
    {
        $repository = $this->repository();
        $placement = new TaskPlacement($repository);
        $project = $repository->findForUser(10, 6);

        $this->expectException(InvalidHierarchy::class);

        $placement->resolveForUpdate($project, ['project_id' => 6]);
    }
}
