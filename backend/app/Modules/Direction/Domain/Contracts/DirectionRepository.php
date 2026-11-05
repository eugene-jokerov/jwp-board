<?php

namespace App\Modules\Direction\Domain\Contracts;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\Exceptions\DirectionNotFound;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;

/**
 * Порт доступа к хранилищу направлений.
 *
 * Все методы работают в границах пользователя: чужое направление снаружи
 * неотличимо от несуществующего (DirectionNotFound → 404).
 * Реализация — Infrastructure\Persistence\EloquentDirectionRepository;
 * запись истории изменения — в декораторе HistoryRecordingDirectionRepository.
 */
interface DirectionRepository
{
    /**
     * Направление пользователя по идентификатору.
     *
     * @throws DirectionNotFound если записи нет или она принадлежит другому пользователю
     */
    public function findForUser(int $userId, int $id): Direction;

    /**
     * Все направления пользователя в порядке создания.
     *
     * @return list<Direction>
     */
    public function allForUser(int $userId): array;

    /**
     * Создать направление.
     *
     * @throws \InvalidArgumentException если значения не проходят value object'ы
     */
    public function create(int $userId, DirectionTitle $title, DirectionColor $color): Direction;

    /**
     * Сохранить изменения направления. Незаданные поля (null) не меняются.
     */
    public function update(Direction $direction, ?DirectionTitle $title, ?DirectionColor $color): Direction;

    /**
     * Удалить направление.
     */
    public function delete(Direction $direction): void;

    /**
     * Количество карточек, привязанных к направлению.
     */
    public function countCards(Direction $direction): int;
}
