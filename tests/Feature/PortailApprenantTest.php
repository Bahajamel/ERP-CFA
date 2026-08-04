<?php

use App\Enums\DocumentType;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Mail\AccesEspaceApprenant;
use App\Models\Candidate;
use App\Models\Document;
use App\Models\Formation;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use App\Portail\PortailApprenantService;
use Database\Factories\CandidateFactory;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Un apprenant rattaché à une classe, prêt à ouvrir son espace. */
function apprenant(array $attrs = []): Candidate
{
    $formation = Formation::factory()->create(['libelle' => 'CDA']);
    $classe = Promotion::factory()->create(['formation_id' => $formation->id, 'nom' => 'CDA 2025']);

    return Candidate::factory()->dansClasse($classe)->create($attrs);
}

/** Document visible (avec fichier) rattaché à un apprenant. */
function documentPour(Candidate $candidate, DocumentType $type): Document
{
    $document = $candidate->documents()->create([
        'type' => $type->value,
        'nom_fichier' => $type->getLabel(),
    ]);
    $document->addMediaFromString(CandidateFactory::pdfDemo())
        ->usingFileName('doc_'.$document->id.'.pdf')
        ->toMediaCollection('fichier');

    return $document;
}

// ── Service : jeton personnel ─────────────────────────────────────────────────

it('génère un jeton personnel stable et un lien vers l\'espace', function () {
    $candidate = apprenant();
    $service = app(PortailApprenantService::class);

    $t1 = $service->jeton($candidate);
    $t2 = $service->jeton($candidate->fresh());

    expect($t1)->toBe($t2)
        ->and(strlen($t1))->toBe(48)
        ->and($service->lienPour($candidate->fresh()))->toContain($t1);
});

it('régénère le jeton et invalide l\'ancien lien', function () {
    $candidate = apprenant();
    $service = app(PortailApprenantService::class);

    $ancien = $service->jeton($candidate);
    $nouveau = $service->regenerer($candidate->fresh());

    expect($nouveau)->not->toBe($ancien)
        ->and($service->parToken($ancien))->toBeNull()
        ->and($service->parToken($nouveau)?->id)->toBe($candidate->id);
});

// ── Accès à l'espace ──────────────────────────────────────────────────────────

it('ouvre l\'accueil de l\'apprenant avec un lien valide', function () {
    $candidate = apprenant();
    $token = app(PortailApprenantService::class)->jeton($candidate);

    $this->get(route('portail.apprenant', ['token' => $token]))
        ->assertOk()
        ->assertSee($candidate->nom_complet)
        ->assertSee('Mon assiduité', false);
});

it('rejette un lien d\'espace inconnu (404)', function () {
    $this->get(route('portail.apprenant', ['token' => 'jeton-inexistant']))
        ->assertNotFound();
});

it('affiche la séance de l\'apprenant dans le calendrier du planning', function () {
    $candidate = apprenant();
    $classe = $candidate->promotions()->first();
    $seance = Seance::factory()->create([
        'promotion_id' => $classe->id,
        'date' => now()->addDays(2)->toDateString(),
        'heure_debut' => '09:00',
        'heure_fin' => '12:30',
        'libelle' => 'Atelier Laravel',
    ]);
    $token = app(PortailApprenantService::class)->jeton($candidate);

    // Par défaut, le planning s'ouvre sur le mois de la prochaine séance.
    $this->get(route('portail.apprenant.planning', ['token' => $token]))
        ->assertOk()
        ->assertSee('Mon planning')
        ->assertSee('Atelier Laravel')
        ->assertSee($seance->date->translatedFormat('F Y')); // le mois affiché
});

it('navigue vers un mois sans séance dans le planning', function () {
    $candidate = apprenant();
    $token = app(PortailApprenantService::class)->jeton($candidate);

    // Un mois explicite et vide : la grille s'affiche, sans séance.
    $this->get(route('portail.apprenant.planning', ['token' => $token, 'mois' => now()->addYear()->format('Y-m')]))
        ->assertOk()
        ->assertSee('Mon planning');
});

// ── Documents ─────────────────────────────────────────────────────────────────

it('liste et télécharge un document autorisé de l\'apprenant', function () {
    $candidate = apprenant();
    $convention = documentPour($candidate, DocumentType::Convention);
    $token = app(PortailApprenantService::class)->jeton($candidate);

    $this->get(route('portail.apprenant.documents', ['token' => $token]))
        ->assertOk()
        ->assertSee('Convention');

    $this->get(route('portail.apprenant.document', ['token' => $token, 'document' => $convention->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('n\'expose pas les documents internes (ex. CV) à l\'apprenant', function () {
    $candidate = apprenant();
    $cv = documentPour($candidate, DocumentType::CvCandidat);
    $token = app(PortailApprenantService::class)->jeton($candidate);

    // Type hors liste blanche : ni listé, ni téléchargeable.
    $this->get(route('portail.apprenant.document', ['token' => $token, 'document' => $cv->id]))
        ->assertNotFound();
});

it('interdit l\'accès aux documents d\'un autre apprenant', function () {
    $moi = apprenant();
    $autre = apprenant();
    $docAutre = documentPour($autre, DocumentType::Convention);
    $token = app(PortailApprenantService::class)->jeton($moi);

    $this->get(route('portail.apprenant.document', ['token' => $token, 'document' => $docAutre->id]))
        ->assertNotFound();
});

// ── White-label ───────────────────────────────────────────────────────────────

it('teinte le portail aux couleurs du CFA de l\'apprenant', function () {
    // Le tenant courant des tests porte la couleur : l'apprenant en hérite.
    Filament::getTenant()->update(['couleur_primaire' => '#10b981']);
    $candidate = apprenant();
    $token = app(PortailApprenantService::class)->jeton($candidate);

    $this->get(route('portail.apprenant', ['token' => $token]))
        ->assertOk()
        ->assertSee('--primary-600:'.Color::hex('#10b981')[600], false);
});

// ── E-mail d'accès ────────────────────────────────────────────────────────────

it('compose l\'e-mail d\'accès avec le lien personnel', function () {
    $candidate = apprenant(['prenom' => 'Léa']);
    $lien = app(PortailApprenantService::class)->lienPour($candidate);

    $rendu = (new AccesEspaceApprenant($candidate, $lien, 'Mon CFA'))->render();

    expect($rendu)->toContain('Léa')
        ->and($rendu)->toContain($lien);
});

it('envoie l\'accès en lot et ignore les apprenants sans e-mail', function () {
    Mail::fake();
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Administrateur');
    $this->actingAs($user);

    $avecEmail = apprenant(['email' => 'lea@example.test']);
    $sansEmail = apprenant(['email' => null, 'telephone' => '+33612345678']);

    Livewire::test(ListCandidates::class)
        ->callTableBulkAction('envoyerEspace', [$avecEmail->getKey(), $sansEmail->getKey()]);

    Mail::assertSent(AccesEspaceApprenant::class, 1);
    Mail::assertSent(AccesEspaceApprenant::class, fn ($mail) => $mail->hasTo('lea@example.test'));
});
