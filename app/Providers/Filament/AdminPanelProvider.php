<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName('ERP CFA')
            ->brandLogo(fn () => view('filament.brand'))
            ->font('Instrument Sans')
            // Identité « cockpit premium » : dark mode par défaut (le switch
            // clair/sombre reste dans le menu utilisateur, préférence mémorisée).
            ->defaultThemeMode(ThemeMode::Dark)
            ->sidebarCollapsibleOnDesktop()
            // Sidebar compacte (16rem au lieu de 20rem) : plus de largeur pour
            // les tableaux des sections (le contenu s'ajuste automatiquement).
            ->sidebarWidth('16rem')
            ->globalSearchKeyBindings(['mod+k'])
            ->login()
            // Page profil : l'utilisateur y active/désactive sa double authentification.
            ->profile(isSimple: false)
            // Double authentification par application (TOTP) avec codes de secours.
            // Facultative pour l'instant (isRequired: false) afin de ne pas verrouiller
            // les comptes existants ; passer à `isRequired: true` pour l'imposer à tous.
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable(),
                isRequired: false,
            )
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // Palette « cockpit » : indigo profond (actions), cyan (information),
            // slate (neutres) — statuts sémantiques inchangés (succès/attente/danger).
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
                'info' => Color::Cyan,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
            ])
            // Ordre des groupes du menu = cycle de vie de l'apprenant, par département.
            ->navigationGroups([
                'Pilotage',
                'Commercial',
                'Admission',
                'Contrats & OPCO',
                'Finance & Facturation',
                'Formation & Scolarité',
                'Qualité',
                'Documents',
                'Administration',
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
            // Topbar : badge d'environnement + menu de création rapide (gated par permissions).
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => auth()->check() ? view('filament.topbar-tools')->render() : '',
            )
            // Identité utilisateur (nom + rôle métier) à gauche de l'avatar.
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => auth()->check() ? view('filament.user-identity')->render() : '',
            )
            // Bouton d'accès rapide (démo) sous le formulaire de connexion — hors production uniquement.
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => app()->isProduction()
                    ? ''
                    : Blade::render(<<<'BLADE'
                        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid rgba(128,128,128,.2);text-align:center;">
                            <x-filament::button tag="a" href="/demo/admin" color="gray" icon="heroicon-m-bolt">
                                Accès rapide Admin (démo)
                            </x-filament::button>
                            <p style="margin-top:.5rem;font-size:.75rem;color:rgba(128,128,128,.9);">
                                Connexion directe pour la démonstration.
                            </p>
                        </div>
                    BLADE),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
