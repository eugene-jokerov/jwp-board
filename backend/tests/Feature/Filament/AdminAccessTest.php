<?php

namespace Tests\Feature\Filament;

use App\Filament\Auth\Login;
use App\Modules\User\Domain\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Доступ в админку (/admin, Filament).
 *
 * Проверяются три вещи:
 *  - гостя панель уводит на свою страницу входа, а не на лендинг (регресс
 *    Route::fallback в routes/web.php, который перехватывает неизвестные пути);
 *  - пускает только пользователя с флагом is_admin (contract FilamentUser);
 *  - флаг нельзя поднять через публичную регистрацию в API.
 */
final class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Гость на /admin получает редирект на страницу входа панели.
     *
     * @return TestResponse<Response>
     */
    private function getAdminAsGuest(): TestResponse
    {
        return $this->get('/admin');
    }

    public function test_guest_is_redirected_to_panel_login(): void
    {
        $response = $this->getAdminAsGuest();

        $response->assertRedirect('/admin/login');
    }

    public function test_regular_user_cannot_access_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_admin_can_access_panel(): void
    {
        $user = User::factory()->admin()->create();

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

        $user = User::query()->where('email', 'intruder@example.test')->firstOrFail();

        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }
}
