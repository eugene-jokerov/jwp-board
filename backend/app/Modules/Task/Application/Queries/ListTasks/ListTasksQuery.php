<?php

namespace App\Modules\Task\Application\Queries\ListTasks;

use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardType;

/**
 * Запрос списка карточек пользователя.
 *
 * Фильтры необязательны: доска запрашивает уровень (type), колонку и родителя,
 * а клиент может уточнить выборку по direction_id.
 *
 * `limit` — размер страницы; null означает «весь список» (так грузятся
 * беклог и работа, а также любая явно отфильтрованная выборка). `offset`
 * считается только вместе с `limit`: по нему приходят следующие страницы
 * закрытых колонок («показать ещё»).
 * `order` — направление по идентификатору: закрытые карточки показываем
 * свежими сверху, рабочие — в порядке создания.
 *
 * `initial` — первичная загрузка доски (клиент не передал ни колонку, ни
 * страницу). В этом режиме закрытые колонки (CardColumn::isClosed) отдаются
 * порцией CardColumn::CLOSED_CARDS_PER_LEVEL на каждую пару «уровень +
 * колонка», а открытые — целиком.
 */
final readonly class ListTasksQuery
{
    public function __construct(
        public int $userId,
        public ?CardType $type = null,
        public ?CardColumn $column = null,
        public ?int $directionId = null,
        public ?int $initiativeId = null,
        public ?int $projectId = null,
        public ?int $limit = null,
        public int $offset = 0,
        public string $order = 'asc',
        public bool $initial = false,
    ) {}
}
