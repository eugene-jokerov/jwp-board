<?php

namespace App\Modules\User\Infrastructure\Persistence;

use App\Modules\User\Domain\Contracts\UserMetaRepository;
use App\Modules\User\Domain\UserMeta;
use App\Modules\User\Domain\ValueObjects\MetaKey;

/**
 * Eloquent-реализация порта UserMetaRepository.
 *
 * Сохранение — UPSERT по уникальной паре (user_id, meta_key): повторная запись
 * той же настройки не плодит строки, поэтому клиенту не нужно знать, была ли
 * настройка сохранена раньше.
 */
final class EloquentUserMetaRepository implements UserMetaRepository
{
    public function allForUser(int $userId): array
    {
        /** @var array<string, string|null> $values */
        $values = UserMeta::query()
            ->where('user_id', $userId)
            ->orderBy('meta_key')
            ->pluck('meta_value', 'meta_key')
            ->all();

        return $values;
    }

    public function findForUser(int $userId, MetaKey $key): ?UserMeta
    {
        return UserMeta::query()
            ->where('user_id', $userId)
            ->where('meta_key', $key->value())
            ->first();
    }

    public function put(int $userId, MetaKey $key, ?string $value): UserMeta
    {
        return UserMeta::query()->updateOrCreate(
            ['user_id' => $userId, 'meta_key' => $key->value()],
            ['meta_value' => $value],
        );
    }

    public function delete(UserMeta $meta): void
    {
        $meta->delete();
    }
}
