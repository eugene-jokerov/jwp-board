<?php

namespace App\Modules\Task\Application\Services;

use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\InvalidHierarchy;
use App\Modules\Task\Domain\Services\TaskPlacement;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskDeadline;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskSlug;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;

/**
 * Прикладной сервис создания карточки.
 *
 * Порядок шагов и есть корректность: сначала домен вычисляет итоговые ссылки
 * (TaskPlacement защищает денормализацию), затем карточка создаётся с уже
 * валидной иерархией. Создание карточки сразу в колонке «В работе» разрешено:
 * она становится ребёнком в работе и тем самым блокирует родителя.
 */
final readonly class TaskFactory
{
    public function __construct(
        private TaskRepository $tasks,
        private TaskPlacement $placement,
    ) {}

    /**
     * @throws InvalidHierarchy если набор ссылок недопустим для типа
     */
    public function create(
        int $userId,
        CardType $type,
        CardColumn $column,
        CardPriority $priority,
        TaskTitle $title,
        ?TaskDescription $description,
        ?int $directionId,
        ?int $initiativeId,
        ?int $projectId,
        ?TaskDeadline $deadline,
        ?TaskSlug $slug = null,
        bool $idea = false,
    ): Task {
        $place = $this->placement->resolve($type, $userId, $directionId, $initiativeId, $projectId);

        return $this->tasks->create($userId, [
            'title' => $title->value(),
            'slug' => $slug?->value(),
            'description' => $description?->value(),
            'deadline' => $deadline?->value(),
            'type' => $type,
            'column' => $column,
            // Приоритет не зависит от колонки: карточка создаётся с тем,
            // что выбрали в форме (или со средним по умолчанию).
            'priority' => $priority,
            // Степень проработки — свойство беклога. Карточка, созданная сразу
            // в работе или закрытой колонке, идеей не бывает.
            'idea' => $column->acceptsIdeas() && $idea,
            'direction_id' => $place['direction_id'],
            'initiative_id' => $place['initiative_id'],
            'project_id' => $place['project_id'],
        ]);
    }
}
