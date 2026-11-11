<?php

namespace Tests\Feature\Filament;

use App\Filament\Auth\Login;
use App\Modules\User\Domain\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Защита формы входа в админку от перебора пароля по email.
 *
 * App\Filament\Auth\Login ведёт отдельный счётчик неудачных попыток по паре
 * `email + IP`: заблокированная пара не пускает даже с верным паролем, а
 * успешный вход счётчик обнуляет. IP-лимит Filament (5 запросов в минуту на
 * вызов метода) не должен влиять на результат, поэтому он снимается в setUp.
 *
 * Сама панель скрыта от посторонних (см. AdminAccessTest), поэтому форма
 * вызывается как Livewire-компонент, а не через маршрут /admin/login.
 */
final class AdminLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password';

    private const MAX_ATTEMPTS = 5;

    protected function setUp(): void
    {
        parent::setUp();

        // Ключ IP-лимита Filament: trait WithRateLimiting считает попытки по
        // `sha1(компонент|метод|ip)`.
        RateLimiter::clear('livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1'));
    }

    public function test_panel_login_page_is_not_exposed(): void
    {
        // Страницы входа в панели нет: /admin/login отдаёт 404, как и остальные
        // адреса админки для гостя. Форма входа при этом жива — её защита от
        // перебора проверяется ниже прямым вызовом Livewire-компонента.
        $this->get('/admin/login')->assertNotFound();
    }

    public function test_failed_attempts_are_counted_per_email(): void
    {
        $user = User::factory()->admin()->create(['password' => self::PASSWORD]);
        $other = User::factory()->admin()->create(['password' => self::PASSWORD]);

        $this->assertFailedLogin($user->email, 'wrong-password');
        $this->assertFailedLogin($user->email, 'wrong-password');

        // Второй email с того же IP свои попытки начинает с нуля.
        $this->assertSame(2, RateLimiter::attempts($this->failuresKey($user->email)));
        $this->assertSame(0, RateLimiter::attempts($this->failuresKey($other->email)));
    }

    public function test_locked_email_is_rejected_even_with_valid_password(): void
    {
        $user = User::factory()->admin()->create(['password' => self::PASSWORD]);

        foreach (range(1, self::MAX_ATTEMPTS) as $attempt) {
            $this->assertFailedLogin($user->email, 'wrong-password');
        }

        $this->assertTrue(RateLimiter::tooManyAttempts(
            $this->failuresKey($user->email),
            self::MAX_ATTEMPTS,
        ));

        // Проверка пароля не выполняется вовсе: ошибки валидации нет,
        // показывается только нотификация о блокировке.
        $component = $this->attemptLogin($user->email, self::PASSWORD);

        $component->assertHasNoFormErrors();
        $component->assertNotified();

        $this->assertGuest('web');
    }

    public function test_lockout_expires_after_the_window(): void
    {
        $user = User::factory()->admin()->create(['password' => self::PASSWORD]);

        foreach (range(1, self::MAX_ATTEMPTS) as $attempt) {
            $this->assertFailedLogin($user->email, 'wrong-password');
        }

        $this->travel(61)->seconds();

        $this->attemptLogin($user->email, self::PASSWORD)
            ->assertHasNoErrors();

        $this->assertAuthenticated('web');
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $user = User::factory()->admin()->create(['password' => self::PASSWORD]);

        $this->assertFailedLogin($user->email, 'wrong-password');
        $this->assertFailedLogin($user->email, 'wrong-password');

        $this->attemptLogin($user->email, self::PASSWORD)
            ->assertHasNoErrors();

        $this->assertAuthenticated('web');

        // Успешный вход обнулил счётчик неудач — блокировки нет.
        $this->assertSame(0, RateLimiter::attempts($this->failuresKey($user->email)));
        $this->assertFalse(RateLimiter::tooManyAttempts($this->failuresKey($user->email), self::MAX_ATTEMPTS));
    }

    /**
     * Ключ счётчика неудачных попыток по паре `email + IP` (App\Filament\Auth\Login).
     */
    private function failuresKey(string $email, string $ip = '127.0.0.1'): string
    {
        return 'filament-admin-login:'.sha1(mb_strtolower(trim($email)).'|'.$ip);
    }

    /**
     * Попытка входа через форму панели.
     *
     * @return Testable<Login>
     */
    private function attemptLogin(string $email, string $password): Testable
    {
        return Livewire::test(Login::class)
            ->fillForm([
                'email' => $email,
                'password' => $password,
            ])
            ->call('authenticate');
    }

    private function assertFailedLogin(string $email, string $password): void
    {
        $this->attemptLogin($email, $password)->assertHasFormErrors(['email']);

        $this->assertGuest('web');
    }
}
