<?php

namespace Tests\Unit\Modules\User\Domain;

use App\Modules\User\Domain\ValueObjects\Password;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Политика длины пароля.
 */
final class PasswordTest extends TestCase
{
    public function test_password_with_min_length_is_accepted(): void
    {
        $password = Password::fromString(str_repeat('a', Password::MIN_LENGTH));

        self::assertSame(str_repeat('a', Password::MIN_LENGTH), $password->value());
    }

    public function test_password_shorter_than_min_length_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Password::fromString(str_repeat('a', Password::MIN_LENGTH - 1));
    }

    public function test_password_longer_than_max_length_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Password::fromString(str_repeat('a', Password::MAX_LENGTH + 1));
    }

    public function test_password_is_not_leaked_in_debug_info(): void
    {
        $password = Password::fromString('secret-password');

        self::assertStringNotContainsString('secret-password', print_r($password->__debugInfo(), true));
    }
}
