<?php

use App\Documents\FicheEmargement;
use App\Emargement\SignatureEmargementService;
use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Seance;
use App\Support\QrCode;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** PNG 1×1 transparent, valide, pour simuler une signature. */
const PNG_TEST = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

/** Séance avec sa promotion, sa formation (RNCP) et n apprentis rattachés. */
function seanceComplete(int $n = 2): Seance
{
    $formation = Formation::factory()->create([
        'libelle' => 'Concepteur Développeur d\'Applications',
        'code_rncp' => 'RNCP37873',
        'rncp_intitule' => 'Concepteur développeur d\'applications',
    ]);
    $promotion = Promotion::factory()->create(['formation_id' => $formation->id]);
    $apprentis = Candidate::factory()->count($n)->create();
    $promotion->apprentis()->attach($apprentis->pluck('id'));

    // À la création, une présence « non renseigné » par apprenti est générée.
    return Seance::factory()->create([
        'promotion_id' => $promotion->id,
        'heure_debut' => '09:00',
        'heure_fin' => '12:30',
        'libelle' => 'Atelier pratique',
    ]);
}

// ── Service : jetons & signature ──────────────────────────────────────────────

it('génère un lien de signature idempotent par présence', function () {
    $presence = seanceComplete(1)->presences()->first();
    $service = app(SignatureEmargementService::class);

    $t1 = $service->jetonPour($presence);
    $t2 = $service->jetonPour($presence->fresh());

    expect($t1)->toBe($t2)
        ->and(strlen($t1))->toBe(48)
        ->and($service->signable($presence->fresh()))->toBeTrue();
});

// ── Page publique de signature ────────────────────────────────────────────────

it('affiche le pavé de signature pour un lien valide', function () {
    $presence = seanceComplete(1)->presences()->first();
    $token = app(SignatureEmargementService::class)->jetonPour($presence);

    $this->get(route('emargement.signer', $token))
        ->assertOk()
        ->assertSee($presence->candidate->prenom)
        ->assertSee('signature-data', false); // le champ caché du pavé
});

it('rejette un lien de signature invalide', function () {
    $this->get(route('emargement.signer', 'jeton-inexistant'))
        ->assertOk()
        ->assertSee('invalide ou expiré', false);
});

it('enregistre la signature, horodate et bascule le statut à Présent', function () {
    $presence = seanceComplete(1)->presences()->first();
    $token = app(SignatureEmargementService::class)->jetonPour($presence);

    $this->post(route('emargement.signer.store', $token), ['signature' => PNG_TEST])
        ->assertRedirect(route('emargement.merci'));

    $presence->refresh();

    expect($presence->aSigne())->toBeTrue()
        ->and($presence->statut)->toBe(PresenceStatut::Present)
        ->and($presence->signed_at)->not->toBeNull()
        ->and($presence->getFirstMedia('signature'))->not->toBeNull()
        ->and($presence->signatureDataUri())->toStartWith('data:image/png;base64,');
});

it('refuse une image qui n\'est pas un PNG', function () {
    $presence = seanceComplete(1)->presences()->first();
    $token = app(SignatureEmargementService::class)->jetonPour($presence);

    $this->post(route('emargement.signer.store', $token), ['signature' => 'data:text/plain;base64,SGVsbG8='])
        ->assertSessionHasErrors('signature');

    expect($presence->fresh()->aSigne())->toBeFalse();
});

it('empêche une seconde signature (idempotent)', function () {
    $presence = seanceComplete(1)->presences()->first();
    $service = app(SignatureEmargementService::class);
    $token = $service->jetonPour($presence);

    $this->post(route('emargement.signer.store', $token), ['signature' => PNG_TEST]);
    $premiereDate = $presence->fresh()->signed_at;

    // Le lien renvoie désormais l'état « déjà signé ».
    $this->get(route('emargement.signer', $token))
        ->assertOk()
        ->assertSee('bien été signée', false);

    // Une nouvelle tentative n'écrase pas la signature.
    $this->post(route('emargement.signer.store', $token), ['signature' => PNG_TEST])
        ->assertRedirect(route('emargement.signer', $token));

    expect($presence->fresh()->signed_at->equalTo($premiereDate))->toBeTrue()
        ->and($service->signable($presence->fresh()))->toBeFalse();
});

