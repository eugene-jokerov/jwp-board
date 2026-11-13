<?php

namespace App\Modules\Task\Infrastructure\Providers;

use App\Modules\History\Domain\Contracts\HistoryRecorder;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Infrastructure\Persistence\DirectionRelationHandler;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskRepository;
use App\Modules\Task\Infrastructure\Persistence\HistoryRecordingTaskRepository;
use App\Modules\Task\Infrastructure\Persistence\UserRelationHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

/**
 * Привязки модуля Task.
 *
 * Порт TaskRepository собирается как декоратор: снизу — Eloquent-репозиторий,
 * сверху — запись журнала изменений. Прикладной слой о декораторе не знает.
 *
 * Связи карточки с владельцем и направлением ведут в соседние модули, поэтому
 * здесь же они регистрируются в морфологической карте Eloquent: адаптер Task
 * не тянет модели их хранилищ и остаётся зависимостью от их Domain.
 */
final class TaskServiceProvider extends ServiceProvider
{
    /**
     * Регистрация портов модуля.
     */
    public function register(): void
    {
        $this->app->bind(TaskRepository::class, fn ($app): HistoryRecordingTaskRepository => new HistoryRecordingTaskRepository(
            new EloquentTaskRepository,
            $app->make(HistoryRecorder::class),
        ));
    }

    /**
     * Регистрация связей модуля.
     */
    public function boot(): void
    {
        $direction = new DirectionRelationHandler;
        Model::resolveRelationUsing('direction', $direction(...));
        $user = new UserRelationHandler;
        Model::resolveRelationUsing('user', $user(...));
    }
}
