<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// La racine renvoie directement vers le panneau d'administration (l'application).
Route::redirect('/', '/admin');

/*
 * Accès rapide de DÉMONSTRATION : connecte directement le compte administrateur.
 * ⚠️ Volontairement réservé aux environnements non-production (local / démo) —
 * ne sera jamais actif sur un déploiement prod. À retirer avant mise en ligne réelle.
 */
if (! app()->isProduction()) {
    Route::get('/demo/admin', function () {
        $admin = User::where('email', 'admin@cfa-v2s.fr')->first();

        if ($admin === null) {
            return redirect('/admin/login');
        }

        Auth::login($admin);
        request()->session()->regenerate();

        return redirect('/admin');
    })->name('demo.admin');
}
