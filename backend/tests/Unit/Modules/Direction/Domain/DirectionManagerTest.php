<?php

namespace Tests\Unit\Modules\Direction\Domain;

use App\Modules\Direction\Domain\Exceptions\DirectionLimitReached;
use App\Modules\Direction\Domain\Services\DirectionManager;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Direction\Support\InMemoryDirectionRepository;

/**
 * Инвариант количества направлений: у пользователя их не больше лимита,
 * переданного сервису (в приложении — config('direction.max_per_user')).
 */
final class DirectionManagerTest extends TestCase
{
    private const int USER_ID = 10;

    /**
     * Лимит для тестов: число произвольное — важно, что сервис берёт его из
     * конструктора, а не из константы или конфига.
     */
    private const int LIMIT = 3;

    /**
     * Репозиторий с указанным числом направлений у тестового пользователя.
     */
    private function repositoryWith(int $count, int $userId = self::USER_ID): InMemoryDirectionRepository
    {
        $repository = new InMemoryDirectionRepository;

        for ($id = 1; $id <= $count; $id++) {
            $repository->add($id, $userId);
        }

        return $repository;
    }

    private function manager(InMemoryDirectionRepository $repository, int $limit = self::LIMIT): DirectionManager
    {
        return new DirectionManager($repository, $limit);
    }

    public function test_create_is_allowed_below_limit(): void
    {
        $repository = $this->repositoryWith(self::LIMIT - 1);

        $created = $this->manager($repository)->create(
            self::USER_ID,
            DirectionTitle::fromString('Новое'),
            DirectionColor::Green,
        );

        self::assertSame(self::USER_ID, $created->user_id);
        self::assertSame(1, $repository->createdCount());
        self::assertSame(self::LIMIT, $repository->countForUser(self::USER_ID));
    }

    public function test_create_is_forbidden_at_limit(): void
    {
        $repository = $this->repositoryWith(self::LIMIT);

        $this->expectException(DirectionLimitReached::class);
        $this->expectExceptionMessage('Достигнут лимит направлений (3).');

        try {
            $this->manager($repository)->create(
                self::USER_ID,
                DirectionTitle::fromString('Лишнее'),
                DirectionColor::Green,
            );
        } finally {
            // До записи дело не дошло: лимит проверяется перед созданием.
            self::assertSame(0, $repository->createdCount());
        }
    }

    public function test_limit_is_taken_from_constructor(): void
    {
        $repository = $this->repositoryWith(1);

        // Тот же репозиторий и тот же пользователь, но лимит равен единице:
        // сервис обязан ориентироваться на переданное число.
        $this->expectException(DirectionLimitReached::class);

        $this->manager($repository, 1)->create(
            self::USER_ID,
            DirectionTitle::fromString('Второе'),
            DirectionColor::Green,
        );
    }

    public function test_limit_is_counted_per_user(): void
    {
        $repository = $this->repositoryWith(self::LIMIT, self::USER_ID + 1);

        $created = $this->manager($repository)->create(
            self::USER_ID,
            DirectionTitle::fromString('Своё'),
            DirectionColor::Green,
        );

        self::assertSame(self::USER_ID, $created->user_id);
        self::assertSame(1, $repository->countForUser(self::USER_ID));
    }
}
