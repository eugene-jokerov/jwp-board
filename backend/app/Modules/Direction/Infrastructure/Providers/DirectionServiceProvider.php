<?php

namespace App\Modules\Direction\Infrastructure\Providers;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Infrastructure\Persistence\EloquentDirectionRepository;
use App\Modules\Direction\Infrastructure\Persistence\HistoryRecordingDirectionRepository;
use App\Modules\History\Domain\Contracts\HistoryRecorder;
use Illuminate\Support\ServiceProvider;

/**
 * Привязка портов модуля Direction к инфраструктурным адаптерам.
 *
 * Порт DirectionRepository собирается как декоратор: снизу — Eloquent-репозиторий,
 * сверху — запись журнала изменений. Прикладной слой о декораторе не знает.
 * DirectionManager зависит только от порта и разрешается автовайрингом.
 */
final class DirectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DirectionRepository::class, fn ($app): HistoryRecordingDirectionRepository => new HistoryRecordingDirectionRepository(
            new EloquentDirectionRepository,
            $app->make(HistoryRecorder::class),
        ));
    }

    public function boot(): void
    {
        //
    }
}
