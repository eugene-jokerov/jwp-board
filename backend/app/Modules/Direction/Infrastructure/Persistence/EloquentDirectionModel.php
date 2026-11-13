<?php

namespace App\Modules\Direction\Infrastructure\Persistence;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Infrastructure\Persistence\Factories\DirectionFactory;
use App\Modules\User\Domain\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Направление для инфраструктурного слоя Direction.
 *
 * Доменная модель Direction не знает ни о соседних модулях, ни о фабриках.
 * Связь «владелец» — отображение таблицы на объект: админка строит по ней
 * колонку и фильтр. Карточки лежат в модуле Task и доступны только через его
 * Domain, поэтому их запросы адаптер направления не объявляет.
 */
final class EloquentDirectionModel extends Direction
{
    /**
     * Таблица доменной модели.
     *
     * Имя класса адаптера Eloquent не превращает в имя таблицы (получилось бы
     * `eloquent_direction_models`), поэтому таблица задана явно — та же,
     * что у доменной модели.
     */
    protected $table = 'directions';

    /** @use HasFactory<DirectionFactory> */
    use HasFactory;

    /**
     * Связанная фабрика.
     *
     * Модель живёт вне App\Models, поэтому автопоиск по namespace не работает.
     */
    protected static function newFactory(): DirectionFactory
    {
        return DirectionFactory::new();
    }

    /**
     * Владелец направления.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
