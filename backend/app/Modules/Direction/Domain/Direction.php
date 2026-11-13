<?php

namespace App\Modules\Direction\Domain;

use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use Illuminate\Database\Eloquent\Model;
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
     * Направление принадлежит указанному пользователю.
     */
    public function belongsToUser(int $userId): bool
    {
        return $this->user_id === $userId;
    }
}
