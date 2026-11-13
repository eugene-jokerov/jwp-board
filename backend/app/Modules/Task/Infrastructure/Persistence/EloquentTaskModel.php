<?php

namespace App\Modules\Task\Infrastructure\Persistence;

use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Infrastructure\Persistence\Factories\TaskFactory;
use App\Modules\User\Domain\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Карточка для инфраструктурного слоя Task.
 *
 * Доменная модель Task намеренно не знает ни о соседних модулях, ни о фабриках:
 * в домене остаются атрибуты, касты и правила. Связи с владельцем и
 * родительскими карточками — это отображение таблицы на графе объектов, его
 * реализует адаптер, поэтому здесь же объявлены Eloquent-связи и фабрика.
 * Связь с направлением живёт в соседнем модуле и регистрируется
 * DirectionRelationHandler через resolveRelationUsing().
 *
 * @property int $user_id
 * @property int|null $direction_id
 * @property int|null $initiative_id
 * @property int|null $project_id
 * @property string $title
 */
final class EloquentTaskModel extends Task
{
    /**
     * Таблица доменной модели.
     *
     * Имя класса адаптера Eloquent не превращает в имя таблицы (получилось бы
     * `eloquent_task_models`), поэтому таблица задана явно — та же,
     * что у доменной модели.
     */
    protected $table = 'tasks';

    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Связанная фабрика.
     *
     * Модель живёт вне App\Models, поэтому автопоиск по namespace не работает.
     */
    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    /**
     * Владелец карточки.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Инициатива проекта (для карточки стратегического уровня — не заполнена).
     *
     * @return BelongsTo<EloquentTaskModel, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(self::class, 'initiative_id');
    }

    /**
     * Проект задачи (для инициативы и карточек без проекта — не заполнен).
     *
     * @return BelongsTo<EloquentTaskModel, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(self::class, 'project_id');
    }

    /**
     * Дочерние карточки уровнем ниже.
     *
     * Для инициативы это проекты (initiative_id), для проекта — задачи
     * (project_id). Связь нужна админке для отображения и удаления дерева.
     *
     * @return HasMany<EloquentTaskModel, $this>
     */
    public function children(): HasMany
    {
        $foreignKey = $this->type === CardType::Initiative ? 'initiative_id' : 'project_id';

        return $this->hasMany(self::class, $foreignKey);
    }
}
