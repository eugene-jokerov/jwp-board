<?php

namespace App\Modules\Direction\Domain;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Domain\Task;
use App\Modules\User\Domain\User;
use Database\Factories\DirectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Направление движения по жизни — корневая сущность модуля Direction.
 *
 * Записи направлений меняются только через прикладные сценарии (команды),
 * поэтому модель не содержит сеттеров бизнес-полей: инварианты (цвет из
 * палитры, непустое название, запрет удаления с карточками) держат доменные
 * сервисы и value object'ы, а не «умная» модель.
 *
 * @property int $id
 * @property int $user_id
 * @property DirectionColor $color
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Direction extends Model
{
    /** @use HasFactory<DirectionFactory> */
    use HasFactory;

    /**
     * Массово заполняемые атрибуты.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'color',
        'title',
    ];

    /**
     * Приведение типов атрибутов.
     *
     * `color` кастуется в php-enum: из модели всегда приходит доменный тип,
     * а не «сырая» строка ключа палитры.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => DirectionColor::class,
        ];
    }

    /**
     * Связанная фабрика.
     *
     * Модель живёт в доменном слое модуля, поэтому путь к фабрике задаётся явно.
     */
    protected static function newFactory(): DirectionFactory
    {
        return DirectionFactory::new();
    }

    /**
     * Владелец направления.
     *
     * Связь нужна админке: Filament строит по ней поле выбора владельца, колонку
     * таблицы и фильтр. Прикладной слой по-прежнему работает с user_id.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Карточки направления (всех уровней).
     *
     * Связь объявлена для админки: Filament строит по ней колонку-счётчик
     * и список карточек на странице направления. Денормализация direction_id
     * на карточках делает связь одним join'ом, без обхода иерархии.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Направление принадлежит указанному пользователю.
     */
    public function belongsToUser(int $userId): bool
    {
        return $this->user_id === $userId;
    }
}
