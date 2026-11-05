<?php

namespace App\Modules\Task\Domain\Services;

use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Domain\Exceptions\InvalidHierarchy;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardType;

/**
 * Доменный сервис иерархии карточки.
 *
 * Проверяет, что набор ссылок соответствует типу карточки и что
 * денормализация не разъезжается с родителем:
 *  - инициатива: direction_id обязателен, initiative_id и project_id — null;
 *  - проект: project_id = null, initiative_id — инициатива своего пользователя,
 *    direction_id наследуется от неё и должен совпасть с переданным;
 *  - задача: initiative_id = null, project_id — проект своего пользователя,
 *    initiative_id и direction_id наследуются от него.
 * Так direction_id карточки всегда равен направлению её «корня», и доска
 * раскладывает карточки по направлениям одним запросом.
 */
final readonly class TaskPlacement
{
    public function __construct(
        private TaskRepository $tasks,
    ) {}

    /**
     * Проверить иерархию и вернуть итоговые значения ссылок карточки.
     *
     * @return array{direction_id: int|null, initiative_id: int|null, project_id: int|null}
     *
     * @throws InvalidHierarchy если набор ссылок недопустим для типа
     */
    public function resolve(CardType $type, int $userId, ?int $directionId, ?int $initiativeId, ?int $projectId): array
    {
        return match ($type) {
            CardType::Initiative => $this->resolveInitiative($userId, $directionId, $initiativeId, $projectId),
            CardType::Project => $this->resolveProject($userId, $directionId, $initiativeId, $projectId),
            CardType::Task => $this->resolveTask($userId, $directionId, $initiativeId, $projectId),
        };
    }

    /**
     * Иерархия карточки, у которой меняются ссылки.
     *
     * Тип карточки неизменяем, поэтому проверяются только ссылки. В запросе
     * приходят уже итоговые значения (клиент передает и унаследованные поля),
     * отсутствующее поле трактуется как null — и это верно: моделируется
     * состояние карточки после обновления.
     *
     * @param  array{direction_id?: int|null, initiative_id?: int|null, project_id?: int|null}  $requested
     * @return array{direction_id: int|null, initiative_id: int|null, project_id: int|null}
     *
     * @throws InvalidHierarchy если набор ссылок недопустим для типа
     */
    public function resolveForUpdate(Task $task, array $requested): array
    {
        $resolved = $this->resolve(
            $task->type,
            (int) $task->user_id,
            $requested['direction_id'] ?? null,
            $requested['initiative_id'] ?? null,
            $requested['project_id'] ?? null,
        );

        if ($resolved['project_id'] === $task->id || $resolved['initiative_id'] === $task->id) {
            throw InvalidHierarchy::selfReference((int) $task->id);
        }

        return $resolved;
    }

    /**
     * @return array{direction_id: int|null, initiative_id: null, project_id: null}
     */
    private function resolveInitiative(int $userId, ?int $directionId, ?int $initiativeId, ?int $projectId): array
    {
        if ($directionId === null) {
            throw InvalidHierarchy::directionRequired(CardType::Initiative);
        }

        if ($initiativeId !== null) {
            throw InvalidHierarchy::fieldNotAllowed(CardType::Initiative, 'initiative_id');
        }

        if ($projectId !== null) {
            throw InvalidHierarchy::fieldNotAllowed(CardType::Initiative, 'project_id');
        }

        if (! $this->tasks->directionExists($userId, $directionId)) {
            throw InvalidHierarchy::parentNotFound('direction_id', $directionId);
        }

        return ['direction_id' => $directionId, 'initiative_id' => null, 'project_id' => null];
    }

    /**
     * @return array{direction_id: int|null, initiative_id: int|null, project_id: null}
     */
    private function resolveProject(int $userId, ?int $directionId, ?int $initiativeId, ?int $projectId): array
    {
        // Проект живёт на тактическом уровне: родитель у него — инициатива, не проект.
        if ($projectId !== null) {
            throw InvalidHierarchy::fieldNotAllowed(CardType::Project, 'project_id');
        }

        // Проект без инициативы допустим — тогда и направления у него нет.
        if ($initiativeId === null) {
            if ($directionId !== null) {
                throw InvalidHierarchy::directionIsInherited(CardType::Project);
            }

            return ['direction_id' => null, 'initiative_id' => null, 'project_id' => null];
        }

        $initiative = $this->tasks->findParent($userId, $initiativeId, CardType::Initiative);

        if ($initiative === null) {
            throw InvalidHierarchy::parentNotFound('initiative_id', $initiativeId);
        }

        $inherited = $initiative->direction_id;

        if ($directionId !== null && $directionId !== $inherited) {
            throw InvalidHierarchy::fieldMismatch('direction_id', $inherited, $directionId);
        }

        return ['direction_id' => $inherited, 'initiative_id' => $initiativeId, 'project_id' => null];
    }

    /**
     * @return array{direction_id: int|null, initiative_id: int|null, project_id: int|null}
     */
    private function resolveTask(int $userId, ?int $directionId, ?int $initiativeId, ?int $projectId): array
    {
        // Задача без проекта допустима — тогда ни инициативы, ни направления у неё нет.
        if ($projectId === null) {
            if ($initiativeId !== null) {
                throw InvalidHierarchy::fieldNotAllowed(CardType::Task, 'initiative_id');
            }

            if ($directionId !== null) {
                throw InvalidHierarchy::directionIsInherited(CardType::Task);
            }

            return ['direction_id' => null, 'initiative_id' => null, 'project_id' => null];
        }

        $project = $this->tasks->findParent($userId, $projectId, CardType::Project);

        if ($project === null) {
            throw InvalidHierarchy::parentNotFound('project_id', $projectId);
        }

        // Инициатива и направление задачи — это инициатива и направление её проекта:
        // иначе денормализация перестанет совпадать с иерархией.
        if ($initiativeId !== null && $initiativeId !== $project->initiative_id) {
            throw InvalidHierarchy::fieldMismatch('initiative_id', $project->initiative_id, $initiativeId);
        }

        if ($directionId !== null && $directionId !== $project->direction_id) {
            throw InvalidHierarchy::fieldMismatch('direction_id', $project->direction_id, $directionId);
        }

        return [
            'direction_id' => $project->direction_id,
            'initiative_id' => $project->initiative_id,
            'project_id' => $projectId,
        ];
    }
}
