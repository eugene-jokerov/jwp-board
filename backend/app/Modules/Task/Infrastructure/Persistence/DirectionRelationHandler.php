<?php

namespace App\Modules\Task\Infrastructure\Persistence;

use App\Modules\Direction\Domain\Direction;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Связь «карточка → направление» для доменной модели Direction.
 *
 * Соседний модуль виден Task только через свой Domain, а доменная модель не
 * умеет строить связи (это забота адаптера). Поэтому связь регистрируется
 * морфологической картой Eloquent: имя `direction` у задачи разбирает этот
 * обработчик.
 *
 * Eloquent передаёт обработчику `$this` модели, на которой читают связь.
 * Направление читают только у карточки: для любой другой модели (например,
 * роли Sanctum) возвращается null, и поиск связи продолжается обычным путём.
 */
final class DirectionRelationHandler
{
    /**
     * Построить связь «принадлежит» на карточке.
     *
     * @param  mixed  $parent  модель, на которой читают связь
     * @return BelongsTo<Direction, EloquentTaskModel>|null
     */
    public function __invoke(mixed $parent): ?BelongsTo
    {
        if (! $parent instanceof EloquentTaskModel) {
            return null;
        }

        return $parent->belongsTo(Direction::class, 'direction_id');
    }
}
