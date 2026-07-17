<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panneau de l'éditeur (nous, exploitant de la solution) — distinct du panneau
 * CFA. Deux audiences, deux panneaux :
 *
 * - `/admin/{cfa}` : le personnel d'un CFA travaille dans SON centre (tenant).
 * - `/editeur`     : nous créons, renommons et suspendons les CFA clients.
 *
 * Ce panneau n'est volontairement PAS multi-tenant : aucun CFA courant n'y est
 * défini, donc OrganisationScope ne s'applique pas et la vision est globale.
 * L'accès est fermé par la permission `access_editeur` (User::canAccessPanel).
 */
class EditeurPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('editeur')
            ->path('editeur')
            ->brandName('ERP CFA — Éditeur')
            ->font('Instrument Sans')
            ->defaultThemeMode(ThemeMode::Dark)
            ->login(Login::class)
            // Palette distincte du panneau CFA (ambre) : repère visuel immédiat
            // pour ne jamais confondre « je pilote un CFA » et « je pilote les CFA ».
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Slate,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Editeur/Resources'), for: 'App\Filament\Editeur\Resources')
            ->discoverPages(in: app_path('Filament/Editeur/Pages'), for: 'App\Filament\Editeur\Pages')
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
