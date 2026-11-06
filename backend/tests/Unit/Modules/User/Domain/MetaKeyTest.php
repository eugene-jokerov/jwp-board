<?php

namespace Tests\Unit\Modules\User\Domain;

use App\Modules\User\Domain\ValueObjects\MetaKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Ключ пользовательской настройки: форма и нормализация.
 */
final class MetaKeyTest extends TestCase
{
    public function test_accepts_namespaced_key(): void
    {
        self::assertSame('board.collapsed_levels', MetaKey::fromString('board.collapsed_levels')->value());
    }

    public function test_trims_surrounding_spaces(): void
    {
        self::assertSame('board.collapsed_columns', MetaKey::fromString('  board.collapsed_columns  ')->value());
    }

    public function test_equals_compares_values(): void
    {
        $key = MetaKey::fromString('board.collapsed_levels');

        self::assertTrue($key->equals(MetaKey::fromString('board.collapsed_levels')));
        self::assertFalse($key->equals(MetaKey::fromString('board.collapsed_columns')));
    }

    public function test_casts_to_string(): void
    {
        self::assertSame('ui.theme', (string) MetaKey::fromString('ui.theme'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeys(): array
    {
        return [
            'пустой' => [''],
            'только пробелы' => ['   '],
            'верхний регистр' => ['Board.CollapsedLevels'],
            'пробел внутри' => ['board collapsed'],
            'дефис' => ['board-collapsed'],
            'точка в начале' => ['.board'],
            'точка в конце' => ['board.'],
            'две точки подряд' => ['board..levels'],
            'кириллица' => ['доска.уровни'],
        ];
    }

    #[DataProvider('invalidKeys')]
    public function test_rejects_invalid_key(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        MetaKey::fromString($value);
    }

    public function test_rejects_too_long_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MetaKey::fromString(str_repeat('a', MetaKey::MAX_LENGTH + 1));
    }
}
