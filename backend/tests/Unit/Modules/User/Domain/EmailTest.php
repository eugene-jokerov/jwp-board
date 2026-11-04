<?php

namespace Tests\Unit\Modules\User\Domain;

use App\Modules\User\Domain\ValueObjects\Email;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Email как value object: нормализация и валидация формата.
 */
final class EmailTest extends TestCase
{
    public function test_email_is_trimmed_and_lowercased(): void
    {
        $email = Email::fromString('  User.Name@Example.COM  ');

        self::assertSame('user.name@example.com', $email->value());
        self::assertSame('user.name@example.com', (string) $email);
    }

    public function test_emails_are_compared_case_insensitively(): void
    {
        self::assertTrue(
            Email::fromString('USER@example.com')->equals(Email::fromString('user@example.com')),
        );
    }

    public function test_different_emails_are_not_equal(): void
    {
        self::assertFalse(
            Email::fromString('one@example.com')->equals(Email::fromString('two@example.com')),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidEmails(): array
    {
        return [
            'пустая строка' => [''],
            'только пробелы' => ['   '],
            'без @' => ['not-an-email'],
            'без домена' => ['user@'],
            'слишком длинный' => [str_repeat('a', 250).'@example.com'],
        ];
    }

    #[DataProvider('invalidEmails')]
    public function test_invalid_email_throws_exception(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        Email::fromString($value);
    }
}
