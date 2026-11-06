<?php

namespace App\Modules\User\Domain;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Пользовательская настройка интерфейса — пара «ключ — значение».
 *
 * Хранилище намеренно «глупое»: значение лежит сырым текстом (обычно JSON),
 * а смысл ключа и структуру значения знает сценарий, который эту настройку
 * читает и пишет. Поэтому у модели нет сеттеров и кастов: инварианты формы
 * ключа держит value object MetaKey, а содержимое значения — прикладной слой.
 *
 * @property int $id
 * @property int $user_id
 * @property string $meta_key
 * @property string|null $meta_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UserMeta extends Model
{
    /**
     * Таблица пар «ключ — значение» пользователя.
     */
    protected $table = 'user_meta';

    /**
     * Массово заполняемые атрибуты.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'meta_key',
        'meta_value',
    ];

    /**
     * Настройка принадлежит указанному пользователю.
     */
    public function belongsToUser(int $userId): bool
    {
        return $this->user_id === $userId;
    }
}
