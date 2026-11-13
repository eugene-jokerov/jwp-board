<?php

namespace App\Modules\Direction\Infrastructure\Persistence;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\Exceptions\DirectionNotFound;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use Illuminate\Support\Facades\DB;

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
        $direction = EloquentDirectionModel::query()
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
        return EloquentDirectionModel::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function countForUser(int $userId): int
    {
        return EloquentDirectionModel::query()->where('user_id', $userId)->count();
    }

    public function create(int $userId, DirectionTitle $title, DirectionColor $color): Direction
    {
        return EloquentDirectionModel::query()->create([
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
        // Соседний модуль доступен только через свой Domain; модели его
        // хранилища сюда не тянутся — считаем строки таблицы по ключу.
        return DB::table('tasks')->where('direction_id', $direction->id)->count();
    }
}
