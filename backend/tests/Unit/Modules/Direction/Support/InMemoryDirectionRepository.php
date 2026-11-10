<?php

namespace Tests\Unit\Modules\Direction\Support;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\Exceptions\DirectionNotFound;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;

/**
 * Тестовый двойник порта DirectionRepository для Unit-тестов доменных правил.
 *
 * Приложение в Unit-тестах не поднято, поэтому модели наполняются напрямую
 * через setRawAttributes(): касты enum доступны и без БД, а сохранение и
 * запросы — нет.
 */
final class InMemoryDirectionRepository implements DirectionRepository
{
    /**
     * Направления по id.
     *
     * @var array<int, Direction>
     */
    private array $directions = [];

    /**
     * Счётчик созданий — проверка, дошло ли дело до записи.
     */
    private int $created = 0;

    /**
     * Занять id направления указанным пользователем.
     */
    public function add(int $id, int $userId): void
    {
        $this->directions[$id] = self::direction($id, $userId);
    }

    /**
     * Сколько раз репозиторий создавал направление.
     */
    public function createdCount(): int
    {
        return $this->created;
    }

    public function findForUser(int $userId, int $id): Direction
    {
        $direction = $this->directions[$id] ?? null;

        if ($direction === null || $direction->user_id !== $userId) {
            throw DirectionNotFound::withId($id);
        }

        return $direction;
    }

    /**
     * @return list<Direction>
     */
    public function allForUser(int $userId): array
    {
        return array_values(array_filter(
            $this->directions,
            static fn (Direction $direction): bool => $direction->user_id === $userId,
        ));
    }

    public function countForUser(int $userId): int
    {
        return count($this->allForUser($userId));
    }

    public function create(int $userId, DirectionTitle $title, DirectionColor $color): Direction
    {
        $id = $this->directions === [] ? 1 : max(array_keys($this->directions)) + 1;
        $direction = self::direction($id, $userId);

        $this->directions[$id] = $direction;
        $this->created++;

        return $direction;
    }

    public function update(Direction $direction, ?DirectionTitle $title, ?DirectionColor $color): Direction
    {
        $this->directions[(int) $direction->id] = $direction;

        return $direction;
    }

    public function delete(Direction $direction): void
    {
        unset($this->directions[(int) $direction->id]);
    }

    public function countCards(Direction $direction): int
    {
        return 0;
    }

    /**
     * Собрать направление без обращения к БД.
     */
    public static function direction(int $id, int $userId): Direction
    {
        $direction = new Direction;
        $direction->setRawAttributes([
            'id' => $id,
            'user_id' => $userId,
            'title' => 'Направление '.$id,
            'color' => DirectionColor::Blue->value,
        ], true);

        return $direction;
    }
}
