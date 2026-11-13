<?php

namespace Tests\Feature\Console;

use App\Modules\Direction\Application\Commands\CreateDirection\CreateDirectionCommand;
use App\Modules\Direction\Domain\ValueObjects\DirectionColor;
use App\Modules\Direction\Domain\ValueObjects\DirectionTitle;
use App\Modules\User\Domain\Exceptions\ReadOnlyMode;
use App\Modules\User\Infrastructure\Persistence\EloquentUserModel;
use App\Shared\Application\Bus\CommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Консольный вход `tinker-as`: вне HTTP правило «только чтение» действует,
 * только если исполнитель вошёл в гвард `web` до dispatch.
 *
 * Без консольного входа Auth::user() возвращает null, и DenyReadOnlyCommands
 * пропускает команду — это граница правила, а не ошибка пайпа.
 */
final class TinkerAsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_rejects_unknown_user(): void
    {
        $this->artisan('tinker-as', ['user' => 'missing@example.com'])
            ->expectsOutput('Пользователь не найден: missing@example.com')
            ->assertSuccessful();

        self::assertNull(Auth::user());
    }

    public function test_command_logs_user_in_and_marks_readonly(): void
    {
        $user = EloquentUserModel::factory()->readonly()->create();

        // Диалог tinker интерактивен: команда входит в пользователя и передаёт
        // управление дальше. Здесь проверяется только шаг входа.
        $this->artisan('tinker-as', ['user' => (string) $user->getKey()])
            ->expectsOutputToContain('readonly: да')
            ->run();

        self::assertSame($user->getKey(), Auth::id());

        $this->expectException(ReadOnlyMode::class);

        app(CommandBus::class)->dispatch(new CreateDirectionCommand(
            (int) $user->getKey(),
            DirectionTitle::fromString('Здоровье'),
            DirectionColor::cases()[0],
        ));
    }

    public function test_command_accepts_email(): void
    {
        $user = EloquentUserModel::factory()->create(['email' => 'regular@example.com']);

        $this->artisan('tinker-as', ['user' => 'regular@example.com'])
            ->expectsOutputToContain('readonly: нет')
            ->run();

        self::assertSame($user->getKey(), Auth::id());
    }
}
