<?php

namespace App\Shared\Application\Bus\DependencyInjection;

/**
 * Порт регистрации сценариев в шине.
 *
 * Общая инфраструктура (BusServiceProvider) и модули не знают друг о друге:
 * провайдер шины собирает список модулей из config/bus.php и опрашивает каждый
 * через этот интерфейс, а модуль сам объявляет свои команды, запросы и пайпы.
 * Так карта сценариев остаётся у модуля, а шина — общей.
 */
interface BusRegistry
{
    /**
     * Карта команд модуля: команда → хендлер.
     *
     * @return array<class-string, class-string>
     */
    public function commands(): array;

    /**
     * Карта запросов модуля: запрос → хендлер.
     *
     * @return array<class-string, class-string>
     */
    public function queries(): array;

    /**
     * Какие команды модуля выполняются без транзакции и запрета записи.
     *
     * По умолчанию шина даёт каждой команде WRITE_PIPES (транзакция плюс
     * запрет записи для read-only пользователя); этот список — исключения.
     *
     * @return list<class-string>
     */
    public function plainCommands(): array;
}
