<?php

namespace App\Modules\User\Domain\Exceptions;

use RuntimeException;

/**
 * Email уже занят другим пользователем.
 *
 * Доменное исключение: слой Domain не знает, каким HTTP-кодом это отдаётся.
 * Преобразование в 422 выполняется на границе приложения (bootstrap/app.php).
 */
final class EmailAlreadyRegistered extends RuntimeException
{
    public static function forEmail(string $email): self
    {
        return new self(sprintf('Пользователь с email %s уже зарегистрирован.', $email));
    }
}
