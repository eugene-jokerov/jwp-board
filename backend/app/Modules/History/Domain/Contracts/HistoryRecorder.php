<?php

namespace App\Modules\History\Domain\Contracts;

use App\Modules\History\Domain\HistoryEntry;

/**
 * Порт записи журнала изменений.
 *
 * Журнал только пополняется: методов изменения и удаления у порта нет.
 * Записи добавляются в той же транзакции, что и само изменение сущности
 * (декораторы репозиториев вызываются внутри команды, обёрнутой
 * TransactionMiddleware), поэтому частичной истории не бывает.
 */
interface HistoryRecorder
{
    /**
     * Добавить запись журнала.
     */
    public function record(HistoryEntry $entry): void;

    /**
     * Добавить несколько записей одной пачкой (например, по одной на поле при update).
     *
     * @param  list<HistoryEntry>  $entries
     */
    public function recordMany(array $entries): void;
}
