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
     * `order` — направление сортировки по идентификатору (новые карточки — `desc`),
     * `limit`/`offset` — страница выборки для «показать ещё».
     *
     * @param  array{type?: CardType|null, column?: CardColumn|null, direction_id?: int|null, initiative_id?: int|null, project_id?: int|null, order?: 'asc'|'desc', limit?: int|null, offset?: int|null}  $filters
     * @return list<Task>
     */
    public function listForUser(int $userId, array $filters = []): array;

    /**
     * Карточки для первичной загрузки доски.
     *
     * Открытые колонки (Беклог, В работе) отдаются целиком, закрытые (Готово,
     * Отмена) — не больше $perLevelLimit карточек на каждую пару «уровень +
     * колонка». Лимит на пару, а не на колонку: у каждого уровня своя лента
     * «показать ещё», и карточки одного уровня не должны съедать лимит другого.
     * Внутри пары карточки идут от новых к старым — на доске свежие сверху.
     *
     * @param  array{type?: CardType|null, column?: CardColumn|null, direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $filters
     * @param  int  $perLevelLimit  максимальное число закрытых карточек на пару «уровень + колонка»
     * @return list<Task>
     */
    public function listInitialForUser(int $userId, array $filters, int $perLevelLimit): array;

    /**
     * Сколько всего карточек подходит под фильтры — без учёта страницы.
     *
     * Нужен клиенту, чтобы знать, есть ли ещё записи для «показать ещё»:
     * фильтры те же, что у listForUser, но limit/offset/order игнорируются.
     *
     * @param  array{type?: CardType|null, column?: CardColumn|null, direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $filters
     */
    public function countForUser(int $userId, array $filters = []): int;

    /**
     * Сколько карточек подходит под фильтры в разрезе колонок — одним запросом.
     *
     * Нужен клиенту для бейджа колонки: число включает карточки всех уровней,
     * в том числе открытых колонок, которые приходят целиком.
     *
     * @param  array{type?: CardType|null, column?: CardColumn|null, direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $filters
     * @return array<string, int> ключ колонки => всего карточек
     */
    public function countByColumnForUser(int $userId, array $filters = []): array;

    /**
     * Сколько закрытых карточек подходит под фильтры в разрезе пар
     * «уровень + колонка» — одним запросом.
     *
     * Счётчики нужны первичной загрузке: закрытые карточки урезаны, а клиент
     * показывает полное число карточек пары и решает, нужна ли кнопка
     * «показать ещё». Пары без карточек в результат не попадают.
     *
     * @param  array{type?: CardType|null, column?: CardColumn|null, direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $filters
     * @return array<string, array<string, int>> ключ колонки => [тип карточки => всего карточек]
     */
    public function countClosedByLevelForUser(int $userId, array $filters = []): array;

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
