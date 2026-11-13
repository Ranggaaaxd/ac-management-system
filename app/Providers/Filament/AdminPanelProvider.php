<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AcConditionChartWidget;
use App\Filament\Widgets\AcNeedsAttentionWidget;
use App\Filament\Widgets\AcOverviewStatsWidget;
use App\Filament\Widgets\BuildingExpenseChartWidget;
use App\Filament\Widgets\DashboardQuickActionsWidget;
use App\Models\CompanySetting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Sistem Manajemen AC')
            ->brandLogo(fn (): ?string => Schema::hasTable('company_settings')
                ? (($logoPath = CompanySetting::query()->value('logo_path'))
                    ? Storage::disk('public')->url($logoPath)
                    : null)
                : null)
            ->favicon(fn (): string => Schema::hasTable('company_settings')
                ? (($logoPath = CompanySetting::query()->value('logo_path'))
                    ? Storage::disk('public')->url($logoPath)
                    : asset('favicon.ico'))
                : asset('favicon.ico'))
            ->sidebarCollapsibleOnDesktop()
            ->login(Login::class)
            ->spa()
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.custom-animations'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.auth.login-styles'),
                scopes: Login::class,
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View => view('filament.auth.login-loading'),
                scopes: Login::class,
            )
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([Dashboard::class])
            ->navigationGroups([
                'Data AC',
                'Servis AC',
                'Inventori & Stok',
                'Keuangan',
                'Master Data',
                'Pengaturan',
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                DashboardQuickActionsWidget::class,
                AcOverviewStatsWidget::class,
                AcConditionChartWidget::class,
                BuildingExpenseChartWidget::class,
                AcNeedsAttentionWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
