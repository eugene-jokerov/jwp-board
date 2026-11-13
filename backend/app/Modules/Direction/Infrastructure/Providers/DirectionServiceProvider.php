<?php

namespace App\Modules\Direction\Infrastructure\Providers;

use App\Modules\Direction\Domain\Contracts\DirectionLimits;
use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Infrastructure\Config\ConfigDirectionLimits;
use App\Modules\Direction\Infrastructure\Persistence\EloquentDirectionRepository;
use App\Modules\Direction\Infrastructure\Persistence\HistoryRecordingDirectionRepository;
use App\Modules\History\Domain\Contracts\HistoryRecorder;
use Illuminate\Support\ServiceProvider;

/**
 * Привязка портов модуля Direction к инфраструктурным адаптерам.
 *
 * Порт DirectionRepository собирается как декоратор: снизу — Eloquent-репозиторий,
 * сверху — запись журнала изменений. Прикладной слой о декораторе не знает.
 *
 * Лимит направлений (config('direction.max_per_user')) приходит через порт
 * DirectionLimits: домен не читает конфигурацию сам, а провайдер решает,
 * откуда взято число. Контроллер получает то же значение из того же порта.
 */
final class DirectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DirectionRepository::class, fn ($app): HistoryRecordingDirectionRepository => new HistoryRecordingDirectionRepository(
            new EloquentDirectionRepository,
            $app->make(HistoryRecorder::class),
        ));

        $this->app->bind(DirectionLimits::class, ConfigDirectionLimits::class);
    }

    public function boot(): void
    {
        //
    }
}
