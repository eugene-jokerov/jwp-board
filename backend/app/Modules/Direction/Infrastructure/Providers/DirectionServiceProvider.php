<?php

namespace App\Modules\Direction\Infrastructure\Providers;

use App\Modules\Direction\Domain\Contracts\DirectionRepository;
use App\Modules\Direction\Domain\Services\DirectionManager;
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
 * DirectionManager зависит от порта и лимита направлений: лимит — параметр
 * конфигурации (config('direction.max_per_user')), поэтому сервис собирается
 * здесь явно, а не автовайрингом: примитив int из контейнера не разрешить.
 */
final class DirectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DirectionRepository::class, fn ($app): HistoryRecordingDirectionRepository => new HistoryRecordingDirectionRepository(
            new EloquentDirectionRepository,
            $app->make(HistoryRecorder::class),
        ));

        $this->app->bind(DirectionManager::class, fn ($app): DirectionManager => new DirectionManager(
            $app->make(DirectionRepository::class),
            $this->maxPerUser(),
        ));
    }

    public function boot(): void
    {
        //
    }

    /**
     * Лимит направлений на пользователя из конфигурации.
     *
     * config() отдаёт mixed, а конфиг уже нормализовал значение до
     * положительного int (config/direction.php), поэтому приведение безопасно.
     */
    private function maxPerUser(): int
    {
        return (int) config('direction.max_per_user');
    }
}
