<?php

namespace App\Modules\Direction\Domain\Services;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\Exceptions\DirectionHasCards;
use App\Modules\Direction\Domain\Exceptions\DirectionLimitReached;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;

/**
 * Доменный сервис направлений: правила, которым подчиняется изменение направлений.
 *
 * Здесь живут инварианты: направлений у пользователя не больше $maxPerUser
 * (проверка при создании) и направление удаляется только пустым. Цвет и название
 * проверяют value object'ы, поэтому сервис остаётся тонким и работает через порт
 * репозитория, не зная про Eloquent.
 *
 * Лимит приходит снаружи (config('direction.max_per_user') через провайдер
 * модуля): домен не читает конфиг сам, а число остаётся в одном месте — его же
 * API отдаёт клиенту в meta списка направлений.
 */
final readonly class DirectionManager
{
    /**
     * @param  int  $maxPerUser  максимум направлений на пользователя (положительное число)
     */
    public function __construct(
        private DirectionRepository $directions,
        private int $maxPerUser,
    ) {}

    /**
     * Создать направление пользователя.
     *
     * @throws DirectionLimitReached если у пользователя уже $maxPerUser направлений
     */
    public function create(int $userId, DirectionTitle $title, DirectionColor $color): Direction
    {
        // Лимит проверяется здесь, а не в валидации запроса: это правило
        // предметной области, и оно должно действовать на любом пути создания
        // направления, а не только через HTTP.
        if ($this->directions->countForUser($userId) >= $this->maxPerUser) {
            throw DirectionLimitReached::withLimit($this->maxPerUser);
        }

        return $this->directions->create($userId, $title, $color);
    }

    /**
     * Изменить направление: переданные поля заменяются, остальные остаются как были.
     */
    public function update(Direction $direction, ?DirectionTitle $title, ?DirectionColor $color): Direction
    {
        if ($title === null && $color === null) {
            return $direction;
        }

        return $this->directions->update($direction, $title, $color);
    }

    /**
     * Удалить направление.
     *
     * @throws DirectionHasCards если в направлении есть карточки (любой колонки)
     */
    public function delete(Direction $direction): void
    {
        $cards = $this->directions->countCards($direction);

        if ($cards > 0) {
            throw DirectionHasCards::withCards((int) $direction->id, $cards);
        }

        $this->directions->delete($direction);
    }
}
