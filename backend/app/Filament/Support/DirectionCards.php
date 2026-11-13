<?php

namespace App\Filament\Support;

use App\Modules\Direction\Infrastructure\Persistence\EloquentDirectionModel;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Карточки направления для админки.
 *
 * Карточки лежат в модуле Task, направление — в модуле Direction, и адаптер
 * одного модуля не видит сохранение другого. Входной адаптер панели — то
 * место, где они встречаются: связь «карточки направления» строится здесь
 * и регистрируется в морфологической карте Eloquent под именем `tasks`.
 * Так Filament (счётчик, RelationManager) читает её как обычную HasMany.
 */
final class DirectionCards
{
    /**
     * Связь «карточки направления» по денормализованному direction_id.
     *
     * @return HasMany<EloquentTaskModel, EloquentDirectionModel>
     */
    public static function relation(mixed $parent): ?HasMany
    {
        if (! $parent instanceof EloquentDirectionModel) {
            return null;
        }

        return $parent->hasMany(EloquentTaskModel::class, 'direction_id');
    }
}
