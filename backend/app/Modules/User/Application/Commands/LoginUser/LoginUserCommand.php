<?php

namespace App\Modules\User\Application\Commands\LoginUser;

use App\Modules\User\Domain\ValueObjects\Email;

/**
 * Команда входа: проверить учётные данные и выдать Bearer-токен.
 *
 * Канал клиента в команду не входит: мобильный клиент получает токен,
 * веб-клиент — сессию. Вход по паролю с проверкой учётных данных общий,
 * а выдача доказательства подлинности — дело адаптера канала.
 */
final readonly class LoginUserCommand
{
    public function __construct(
        public Email $email,
        public string $password,
        public string $deviceName,
    ) {}
}
