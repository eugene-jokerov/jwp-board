<?php

namespace Database\Seeders;

use App\Modules\Direction\Domain\Direction;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\User\Domain\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Демо-данные для локальной разработки.
 *
 * Пользователь demo@jwp-board.local / password с доской, повторяющей набор
 * демо-версии фронта: три направления, инициативы в разных колонках, проекты
 * под инициативами «в работе» и задачи под проектами «в работе». Отдельно есть
 * проекты без инициативы и задачи без проекта — у них направления нет.
 *
 * Ссылки иерархии заполняются вручную, как это делает доменный сервис
 * TaskPlacement: direction_id у проекта и задачи наследуется от родителя.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Заполнить базу демо-данными.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'demo@jwp-board.local'],
            [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        // Сид идемпотентен: повторный запуск не плодит вторую доску.
        if (Task::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        $directions = [
            'c1' => $this->direction($user, 'Направление 1', DirectionColor::Blue),
            'c2' => $this->direction($user, 'Направление 2', DirectionColor::Amber),
            'c3' => $this->direction($user, 'Направление 3', DirectionColor::Green),
        ];

        $initiatives = [
            'init-1' => $this->initiative($user, $directions['c1'], 'Инициатива 1.1', CardColumn::InProgress),
            'init-2' => $this->initiative($user, $directions['c1'], 'Инициатива 1.2', CardColumn::Backlog),
            'init-3' => $this->initiative($user, $directions['c1'], 'Инициатива 1.3', CardColumn::Complete),
            'init-4' => $this->initiative($user, $directions['c2'], 'Инициатива 2.1', CardColumn::InProgress),
            'init-5' => $this->initiative($user, $directions['c2'], 'Инициатива 2.2', CardColumn::InProgress),
            'init-6' => $this->initiative($user, $directions['c2'], 'Инициатива 2.3', CardColumn::Canceled),
            'init-7' => $this->initiative($user, $directions['c3'], 'Инициатива 3.1', CardColumn::InProgress),
            'init-8' => $this->initiative($user, $directions['c3'], 'Инициатива 3.2', CardColumn::Backlog),
        ];

        $projects = [
            'project-1' => $this->project($user, $initiatives['init-1'], 'Проект 1.1.1', CardColumn::InProgress),
            'project-2' => $this->project($user, $initiatives['init-1'], 'Проект 1.1.2', CardColumn::Complete),
            'project-3' => $this->project($user, $initiatives['init-4'], 'Проект 2.1.1', CardColumn::InProgress),
            'project-4' => $this->project($user, $initiatives['init-5'], 'Проект 2.2.1', CardColumn::Backlog),
            'project-5' => $this->project($user, $initiatives['init-7'], 'Проект 3.1.1', CardColumn::InProgress),
            'project-6' => $this->project($user, $initiatives['init-7'], 'Проект 3.1.2', CardColumn::InProgress),
            'project-7' => $this->project($user, null, 'Проект без инициативы 1', CardColumn::Backlog),
            'project-8' => $this->project($user, null, 'Проект без инициативы 2', CardColumn::InProgress),
        ];

        $this->task($user, $projects['project-1'], 'Задача 1.1.1.1', CardColumn::InProgress);
        $this->task($user, $projects['project-1'], 'Задача 1.1.1.2', CardColumn::Backlog);
        $this->task($user, $projects['project-3'], 'Задача 2.1.1.1', CardColumn::Complete);
        $this->task($user, $projects['project-5'], 'Задача 3.1.1.1', CardColumn::InProgress);
        $this->task($user, $projects['project-6'], 'Задача 3.1.2.1', CardColumn::Backlog);
        $this->task($user, null, 'Задача без проекта 1', CardColumn::InProgress);
        $this->task($user, null, 'Задача без проекта 2', CardColumn::Backlog);
        $this->task($user, null, 'Задача без проекта 3', CardColumn::Canceled);
    }

    /**
     * Направление демо-пользователя.
     */
    private function direction(User $user, string $title, DirectionColor $color): Direction
    {
        return Direction::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'color' => $color->value,
        ]);
    }

    /**
     * Инициатива: направление обязательно, ссылок на другие карточки нет.
     */
    private function initiative(User $user, Direction $direction, string $title, CardColumn $column): Task
    {
        return Task::query()->create([
            'user_id' => $user->id,
            'direction_id' => $direction->id,
            'initiative_id' => null,
            'project_id' => null,
            'type' => 'initiative',
            'column' => $column->value,
            'title' => $title,
        ]);
    }

    /**
     * Проект: без инициативы направления не имеет.
     */
    private function project(User $user, ?Task $initiative, string $title, CardColumn $column): Task
    {
        return Task::query()->create([
            'user_id' => $user->id,
            'direction_id' => $initiative?->direction_id,
            'initiative_id' => $initiative?->id,
            'project_id' => null,
            'type' => 'project',
            'column' => $column->value,
            'title' => $title,
        ]);
    }

    /**
     * Задача: наследует инициативу и направление своего проекта.
     */
    private function task(User $user, ?Task $project, string $title, CardColumn $column): Task
    {
        return Task::query()->create([
            'user_id' => $user->id,
            'direction_id' => $project?->direction_id,
            'initiative_id' => $project?->initiative_id,
            'project_id' => $project?->id,
            'type' => 'task',
            'column' => $column->value,
            'title' => $title,
        ]);
    }
}
