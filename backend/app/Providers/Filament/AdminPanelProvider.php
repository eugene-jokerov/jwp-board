<?php

namespace App\Providers\Filament;

use App\Http\Middleware\HideAdminPanel;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Настройка админ-панели (/admin).
     *
     * Панель скрыта от посторонних: гость и авторизованный пользователь без
     * флага is_admin получают 404 (`App\Http\Middleware\HideAdminPanel`), как
     * будто адреса не существует. Публичной страницы входа у панели поэтому
     * нет (`->login(null)`): вход выполняется тем же каналом, что у
     * веб-клиента — гвард `web` (config/auth.php), который использует Sanctum
     * для SPA-режима, так что сессия админки и веб-клиента живут в одном канале.
     *
     * Ассеты панели публикует `filament:install --panels`; Vite/Tailwind
     * в бэкенде нет и для панели не нужны.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Маршрута /admin/login нет: страница входа панели не публикуется.
            // Свой класс входа (App\Filament\Auth\Login) остаётся — на нём
            // держится защита формы от перебора и проверка прав при входе.
            ->login(null)
            // Промпт подтверждения email ведёт на маршрут входа панели, которого
            // больше нет, поэтому подтверждение не требуется.
            ->emailVerification(isRequired: false)
            ->authGuard('web')
            ->authPasswordBroker('users')
            ->brandName('JWP Board')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                // Сразу после старта сессии, до любой проверки аутентификации:
                // гость и пользователь без прав получают 404, не раскрывая
                // существование панели (иначе Filament ответил бы редиректом
                // на вход, а после него — 403).
                HideAdminPanel::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                // Проверка «пароль сессии изменился» штатно редиректит на вход
                // панели; маршрута входа больше нет, поэтому до неё доходят
                // только администраторы (остальные отсечены выше).
                AuthenticateSession::class,
            ])
            // Livewire-запросы панели идут на свой эндпоинт и в HTTP-группу выше
            // не попадают: HideAdminPanel добавляется им явно (persistent
            // middleware), иначе действие пользователя без прав ответило бы 403
            // или редиректом на несуществующую страницу входа.
            ->middleware([HideAdminPanel::class], isPersistent: true);
    }
}
