<?php

namespace App\Http\Middleware;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;

/**
 * Contrôle d'accès au panneau CFA, avec une porte de sortie.
 *
 * Filament, lui, se contente d'un `abort_if(! canAccessPanel(), 403)` : un mur
 * nu, sans explication ni moyen d'en sortir. Or les deux panneaux partagent la
 * même session — un éditeur connecté sur /editeur qui clique sur /admin n'a rien
 * à y faire (il n'est rattaché à aucun CFA), mais il mérite d'être orienté, pas
 * muré. Même chose pour un compte dont le CFA vient d'être suspendu.
 */
class AuthenticateCfaPanel extends Authenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return; /** @phpstan-ignore-line */
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        $user = $guard->user();
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($user instanceof FilamentUser && $user->canAccessPanel($panel)) {
            return;
        }

        throw new HttpResponseException($this->orienter($user));
    }

    /** Renvoie l'utilisateur là où il a effectivement quelque chose à faire. */
    private function orienter(mixed $user): RedirectResponse
    {
        // Cas courant : on est connecté en tant qu'éditeur. L'éditeur n'a pas de
        // CFA (par nature), donc le panneau CFA n'a rien à lui montrer — on le
        // ramène simplement chez lui.
        if ($user instanceof User && $user->can(RolePermissionSeeder::PERMISSION_EDITEUR)) {
            Notification::make()
                ->title('Vous êtes connecté en tant qu\'éditeur')
                ->body('Ce compte pilote les CFA clients ; il n\'est rattaché à aucun CFA et n\'a donc pas d\'espace de travail. Pour entrer dans un CFA, déconnectez-vous et utilisez un compte de ce CFA.')
                ->info()
                ->persistent()
                ->send();

            return redirect()->to(Filament::getPanel('editeur')->getUrl());
        }

        // Compte actif mais rattaché à aucun CFA actif (jamais rattaché, ou CFA
        // suspendu) : on l'explique et on rend la main à l'écran de connexion.
        Notification::make()
            ->title('Aucun espace de travail')
            ->body('Votre compte n\'est rattaché à aucun CFA actif. Contactez votre administrateur pour qu\'il vous rattache à votre centre.')
            ->warning()
            ->persistent()
            ->send();

        Filament::auth()->logout();
        $request = request();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(Filament::getLoginUrl());
    }
}
