<?php

namespace App\Modules\Direction\Infrastructure\Persistence;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\Exceptions\DirectionNotFound;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use App\Modules\Task\Domain\Task;

/**
 * Eloquent-реализация порта DirectionRepository.
 *
 * Все запросы ограничены user_id: граница пользователя — часть контракта порта,
 * а не забота вызывающего кода.
 */
final class EloquentDirectionRepository implements DirectionRepository
{
    public function findForUser(int $userId, int $id): Direction
    {
        $direction = Direction::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        if ($direction === null) {
            throw DirectionNotFound::withId($id);
        }

        return $direction;
    }

    public function allForUser(int $userId): array
    {
        return Direction::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function create(int $userId, DirectionTitle $title, DirectionColor $color): Direction
    {
        return Direction::query()->create([
            'user_id' => $userId,
            'title' => $title->value(),
            'color' => $color->key(),
        ]);
    }

    public function update(Direction $direction, ?DirectionTitle $title, ?DirectionColor $color): Direction
    {
        if ($title !== null) {
            $direction->title = $title->value();
        }

        if ($color !== null) {
            $direction->color = $color;
        }

        $direction->save();

        return $direction;
    }

    public function delete(Direction $direction): void
    {
        $direction->delete();
    }

    public function countCards(Direction $direction): int
    {
        // Денормализация direction_id на карточках позволяет проверить
        // «направление не пустое» одним запросом к tasks без обхода иерархии.
        return Task::query()->where('direction_id', $direction->id)->count();
    }
}
