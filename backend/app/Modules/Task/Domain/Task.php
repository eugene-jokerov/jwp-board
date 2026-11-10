<?php

namespace App\Modules\Task\Domain;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardLevel;
use App\Modules\Task\Domain\ValueObjects\CardType;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Карточка канбан-доски.
 *
 * Одна таблица на три уровня: инициатива (стратегический), проект
 * (тактический) и задача (операционный). Уровень в базе не хранится —
 * он выводится из типа (CardType::level()).
 *
 * Модель — только хранилище: инварианты иерархии (какие ссылки допустимы для
 * типа) и правила смены колонки держат доменные сервисы TaskPlacement и
 * ColumnTransitionPolicy, а не сеттеры модели.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $direction_id
 * @property int|null $initiative_id
 * @property int|null $project_id
 * @property CardType $type
 * @property CardColumn $column
 * @property bool $idea
 * @property string $title
 * @property string|null $slug
 * @property string|null $description
 * @property Carbon|null $deadline
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Массово заполняемые атрибуты.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'direction_id',
        'initiative_id',
        'project_id',
        'type',
        'column',
        'idea',
        'title',
        'slug',
        'description',
        'deadline',
    ];

    /**
     * Приведение типов атрибутов.
     *
     * `type` и `column` — php-enum'ы: из модели всегда приходит доменный тип,
     * а не «сырая» строка из базы. `idea` — boolean: в MySQL флаг приходит
     * числом 0/1, а в ответе API и на клиенте это именно `true`/`false`.
     * `deadline` — дата (Carbon), чтобы ответ API отдавал ISO-строку,
     * а не формат базы.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CardType::class,
            'column' => CardColumn::class,
            'idea' => 'boolean',
            'deadline' => 'datetime',
        ];
    }

    /**
     * Связанная фабрика.
     *
     * Модель живёт в доменном слое модуля, поэтому путь к фабрике задаётся явно.
     */
    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    /**
     * Карточка принадлежит указанному пользователю.
     */
    public function belongsToUser(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * Уровень доски, на котором живёт карточка.
     */
    public function level(): CardLevel
    {
        return $this->type->level();
    }
}
