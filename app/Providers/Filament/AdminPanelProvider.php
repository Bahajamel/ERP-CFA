<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Tenancy\ProfilCfa;
use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Filament\Resources\Candidates\Pages\ViewCandidate;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Http\Middleware\AuthenticateCfaPanel;
use App\Models\Organisation;
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
            // Multi-tenant : chaque CFA (Organisation) est un tenant. Les données
            // rattachées (organisation_id, relation `organisation`) sont cloisonnées
            // automatiquement ; les resources de référence sont exclues via
            // $isScopedToTenant = false.
            ->tenant(Organisation::class, slugAttribute: 'slug', ownershipRelationship: 'organisation')
            // Fiche du CFA courant (nom). Pas de ->tenantRegistration() ici :
            // l'ouverture d'un CFA est un acte commercial, réservé au panneau
            // /editeur — on ne s'inscrit pas soi-même comme CFA client.
            ->tenantProfile(ProfilCfa::class)
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
            // Page de connexion premium « Meridian CFA » (écran divisé) — la logique
            // d'auth reste celle de Filament, seule la vue est personnalisée.
            ->login(Login::class)
            // Page profil enrichie : photo de profil + double authentification.
            ->profile(EditProfile::class, isSimple: false)
            // Double authentification par application (TOTP) avec codes de secours.
            // OBLIGATOIRE pour les administrateurs (accès total, cible privilégiée) :
            // à leur prochaine connexion, ils sont dirigés vers la mise en place du MFA.
            // Facultative pour les autres rôles (pas de verrouillage des comptes métier).
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable(),
                isRequired: fn (): bool => auth()->user()?->hasRole('Administrateur') ?? false,
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
            // Barre de navigation contextuelle « Commercial » : accès rapides
            // Candidats / Entreprises / Offres, affichée en haut du contenu (au-dessus
            // du titre) sur toutes les pages du groupe Commercial. Le rendu est
            // conditionnel DANS la vue (CommercialNavigation::doitAfficher) : aucune
            // duplication par page, aucune route codée en dur, onglet actif déduit
            // de la route. Placée avant le bouton « Retour » pour rester au-dessus.
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): string => auth()->check() ? view('filament.commercial-subnav')->render() : '',
            )
            // Sélecteur de tables « façon Monday » : bascule entre Base Candidats et
            // les tableaux personnalisés du CFA. Rendu conditionnel DANS la vue
            // (BoardNavigation::doitAfficher) — visible sur la Base Candidats et les
            // pages des tableaux personnalisés, onglet actif déduit de la route.
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): string => auth()->check() ? view('filament.board-switcher')->render() : '',
            )
            // Flèche « Retour à la liste » : uniquement sur les sous-pages des
            // ressources Candidat & Entreprise (Créer / Modifier / Voir), pas sur
            // les listes ni le reste du logiciel. Scopée aux classes de pages
            // concernées ; CONTENT_START couvre aussi leurs vues custom (premium).
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn (): string => auth()->check() ? view('filament.back-button')->render() : '',
                scopes: [
                    CreateCandidate::class,
                    EditCandidate::class,
                    ViewCandidate::class,
                    CreateCompany::class,
                    EditCompany::class,
                    ViewCompany::class,
                ],
            )
            // Assistant d'aide « Demander à l'IA » : bouton flottant sur toutes les pages.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() ? Blade::render('@livewire(\App\Livewire\AssistantIa::class)') : '',
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
            // Authenticate maison : oriente au lieu de murer (403) un compte qui
            // n'a rien à faire ici — typiquement l'éditeur, connecté via la même
            // session et sans CFA rattaché. Voir AuthenticateCfaPanel.
            ->authMiddleware([
                AuthenticateCfaPanel::class,
            ]);
    }
}
