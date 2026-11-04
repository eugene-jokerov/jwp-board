<?php

namespace Tests\Feature\Shared\Bus;

use App\Shared\Application\Bus\CommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Шина команд: маппинг «команда → хендлер» и транзакционный пайп.
 *
 * Проверяем поведение реальной привязки из BusServiceProvider: команда
 * попадает в хендлер по карте Bus::map(), а пайпы выполняются до вызова.
 */
final class CommandBusTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_command_to_mapped_handler(): void
    {
        Bus::map([CommandBusTestCommand::class => CommandBusTestHandler::class]);

        $result = $this->commandBus()->dispatch(new CommandBusTestCommand('payload'));

        self::assertSame('handled:payload', $result);
    }

    public function test_throws_when_command_has_no_handler(): void
    {
        $this->expectException(RuntimeException::class);

        $this->commandBus()->dispatch(new CommandBusTestCommand('unmapped'));
    }

    public function test_transaction_pipe_rolls_back_on_handler_failure(): void
    {
        // Запись вне транзакции команды — она должна остаться после откатa.
        DB::table('users')->insert([
            'email' => 'committed@jwp-board.local',
            'password' => 'hash',
        ]);

        Bus::map([CommandBusTestFailingCommand::class => CommandBusTestFailingHandler::class]);

        try {
            $this->commandBus()->dispatch(new CommandBusTestFailingCommand);
            self::fail('Ожидалось исключение хендлера');
        } catch (RuntimeException $e) {
            self::assertSame('handler failed', $e->getMessage());
        }

        // Изменение из хендлера откатилось, ранее вставленная строка осталась.
        self::assertDatabaseCount('users', 1);
        self::assertDatabaseMissing('users', ['email' => 'rollback@jwp-board.local']);
    }

    /**
     * Шина команд из контейнера: те же привязки и пайпы, что в приложении.
     */
    private function commandBus(): CommandBus
    {
        $bus = $this->app->make(CommandBus::class);
        self::assertInstanceOf(CommandBus::class, $bus);

        return $bus;
    }
}

/**
 * Тестовая команда без побочных эффектов.
 */
final readonly class CommandBusTestCommand
{
    public function __construct(
        public string $payload,
    ) {}
}

/**
 * Хендлер тестовой команды: возвращает строку, чтобы проверить проброс результата.
 */
final readonly class CommandBusTestHandler
{
    public function handle(CommandBusTestCommand $command): string
    {
        return 'handled:'.$command->payload;
    }
}

/**
 * Команда, хендлер которой падает после записи в БД.
 */
final readonly class CommandBusTestFailingCommand {}

/**
 * Хендлер: пишет строку и бросает исключение — транзакция должна откатиться.
 */
final readonly class CommandBusTestFailingHandler
{
    public function handle(CommandBusTestFailingCommand $command): void
    {
        DB::table('users')->insert([
            'email' => 'rollback@jwp-board.local',
            'password' => 'hash',
        ]);

        throw new RuntimeException('handler failed');
    }
}
