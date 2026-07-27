<?php

namespace App\Providers;

use App\Signature\Contracts\SignatureProvider;
use App\Signature\Providers\NullSignatureProvider;
use App\Signature\Providers\SimulationSignatureProvider;
use App\Signature\Providers\YousignSignatureProvider;
use Filament\Events\TenantSet;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Prestataire de signature électronique actif (config/signature.php).
        // Un prestataire eIDAS réel s'ajoute ici en implémentant SignatureProvider.
        $this->app->bind(SignatureProvider::class, fn () => match (config('signature.driver')) {
            'simulation' => new SimulationSignatureProvider,
            // Prestataire réel. Sans clé d'API, il se déclare inactif : basculer
            // SIGNATURE_DRIVER=yousign sans abonnement ne casse rien, la signature
            // électronique est simplement indisponible.
            'yousign' => $this->app->make(YousignSignatureProvider::class),
            default => new NullSignatureProvider,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // En production, toutes les URL générées sont en HTTPS : évite qu'un
        // asset ou une redirection ne repasse en http (contenu mixte, cookie de
        // session non « secure » transmis en clair). Sans effet en local (http).
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Politique de mot de passe forte (appliquée partout où Password::default()
        // est utilisé : formulaire utilisateur, page profil Filament) : 12 caractères
        // minimum, casse mixte, chiffre + symbole, et refus des mots de passe connus
        // comme compromis (fuite HaveIBeenPwned, k-anonymat — échoue « ouvert » si
        // l'API est injoignable, donc sans blocage hors-ligne).
        Password::defaults(fn (): Password => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());

        // Mémorise la date de dernière connexion de l'utilisateur.
        Event::listen(Login::class, function (Login $event): void {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });

        // Multi-tenant : dès qu'un CFA courant est défini, on fixe le paramètre
        // {tenant} par défaut pour toute génération d'URL. Ainsi les appels
        // `route('filament.admin.resources.…')` (widgets, cockpit, services)
        // reçoivent automatiquement le tenant, sans le passer explicitement.
        Event::listen(TenantSet::class, function (TenantSet $event): void {
            URL::defaults(['tenant' => $event->getTenant()->getRouteKey()]);
        });
    }
}
