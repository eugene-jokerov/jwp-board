<?php

namespace Tests\Unit\Shared\Application\Bus\Middleware;

use App\Modules\User\Domain\Exceptions\ReadOnlyMode;
use App\Modules\User\Domain\User;
use App\Shared\Application\Bus\Middleware\DenyReadOnlyCommands;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Unit-тест пайпа шины `DenyReadOnlyCommands`.
 *
 * Пайп читает пользователя из Auth, поэтому HTTP-контекст ему не нужен:
 * достаточно Auth::login(). Feature-тест ReadOnlyCommandPipesTest проверяет
 * поведение через настоящую шину, а здесь изолированно — ровно два решения:
 * вызвать следующий слой или бросить ReadOnlyMode.
 */
final class DenyReadOnlyCommandsTest extends TestCase
{
    /**
     * Выполнить пайп и вернуть «дошло ли до следующего слоя».
     *
     * @throws ReadOnlyMode если пайп запретил команду
     */
    private function passes(object $command): bool
    {
        $called = false;

        (new DenyReadOnlyCommands)($command, function () use (&$called): string {
            $called = true;

            return 'handled';
        });

        return $called;
    }

    public function test_guest_is_passed_through(): void
    {
        Auth::forgetGuards();

        self::assertTrue($this->passes(new DenyReadOnlyCommandsTestCommand));
    }

    public function test_regular_user_is_passed_through(): void
    {
        Auth::login(User::factory()->make(['readonly' => false]));

        self::assertTrue($this->passes(new DenyReadOnlyCommandsTestCommand));
    }

    /**
     * Read-only пользователь: следующий слой (хендлер) не вызывается вовсе,
     * поэтому сценарий не успевает ничего изменить.
     */
    public function test_handler_is_not_called_for_readonly_user(): void
    {
        Auth::login(User::factory()->make(['readonly' => true]));

        $called = false;

        try {
            (new DenyReadOnlyCommands)(new DenyReadOnlyCommandsTestCommand, function () use (&$called): string {
                $called = true;

                return 'handled';
            });

            self::fail('Пайп должен был бросить ReadOnlyMode');
        } catch (ReadOnlyMode $e) {
            self::assertSame(ReadOnlyMode::denied()->getMessage(), $e->getMessage());
        }

        self::assertFalse($called, 'Хендлер не должен вызываться при отказе');
    }

    /**
     * Администратор с флагом readonly: пайп смотрит только на флаг, но команды
     * админки вообще не получают этот пайп (см. COMMAND_PIPES). Здесь важно,
     * что сам пайп не делает исключения «по роли» — решение принимает карта.
     */
    public function test_pipe_does_not_check_admin_flag(): void
    {
        Auth::login(User::factory()->make(['readonly' => true, 'is_admin' => true]));

        $this->expectException(ReadOnlyMode::class);

        $this->passes(new DenyReadOnlyCommandsTestCommand);
    }
}

/**
 * Тестовая команда: пайпу важно только, что следующий слой вызван.
 */
final readonly class DenyReadOnlyCommandsTestCommand {}
