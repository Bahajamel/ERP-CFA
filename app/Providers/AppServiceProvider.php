<?php

namespace App\Providers;

use App\Signature\Contracts\SignatureProvider;
use App\Signature\Providers\NullSignatureProvider;
use App\Signature\Providers\SimulationSignatureProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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
            default => new NullSignatureProvider,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
    }
}
