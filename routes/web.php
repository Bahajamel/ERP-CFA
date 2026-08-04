<?php

use App\Http\Controllers\CandidatureController;
use App\Http\Controllers\EmargementSignatureController;
use App\Http\Controllers\EntrepriseFormController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\Portail\PortailApprenantController;
use App\Http\Controllers\Portail\PortailEntrepriseController;
use App\Http\Controllers\PublicCustomTableController;
use App\Http\Controllers\SecureMediaController;
use App\Http\Controllers\SignatureWebhookController;
use App\Http\Controllers\VitrineController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Site vitrine public de Meridian CFA (page commerciale). La racine ne redirige
// plus vers l'ERP : « Se connecter » y mène explicitement. Aucune donnée
// sensible ici — ce sont des pages publiques.
Route::get('/', [VitrineController::class, 'accueil'])->name('vitrine.accueil');
Route::get('/mentions-legales', [VitrineController::class, 'mentions'])->name('vitrine.mentions');
Route::get('/politique-confidentialite', [VitrineController::class, 'confidentialite'])->name('vitrine.confidentialite');

// Bases SEO. On expose la vitrine, on tient les crawlers hors des espaces
// authentifiés (admin, editeur) et des formulaires publics tokenisés.
Route::get('/robots.txt', function () {
    $lignes = [
        'User-agent: *',
        'Allow: /$',
        'Allow: /mentions-legales',
        'Allow: /politique-confidentialite',
        'Disallow: /admin',
        'Disallow: /editeur',
        'Disallow: /candidature',
        'Disallow: /entreprise',
        'Disallow: /inscription',
        'Disallow: /mon-espace',
        'Disallow: /espace-entreprise',
        'Disallow: /tableau',
        'Sitemap: '.route('vitrine.sitemap'),
    ];

    return response(implode("\n", $lignes)."\n", 200, ['Content-Type' => 'text/plain']);
})->name('vitrine.robots');

Route::get('/sitemap.xml', function () {
    $urls = [route('vitrine.accueil'), route('vitrine.mentions'), route('vitrine.confidentialite')];

    return response()
        ->view('vitrine.sitemap', ['urls' => $urls])
        ->header('Content-Type', 'application/xml');
})->name('vitrine.sitemap');

// Accès aux pièces sensibles (données personnelles / NIR) stockées sur disque
// privé : jamais d'URL publique. Double verrou — session ERP (`auth`) ET lien
// signé non expiré (`signed`), généré par App\Support\SecureMedia.
Route::get('/documents-securises/{media}', SecureMediaController::class)
    ->middleware(['auth', 'signed'])
    ->name('documents.securise');

// Formulaire public de candidature (sans accès ERP) : crée un candidat « Dossier
// incomplet » avec ses pièces (collections média cv / piece_identite / carte_vitale / attestation_projet).
// Le CFA destinataire vient du segment {cfa} (slug de l'organisation). Segment
// absent = CFA par défaut : les liens historiques /candidature restent valides.
// « merci » est déclaré AVANT « {cfa?} » pour ne pas être pris pour un slug.
Route::view('/candidature/merci', 'candidature.merci')->name('candidature.merci');
Route::post('/candidature', [CandidatureController::class, 'store'])->middleware('throttle:6,1')->name('candidature.store');
Route::get('/candidature/{cfa?}', [CandidatureController::class, 'create'])->name('candidature.create');

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

// Signature publique de l'émargement (sans accès ERP) : l'apprenant signe sa
// présence à une séance via un lien tokenisé personnel, depuis son appareil.
// « confirmation » est déclaré AVANT « {token} » pour ne pas être capté comme un jeton.
Route::view('/emargement/confirmation', 'emargement.merci')->name('emargement.merci');
// QR unique de séance : la classe choisit son nom puis signe.
Route::get('/emargement/seance/{token}', [EmargementSignatureController::class, 'seance'])->name('emargement.seance');
Route::get('/emargement/{token}', [EmargementSignatureController::class, 'show'])->name('emargement.signer');
Route::post('/emargement/{token}', [EmargementSignatureController::class, 'store'])
    ->middleware('throttle:10,1')->name('emargement.signer.store');

// Espace personnel de l'apprenant (portail sans mot de passe) : accès par un
// jeton personnel porté par l'URL, sans session ERP — comme l'inscription et
// l'émargement. Chaque page ne montre que les données de cet apprenant.
Route::get('/mon-espace/{token}', [PortailApprenantController::class, 'accueil'])->name('portail.apprenant');
Route::get('/mon-espace/{token}/planning', [PortailApprenantController::class, 'planning'])->name('portail.apprenant.planning');
Route::get('/mon-espace/{token}/documents', [PortailApprenantController::class, 'documents'])->name('portail.apprenant.documents');
Route::get('/mon-espace/{token}/document/{document}', [PortailApprenantController::class, 'document'])
    ->middleware('throttle:30,1')->name('portail.apprenant.document');

// Espace personnel de l'entreprise (portail sans mot de passe) : même principe
// côté employeur — jeton personnel, sans session ERP. L'entreprise y suit ses
// alternants, leur assiduité, ses documents et ses factures.
Route::get('/espace-entreprise/{token}', [PortailEntrepriseController::class, 'accueil'])->name('portail.entreprise');
Route::get('/espace-entreprise/{token}/alternants', [PortailEntrepriseController::class, 'alternantsPage'])->name('portail.entreprise.alternants');
Route::get('/espace-entreprise/{token}/documents', [PortailEntrepriseController::class, 'documents'])->name('portail.entreprise.documents');
Route::get('/espace-entreprise/{token}/factures', [PortailEntrepriseController::class, 'factures'])->name('portail.entreprise.factures');
Route::get('/espace-entreprise/{token}/document/{document}', [PortailEntrepriseController::class, 'document'])
    ->middleware('throttle:30,1')->name('portail.entreprise.document');

// Formulaire public « entreprise partenaire » (sans accès ERP) : auto-rempli
// depuis le SIRET (identité + OPCO), crée une entreprise « Prospect » + contact.
// Comme pour la candidature, le CFA vient du segment {cfa} (absent = CFA par
// défaut). Toutes les routes à segment littéral sont déclarées AVANT « {cfa?} ».
Route::get('/entreprise/lookup', [EntrepriseFormController::class, 'lookup'])->middleware('throttle:20,1')->name('entreprise.lookup');
// Étape 2 — fiche besoin : l'entreprise décrit elle-même le poste recherché. Le
// besoin créé attend une relecture commerciale avant d'entrer dans le matching.
// L'entreprise du parcours vient de la session (posée à l'étape 1), pas de l'URL —
// le CFA se déduit donc de l'entreprise elle-même, sans ambiguïté possible.
Route::get('/entreprise/besoin', [EntrepriseFormController::class, 'besoin'])->name('entreprise.besoin');
Route::post('/entreprise/besoin', [EntrepriseFormController::class, 'besoinStore'])
    ->middleware('throttle:6,1')->name('entreprise.besoin.store');
Route::view('/entreprise/merci', 'entreprise.merci')->name('entreprise.merci');
Route::post('/entreprise', [EntrepriseFormController::class, 'store'])->middleware('throttle:6,1')->name('entreprise.store');
Route::get('/entreprise/{cfa?}', [EntrepriseFormController::class, 'create'])->name('entreprise.create');

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
