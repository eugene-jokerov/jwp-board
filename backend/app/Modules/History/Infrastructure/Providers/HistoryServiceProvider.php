<?php

namespace App\Modules\History\Infrastructure\Providers;

use App\Modules\History\Domain\Contracts\HistoryRecorder;
use App\Modules\History\Infrastructure\Persistence\EloquentHistoryRecorder;
use Illuminate\Support\ServiceProvider;

/**
 * Привязка порта журнала изменений к Eloquent-реализации.
 */
final class HistoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HistoryRecorder::class, EloquentHistoryRecorder::class);
    }

    public function boot(): void
    {
        //
    }
}
