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

    /**
     * Rend la main à l'écran de connexion du panneau CFA, en expliquant pourquoi.
     *
     * On NE détourne PAS vers /editeur : qui demande /admin veut /admin. Se
     * retrouver ailleurs sans l'avoir demandé donne l'impression d'un lien cassé,
     * et enferme l'éditeur dans une boucle dont il ne peut pas sortir. On le
     * déconnecte donc et on le dépose sur la connexion du CFA, d'où il repart
     * avec le bon compte.
     */
    private function orienter(mixed $user): RedirectResponse
    {
        $estEditeur = $user instanceof User
            && $user->can(RolePermissionSeeder::PERMISSION_EDITEUR);

        Notification::make()
            ->title($estEditeur
                ? 'Vous étiez connecté en tant qu\'éditeur'
                : 'Aucun espace de travail')
            ->body($estEditeur
                ? 'Le compte éditeur pilote les CFA clients : il n\'est rattaché à aucun CFA et n\'a donc pas d\'espace de travail. Connectez-vous avec un compte du CFA pour entrer.'
                : 'Votre compte n\'est rattaché à aucun CFA actif. Contactez votre administrateur pour qu\'il vous rattache à votre centre.')
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
