<?php

use App\Http\Controllers\CandidatureController;
use App\Http\Controllers\EntrepriseFormController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\PublicCustomTableController;
use App\Http\Controllers\SecureMediaController;
use App\Http\Controllers\SignatureWebhookController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// La racine renvoie directement vers le panneau d'administration (l'application).
Route::redirect('/', '/admin');

// Accès aux pièces sensibles (données personnelles / NIR) stockées sur disque
// privé : jamais d'URL publique. Double verrou — session ERP (`auth`) ET lien
// signé non expiré (`signed`), généré par App\Support\SecureMedia.
Route::get('/documents-securises/{media}', SecureMediaController::class)
    ->middleware(['auth', 'signed'])
    ->name('documents.securise');

// Formulaire public de candidature (sans accès ERP) : crée un candidat « Dossier
// incomplet » avec ses pièces (collections média cv / piece_identite / carte_vitale / attestation_projet).
Route::get('/candidature', [CandidatureController::class, 'create'])->name('candidature.create');
Route::post('/candidature', [CandidatureController::class, 'store'])->middleware('throttle:6,1')->name('candidature.store');
Route::view('/candidature/merci', 'candidature.merci')->name('candidature.merci');

// Formulaire public de candidature RATTACHÉ À UN TABLEAU personnalisé (sans accès
// ERP) : un jeton ouvre un formulaire bâti sur les colonnes du tableau et crée une
// LIGNE dans ce tableau. « merci » déclaré AVANT « {token} » (sinon capté comme jeton).
Route::view('/tableau/candidature/merci', 'public.tableau-merci')->name('tableau.candidature.merci');
Route::get('/tableau/{token}', [PublicCustomTableController::class, 'show'])->name('tableau.candidature');
Route::post('/tableau/{token}', [PublicCustomTableController::class, 'store'])
    ->middleware('throttle:10,1')->name('tableau.candidature.store');

// Formulaire public d'inscription en scolarité (sans accès ERP) : après validation
// de son admission, l'apprenant choisit ses matières via un lien tokenisé personnel.
// « merci » est déclaré AVANT « {token} » pour ne pas être capté comme un jeton.
Route::view('/inscription/merci', 'inscription.merci')->name('inscription.merci');
Route::get('/inscription/{token}', [InscriptionController::class, 'show'])->name('inscription.matieres');
Route::post('/inscription/{token}', [InscriptionController::class, 'store'])
    ->middleware('throttle:10,1')->name('inscription.matieres.store');

// Formulaire public « entreprise partenaire » (sans accès ERP) : auto-rempli
// depuis le SIRET (identité + OPCO), crée une entreprise « Prospect » + contact.
Route::get('/entreprise', [EntrepriseFormController::class, 'create'])->name('entreprise.create');
Route::get('/entreprise/lookup', [EntrepriseFormController::class, 'lookup'])->middleware('throttle:20,1')->name('entreprise.lookup');
Route::post('/entreprise', [EntrepriseFormController::class, 'store'])->middleware('throttle:6,1')->name('entreprise.store');
// Étape 2 — fiche besoin : l'entreprise décrit elle-même le poste recherché. Le
// besoin créé attend une relecture commerciale avant d'entrer dans le matching.
// L'entreprise du parcours vient de la session (posée à l'étape 1), pas de l'URL.
Route::get('/entreprise/besoin', [EntrepriseFormController::class, 'besoin'])->name('entreprise.besoin');
Route::post('/entreprise/besoin', [EntrepriseFormController::class, 'besoinStore'])
    ->middleware('throttle:6,1')->name('entreprise.besoin.store');
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
