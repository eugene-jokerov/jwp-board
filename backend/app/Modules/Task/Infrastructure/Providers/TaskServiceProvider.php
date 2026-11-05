<?php

namespace App\Modules\Task\Infrastructure\Providers;

use App\Modules\History\Domain\Contracts\HistoryRecorder;
use App\Modules\Task\Application\Services\TaskEditor;
use App\Modules\Task\Application\Services\TaskFactory;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskRepository;
use App\Modules\Task\Infrastructure\Persistence\HistoryRecordingTaskRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Привязка портов модуля Task к инфраструктурным адаптерам.
 *
 * Порт TaskRepository собирается как декоратор: снизу Eloquent-репозиторий,
 * сверху запись журнала изменений. Прикладной слой о декораторе не знает.
 */
final class TaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaskRepository::class, fn ($app): HistoryRecordingTaskRepository => new HistoryRecordingTaskRepository(
            new EloquentTaskRepository,
            $app->make(HistoryRecorder::class),
        ));

        $this->app->bind(TaskFactory::class);
        $this->app->bind(TaskEditor::class);
    }

    public function boot(): void
    {
        //
    }
}
