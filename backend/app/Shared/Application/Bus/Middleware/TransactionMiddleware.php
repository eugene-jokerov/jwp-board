<?php

namespace App\Shared\Application\Bus\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Пайп шины команд: выполняет хендлер внутри транзакции БД.
 *
 * Регистрация пользователя — это проверка занятости email и вставка записи.
 * Оборачивая хендлер в транзакцию, мы получаем атомарность на уровне запроса;
 * гонку между параллельными запросами окончательно закрывает уникальный
 * индекс users.email.
 */
final readonly class TransactionMiddleware
{
    /**
     * Выполнить следующий обработчик в транзакции.
     *
     * @param  Closure(object): mixed  $next
     */
    public function __invoke(object $command, Closure $next): mixed
    {
        return DB::transaction(fn () => $next($command));
    }
}
