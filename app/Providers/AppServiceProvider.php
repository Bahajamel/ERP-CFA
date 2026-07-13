<?php

namespace App\Providers;

use App\Signature\Contracts\SignatureProvider;
use App\Signature\Providers\NullSignatureProvider;
use App\Signature\Providers\SimulationSignatureProvider;
use Filament\Events\TenantSet;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
            default => new NullSignatureProvider,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
