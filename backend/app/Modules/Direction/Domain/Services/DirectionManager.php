<?php

namespace App\Modules\Direction\Domain\Services;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\Exceptions\DirectionHasCards;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;

/**
 * Доменный сервис направлений: правила, которым подчиняется изменение направлений.
 *
 * Здесь живёт единственный инвариант удаления: направление удаляется только
 * пустым. Цвет и название проверяют value object'ы, поэтому сервис остаётся
 * тонким и работает через порт репозитория, не зная про Eloquent.
 */
final readonly class DirectionManager
{
    public function __construct(
        private DirectionRepository $directions,
    ) {}

    /**
     * Создать направление пользователя.
     */
    public function create(int $userId, DirectionTitle $title, DirectionColor $color): Direction
    {
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
