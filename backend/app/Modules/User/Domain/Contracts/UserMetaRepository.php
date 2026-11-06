<?php

namespace App\Modules\User\Domain\Contracts;

use App\Modules\User\Domain\UserMeta;
use App\Modules\User\Domain\ValueObjects\MetaKey;

/**
 * Порт доступа к пользовательским настройкам.
 *
 * Все методы работают в границах пользователя: чужую настройку снаружи
 * не достать, потому что выборка всегда идёт по user_id.
 * Реализация — Infrastructure\Persistence\EloquentUserMetaRepository.
 */
interface UserMetaRepository
{
    /**
     * Все настройки пользователя: ключ => сырое значение.
     *
     * Значение хранится текстом; разбор формата — забота прикладного слоя.
     *
     * @return array<string, string|null>
     */
    public function allForUser(int $userId): array;

    /**
     * Настройка пользователя по ключу; null — настройка не сохранена.
     */
    public function findForUser(int $userId, MetaKey $key): ?UserMeta;

    /**
     * Сохранить значение настройки (insert или update по паре «пользователь + ключ»).
     */
    public function put(int $userId, MetaKey $key, ?string $value): UserMeta;

    /**
     * Удалить настройку.
     */
    public function delete(UserMeta $meta): void;
}
