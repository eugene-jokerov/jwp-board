<?php

namespace App\Modules\Task\Infrastructure\Persistence;

use App\Modules\User\Domain\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Связь «карточка → владелец» для доменной модели User.
 *
 * Пользователь — соседний модуль, видимый только через Domain, а доменная
 * модель не умеет строить связи (это забота адаптера). Поэтому связь
 * регистрируется морфологической картой Eloquent: имя `user` у задачи
 * разбирает этот обработчик. Для любой другой модели (например, роли Sanctum)
 * возвращается null, и поиск связи продолжается обычным путём.
 */
final class UserRelationHandler
{
    /**
     * Построить связь «принадлежит» от карточки к владельцу.
     *
     * @param  mixed  $parent  модель, на которой читают связь
     * @return BelongsTo<User, EloquentTaskModel>|null
     */
    public function __invoke(mixed $parent): ?BelongsTo
    {
        if (! $parent instanceof EloquentTaskModel) {
            return null;
        }

        return $parent->belongsTo(User::class, 'user_id');
    }
}
