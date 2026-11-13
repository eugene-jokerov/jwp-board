<?php

namespace Tests\Feature\Filament;

use App\Filament\Auth\Login;
use App\Modules\User\Infrastructure\Persistence\EloquentUserModel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Доступ в админку (/admin, Filament).
 *
 * Панель не афишируется: гость и авторизованный пользователь без флага
 * is_admin получают 404 — тот же ответ, что на несуществующий адрес. Тест
 * фиксирует три вещи:
 *  - посторонним не достаётся ни редиректа на страницу входа, ни 403,
 *    подтверждающего существование панели (в том числе маршрута /admin/login);
 *  - пускает только пользователь с флагом is_admin (contract FilamentUser);
 *  - флаг нельзя поднять через публичную регистрацию в API.
 */
final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адреса, которые ведут в панель.
     *
     * @return list<string>
     */
    private function adminPaths(): array
    {
        return ['/admin', '/admin/login', '/admin/directions', '/admin/users', '/admin/tasks'];
    }

    public function test_guest_gets_404_for_panel(): void
    {
        foreach ($this->adminPaths() as $path) {
            $response = $this->get($path);

            // 404, а не 302 на /admin/login: редирект выдавал бы существование панели.
            $response->assertNotFound();
            self::assertSame(404, $response->getStatusCode(), "Гость не должен видеть {$path}");
        }
    }

    public function test_regular_user_gets_404_for_panel(): void
    {
        $user = EloquentUserModel::factory()->create();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));

        // Вход выполняется через форму панели (Livewire-компонент вызывается
        // напрямую: маршрута /admin/login больше нет), но пользователь без
        // прав не проходит проверку canAccessPanel в ней.
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest('web');

        // Авторизованный не-администратор тоже не должен узнать о панели.
        $this->actingAs($user, 'web');

        foreach ($this->adminPaths() as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_admin_can_access_panel(): void
    {
        $user = EloquentUserModel::factory()->admin()->create();

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        // Проверяем реальным входом через форму панели, а не actingAs:
        // middleware AuthenticateSession сверяет пароль сессии с текущим,
        // а actingAs сессию панели не создаёт — результат зависел бы от теста.
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user, 'web');

        $this->get('/admin')->assertSuccessful();
    }

    public function test_guest_sees_landing_not_admin(): void
    {
        // Лендинг не задет проверкой панели: 404 отдаёт только /admin.
        $this->get('/')->assertOk();
    }

    public function test_is_admin_is_not_filled_through_api_registration(): void
    {
        $response = $this->postJson('/api/v1/user/register', [
            'email' => 'intruder@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'device_name' => 'phpunit',
            // Поле не проходит валидацию FormRequest и в команду не попадает.
            'is_admin' => true,
        ]);

        $response->assertCreated();

        $user = EloquentUserModel::query()->where('email', 'intruder@example.test')->firstOrFail();

        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }
}