// ── Génération de la fiche PDF ────────────────────────────────────────────────

it('expose les bonnes données pour la fiche d\'émargement', function () {
    $seance = seanceComplete(3);

    $d = app(FicheEmargement::class)->donnees($seance);

    expect($d['horaires'])->toBe('09h00 – 12h30')
        ->and($d['formation']->code_rncp)->toBe('RNCP37873')
        ->and($d['lignes'])->toHaveCount(3)
        ->and($d['cfa']->is(Filament::getTenant()))->toBeTrue();
});

it('génère un PDF de fiche d\'émargement, signature comprise', function () {
    $seance = seanceComplete(2);
    $presence = $seance->presences()->first();
    $token = app(SignatureEmargementService::class)->jetonPour($presence);
    $this->post(route('emargement.signer.store', $token), ['signature' => PNG_TEST]);

    $pdf = app(FicheEmargement::class)->pour($seance->fresh());

    expect($pdf)->toStartWith('%PDF-')
        ->and($presence->fresh()->signatureDataUri())->not->toBeNull();
});

it('génère la fiche même pour une séance sans apprenant', function () {
    $formation = Formation::factory()->create();
    $promotion = Promotion::factory()->create(['formation_id' => $formation->id]);
    $seance = Seance::factory()->create(['promotion_id' => $promotion->id]);

    $pdf = app(FicheEmargement::class)->pour($seance);

    expect($pdf)->toStartWith('%PDF-');
});

// ── QR code de distribution ───────────────────────────────────────────────────

it('génère un QR code SVG en data-URI pour le lien de signature', function () {
    $uri = QrCode::dataUri('https://cfa.test/emargement/jeton', 120);

    expect($uri)->toStartWith('data:image/svg+xml;base64,');

    $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
    expect($svg)->toContain('<svg');
});

// ── QR unique de séance (la classe choisit son nom) ──────────────────────────

it('génère un jeton de séance idempotent', function () {
    $seance = seanceComplete(2);
    $service = app(SignatureEmargementService::class);

    $t1 = $service->jetonSeance($seance);
    $t2 = $service->jetonSeance($seance->fresh());

    expect($t1)->toBe($t2)->and(strlen($t1))->toBe(48);
});

it('liste les apprenants non signés depuis le QR de séance', function () {
    $seance = seanceComplete(2);
    $token = app(SignatureEmargementService::class)->jetonSeance($seance);
    $apprenti = $seance->presences()->with('candidate')->first()->candidate;

    $this->get(route('emargement.seance', $token))
        ->assertOk()
        ->assertSee('Choisissez votre nom', false)
        ->assertSee($apprenti->nom_complet);
});

it('retire un apprenant de la liste une fois qu\'il a signé', function () {
    $seance = seanceComplete(2);
    $service = app(SignatureEmargementService::class);
    $token = $service->jetonSeance($seance);

    $presence = $seance->presences()->with('candidate')->first();
    $nom = $presence->candidate->nom_complet;
    $service->enregistrer($presence, PNG_TEST, '127.0.0.1');

    $this->get(route('emargement.seance', $token))
        ->assertOk()
        ->assertDontSee($nom);
});

it('affiche « tout le monde a signé » quand la séance est complète', function () {
    $seance = seanceComplete(1);
    $service = app(SignatureEmargementService::class);
    $token = $service->jetonSeance($seance);
    $service->enregistrer($seance->presences()->first(), PNG_TEST, '127.0.0.1');

    $this->get(route('emargement.seance', $token))
        ->assertOk()
        ->assertSee('Tout le monde a signé', false);
});

it('rejette le QR d\'une séance annulée', function () {
    $seance = seanceComplete(1);
    $seance->update(['statut' => SeanceStatut::Annulee->value]);
    $token = app(SignatureEmargementService::class)->jetonSeance($seance);

    $this->get(route('emargement.seance', $token))
        ->assertOk()
        ->assertSee('invalide', false);
});
