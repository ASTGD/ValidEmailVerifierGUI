<?php

namespace App\Providers\Filament;

use App\Filament\Pages\OpsOverview;
use App\Filament\Widgets\AdminDashboardHighlights;
use App\Filament\Widgets\AdminQuickLinks;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\EngineWarmupOverview;
use App\Filament\Widgets\FeedbackAnalytics;
use App\Filament\Widgets\FinalizationHealth;
use App\Support\EngineSettings;
use App\Support\Roles;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $goControlPlaneUrl = $this->resolveGoControlPlaneUrl();

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->maxContentWidth(\Filament\Support\Enums\Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->login()
            ->brandName(config('verifier.brand_name') ?: config('app.name'))
            ->assets([
                Css::make('admin-overrides', resource_path('css/filament/admin/admin-overrides.css'))
                    ->relativePublicPath('css/filament/admin-overrides.css'),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                OpsOverview::class,
            ])
            ->navigationItems([
                NavigationItem::make('Verifier Engine Room')
                    ->group('Operations')
                    ->icon(Heroicon::OutlinedServer)
                    ->sort(3)
                    ->url(fn (): string => $goControlPlaneUrl ?? '')
                    ->openUrlInNewTab()
                    ->visible(function () use ($goControlPlaneUrl): bool {
                        $user = auth()->user();

                        return $goControlPlaneUrl !== null
                            && $user
                            && method_exists($user, 'hasRole')
                            && $user->hasRole(Roles::ADMIN);
                    }),
                NavigationItem::make('Queue Engine Room')
                    ->group('Operations')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->sort(10)
                    ->url(fn (): string => url('/'.trim((string) config('horizon.path', 'horizon'), '/')))
                    ->openUrlInNewTab()
                    ->visible(function (): bool {
                        $user = auth()->user();

                        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole(Roles::ADMIN)) {
                            return false;
                        }

                        try {
                            return EngineSettings::horizonEnabled();
                        } catch (\Throwable $exception) {
                            return false;
                        }
                    }),
                NavigationItem::make('Operations Docs')
                    ->group('Operations')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->sort(11)
                    ->url(fn (): string => route('internal.docs.index'))
                    ->openUrlInNewTab()
                    ->visible(function (): bool {
                        $user = auth()->user();

                        return $user && method_exists($user, 'hasRole') && $user->hasRole(Roles::ADMIN);
                    }),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AdminDashboardHighlights::class,
                AdminStatsOverview::class,
                FeedbackAnalytics::class,
                FinalizationHealth::class,
                EngineWarmupOverview::class,
                AccountWidget::class,
                AdminQuickLinks::class,
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

    private function resolveGoControlPlaneUrl(): ?string
    {
        $baseUrl = trim((string) config('services.go_control_plane.base_url', ''));

        if ($baseUrl === '') {
            return null;
        }

        $baseUrl = rtrim($baseUrl, '/');

        if (str_contains($baseUrl, '/verifier-engine-room')) {
            return $baseUrl;
        }

        return $baseUrl.'/verifier-engine-room/overview';
    }
}
