<?php

use App\Http\Controllers\CandidatureController;
use App\Http\Controllers\EntrepriseFormController;
use App\Http\Controllers\SignatureWebhookController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// La racine renvoie directement vers le panneau d'administration (l'application).
Route::redirect('/', '/admin');

// Formulaire public de candidature (sans accès ERP) : crée un candidat « Dossier
// incomplet » avec ses pièces (collections média cv / piece_identite / carte_vitale / attestation_projet).
Route::get('/candidature', [CandidatureController::class, 'create'])->name('candidature.create');
Route::post('/candidature', [CandidatureController::class, 'store'])->middleware('throttle:6,1')->name('candidature.store');
Route::view('/candidature/merci', 'candidature.merci')->name('candidature.merci');

// Formulaire public « entreprise partenaire » (sans accès ERP) : auto-rempli
// depuis le SIRET (identité + OPCO), crée une entreprise « Prospect » + contact.
Route::get('/entreprise', [EntrepriseFormController::class, 'create'])->name('entreprise.create');
Route::get('/entreprise/lookup', [EntrepriseFormController::class, 'lookup'])->middleware('throttle:20,1')->name('entreprise.lookup');
Route::post('/entreprise', [EntrepriseFormController::class, 'store'])->middleware('throttle:6,1')->name('entreprise.store');
Route::view('/entreprise/merci', 'entreprise.merci')->name('entreprise.merci');

// Callback des prestataires de signature électronique eIDAS (EPIC-08).
// Authentifié par secret partagé (config/signature.php), pas par session.
Route::post('/webhooks/signature/{provider}', SignatureWebhookController::class)
    ->name('webhooks.signature');

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
