<?php

namespace App\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Вход в админку с защитой от перебора пароля.
 *
 * Маршрута входа у панели нет (`->login(null)` в AdminPanelProvider): вход
 * выполняется через /api/v1/auth/web/login. Класс страницы остаётся, потому
 * что на базовой странице Filament держится проверка учётных данных и прав
 * администратора, а этот подкласс добавляет к ней счётчик попыток; форма
 * используется тестами как Livewire-компонент.
 *
 * Базовая страница Filament ограничивает только количество запросов с одного
 * IP: неверные пароли к разным аккаунтам с одного адреса считаются вместе, а
 * распределённый перебор (много IP на один email) не ограничивается вовсе.
 * Здесь добавляется второй, независимый счётчик: неудачные попытки по паре
 * `email + IP`. Он и защищает конкретный аккаунт от перебора.
 *
 * Правила:
 *  - лимит 5 неудачных попыток за 60 секунд на пару `email + IP`;
 *  - успешный вход сбрасывает счётчик (считаем неудачи, а не все нажатия);
 *  - проверка лимита выполняется до проверки пароля — заблокированный клиент
 *    не нагружает хеширование и не узнаёт, верны ли учётные данные;
 *  - текст ошибки одинаков для «нет такого email», «неверный пароль» и
 *    «нет прав на панель» (так выдаёт базовая страница) — email не перечисляется.
 *
 * IP-лимит Filament (5 запросов в минуту через `rateLimit()`) остаётся
 * вторым слоем: он ограничивает общий поток запросов к Livewire-методу.
 *
 * Счётчики хранятся в CACHE_STORE. При нескольких PHP-инстансах нужен общий
 * стор (redis/database), иначе запас попыток умножается на число инстансов.
 */
class Login extends BaseLogin
{
    /**
     * Слаг маршрута (используется, если страницу входа в панели подключат
     * снова): по умолчанию Filament строит его из имени класса, и подкласс
     * увёл бы панель с /admin/login на /admin/app-login.
     */
    protected static ?string $slug = 'login';

    /**
     * Сколько неудачных попыток допускается в окне.
     */
    private const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Длина окна (и блокировки) в секундах.
     */
    private const LOCKOUT_SECONDS = 60;

    /**
     * Вход по email и паролю с защитой от перебора по конкретному email.
     */
    public function authenticate(): ?LoginResponse
    {
        // Сбрасываем незавершённый многофакторный вход: он относится к прошлой
        // попытке и не должен переживать новый ввод учётных данных.
        $this->userUndertakingMultiFactorAuthentication = null;

        $key = $this->loginAttemptsKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS)) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                (string) request()->ip(),
                RateLimiter::availableIn($key),
            ))?->send();

            return null;
        }

        try {
            return parent::authenticate();
        } finally {
            // Пароль успешного входа остаётся в состоянии формы: при повторном
            // заполнении (fill()) он подставился бы обратно в поле ввода.
            $this->form->fill(['password' => null]);

            // Считаются только неудачи: успешный вход сбрасывает счётчик,
            // чтобы штатная работа администратора не упиралась в блокировку.
            Filament::auth()->check()
                ? RateLimiter::clear($key)
                : RateLimiter::hit($key, self::LOCKOUT_SECONDS);
        }
    }

    /**
     * Нотификация о блокировке.
     *
     * Время до следующей попытки берётся из нашего счётчика (по email),
     * иначе Filament показал бы остаток по своему IP-ключу.
     */
    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        $key = $this->loginAttemptsKey();

        return parent::getRateLimitedNotification(new TooManyRequestsException(
            $exception->component,
            $exception->method,
            $exception->ip,
            RateLimiter::tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS)
                ? RateLimiter::availableIn($key)
                : $exception->secondsUntilAvailable,
        ));
    }

    /**
     * Ключ счётчика неудачных попыток для пары `email + IP`.
     */
    private function loginAttemptsKey(): string
    {
        $email = mb_strtolower(trim((string) data_get($this->data, 'email', '')));

        // Пустой email (например, запрос в обход формы) не должен схлопывать
        // все попытки в ключ `sha1('|ip')` — отделяем «пусто» явной меткой.
        return 'filament-admin-login:'.sha1(($email === '' ? '-' : $email).'|'.request()->ip());
    }
}
