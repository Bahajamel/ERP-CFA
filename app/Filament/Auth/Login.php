<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Page de connexion « Meridian CFA » — design premium en écran divisé
 * (hero marketing à gauche, formulaire à droite).
 *
 * Étend la page Filament officielle : TOUTE la logique d'authentification
 * (rate-limit, MFA/TOTP, events Laravel, validations, champs email/password/
 * remember, bouton de soumission, render hooks) reste strictement inchangée.
 * Seule la VUE est remplacée — cf. resources/views/filament/auth/login.blade.php,
 * qui réaffiche le formulaire réel via {{ $this->content }} à l'intérieur d'un
 * élément racine UNIQUE (impératif Livewire pour que le formulaire soit relié).
 */
class Login extends BaseLogin
{
    protected string $view = 'filament.auth.login';
}
