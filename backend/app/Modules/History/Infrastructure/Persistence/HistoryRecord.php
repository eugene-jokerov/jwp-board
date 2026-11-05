<?php

namespace App\Modules\History\Infrastructure\Persistence;

use App\Modules\History\Domain\ValueObjects\EntityType;
use App\Modules\History\Domain\ValueObjects\HistoryAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eloquent-представление строки журнала изменений.
 *
 * Записи только добавляются: модель без массового заполнения бизнес-полей
 * через API и без обновления — история должна оставаться неизменной.
 *
 * @property int $id
 * @property EntityType $entity_type
 * @property int $entity_id
 * @property int|null $user_id
 * @property HistoryAction $action
 * @property string|null $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property Carbon|null $created_at
 */
class HistoryRecord extends Model
{
    /**
     * Таблица журнала.
     *
     * @var string
     */
    protected $table = 'histories';

    /**
     * У журнала нет updated_at: запись не меняется после создания.
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'entity_type',
        'entity_id',
        'user_id',
        'action',
        'field',
        'old_value',
        'new_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => EntityType::class,
            'action' => HistoryAction::class,
        ];
    }
}
