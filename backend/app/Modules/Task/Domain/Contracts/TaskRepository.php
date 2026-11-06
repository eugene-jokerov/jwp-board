<?php

namespace App\Modules\Task\Domain\Contracts;

use App\Modules\Task\Domain\Exceptions\TaskNotFound;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;

/**
 * Порт доступа к хранилищу карточек.
 *
 * Все методы работают в границах пользователя: чужая карточка снаружи
 * неотличима от несуществующей (TaskNotFound → 404).
 */
interface TaskRepository
{
    /**
     * Карточка пользователя по идентификатору.
     *
     * @throws TaskNotFound если записи нет или она принадлежит другому пользователю
     */
    public function findForUser(int $userId, int $id): Task;

    /**
     * Карточки пользователя с фильтрами, в порядке создания.
     *
     * Фильтр с null не применяется; числовые фильтры применяются только
     * при значении > 0 (0 и отрицательные не сужают выборку).
     *
     * @param  array{type?: CardType|null, column?: CardColumn|null, direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $filters
     * @return list<Task>
     */
    public function listForUser(int $userId, array $filters = []): array;

    /**
     * Дочерние карточки в работе: проекты инициативы или задачи проекта.
     */
    public function countChildrenInProgress(Task $task): int;

    /**
     * Количество детей в работе для пачки карточек — одним запросом.
     *
     * Список карточек спрашивает признак для каждой строки, поэтому пакетный
     * метод нужен, чтобы не делать count() на карточку.
     *
     * @param  list<Task>  $tasks
     * @return array<int, int> id карточки => количество её детей в работе
     */
    public function countChildrenInProgressFor(array $tasks): array;

    /**
     * Количество дочерних карточек в любой колонке.
     */
    public function countChildren(Task $task): int;

    /**
     * Родительская карточка пользователя нужного типа.
     */
    public function findParent(int $userId, int $id, CardType $type): ?Task;

    /**
     * Создать карточку с уже проверенной иерархией.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(int $userId, array $attributes): Task;

    /**
     * Сохранить изменения карточки; передаются только изменившиеся значения.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(Task $task, array $changes): Task;

    /**
     * Удалить карточку.
     */
    public function delete(Task $task): void;

    /**
     * Направление пользователя существует.
     */
    public function directionExists(int $userId, int $directionId): bool;
}
