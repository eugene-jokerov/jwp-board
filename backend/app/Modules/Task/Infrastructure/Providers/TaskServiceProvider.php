<?php

namespace App\Modules\Task\Infrastructure\Providers;

use App\Modules\History\Domain\Contracts\HistoryRecorder;
use App\Modules\Task\Domain\Contracts\TaskRepository;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskRepository;
use App\Modules\Task\Infrastructure\Persistence\HistoryRecordingTaskRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Привязка портов модуля Task к инфраструктурным адаптерам.
 *
 * Порт TaskRepository собирается как декоратор: снизу Eloquent-репозиторий,
 * сверху запись журнала изменений. Прикладной слой о декораторе не знает.
 * Прикладные сервисы без зависимостей от запроса разрешаются автовайрингом —
 * их привязки не нужны.
 */
final class TaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaskRepository::class, fn ($app): HistoryRecordingTaskRepository => new HistoryRecordingTaskRepository(
            new EloquentTaskRepository,
            $app->make(HistoryRecorder::class),
        ));
    }

    public function boot(): void
    {
        //
    }
}
