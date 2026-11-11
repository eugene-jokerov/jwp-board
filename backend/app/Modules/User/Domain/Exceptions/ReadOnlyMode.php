<?php

namespace App\Modules\User\Domain\Exceptions;

use RuntimeException;

/**
 * Учётная запись работает в режиме только для чтения.
 *
 * Доменное исключение: слой Domain не знает, каким HTTP-кодом это отдаётся.
 * Преобразование в 403 выполняется на границе приложения (bootstrap/app.php).
 *
 * Бросается до выполнения сценария — и в HTTP-слое (DenyReadOnlyWrites), и на
 * шине команд (DenyReadOnlyCommands): правило одно, а точек входа две.
 */
final class ReadOnlyMode extends RuntimeException
{
    public static function denied(): self
    {
        return new self('Учётная запись работает в режиме только для чтения: изменения недоступны.');
    }
}
