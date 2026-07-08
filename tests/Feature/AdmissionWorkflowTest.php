<?php

use App\Enums\AdmissionStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\RuptureStatut;
use App\Filament\Resources\Admissions\AdmissionResource;
use App\Models\Admission;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\User;
use App\StateMachine\InvalidTransitionException;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create());
});

/** Admission officielle d'un candidat SANS CV (chaîne contrat signé + OPCO). */
function admissionSansCv(): Admission
{
    return Admission::factory()->create();
}

/** Admission officielle d'un candidat AVEC un CV attaché. */
function admissionAvecCv(): Admission
{
    Storage::fake('public');
    $candidate = Candidate::factory()->create();
    $candidate->addMediaFromString("%PDF-1.4\ntrailer<</Root 1 0 R>>\n%%EOF")
        ->usingFileName('cv.pdf')
        ->toMediaCollection('cv');

    return Admission::factory()->create(['candidate_id' => $candidate->id]);
}

/*
|--------------------------------------------------------------------------
| Création : l'admission naît du dossier OPCO (créé/transmis), jamais avant
|--------------------------------------------------------------------------
*/

it('ne crée plus d\'admission à la création du candidat (fin de la pré-admission)', function () {
    $candidate = Candidate::factory()->create();

    expect(Admission::query()->where('candidate_id', $candidate->id)->exists())->toBeFalse();
});

it('ouvre automatiquement l\'admission « À vérifier » quand le dossier OPCO est créé/transmis', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Signe,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);

    // Ouverture du dossier OPCO à la signature : préparation → pas d'admission.
    $contract->ouvrirDossierOpco();
    expect(Admission::query()->where('contract_id', $contract->id)->exists())->toBeFalse();

    // Dossier prêt au dépôt (« créé ») → admission officielle ouverte.
    $contract->opcoFile->transitionTo(OpcoStatut::PretDepot);

    $admission = Admission::query()->where('contract_id', $contract->id)->first();
    expect($admission)->not->toBeNull()
        ->and($admission->statut)->toBe(AdmissionStatut::AVerifier)
        ->and($admission->candidate_id)->toBe($contract->candidate_id);
});

it('n\'ouvre qu\'une seule admission par contrat (transitions OPCO rejouées)', function () {
    $admission = admissionSansCv();
    $opco = $admission->contract->opcoFile;

    // Rejouer une transition qui redéclenche l'ouverture : aucun doublon.
    $opco->transitionTo(OpcoStatut::Depose);

    expect(Admission::query()->where('contract_id', $admission->contract_id)->count())->toBe(1);
});

it('refuse de créer une admission si le contrat n\'est pas signé par les trois parties', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Brouillon,
        'statut_signature' => ContractSignatureStatut::NonSigne,
    ]);

    expect(fn () => Admission::query()->create(['contract_id' => $contract->id]))
        ->toThrow(ValidationException::class);
});

it('refuse de créer une admission tant que le dossier OPCO n\'est pas créé ou transmis', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Signe,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);
    $contract->ouvrirDossierOpco(); // reste « À préparer »

    expect(fn () => Admission::query()->create(['contract_id' => $contract->id]))
        ->toThrow(ValidationException::class);
});

it('conserve l\'admission même si l\'OPCO rejette le dossier ensuite', function () {
    $admission = admissionSansCv();
    $opco = $admission->contract->opcoFile;

    $opco->transitionTo(OpcoStatut::Depose);
    $opco->transitionTo(OpcoStatut::AttenteRetour);
    $opco->forceFill(['motif_rejet' => 'Pièces incomplètes.'])->save();
    $opco->transitionTo(OpcoStatut::Rejete);

    expect($admission->fresh())->not->toBeNull()
        ->and($admission->fresh()->statut)->toBe(AdmissionStatut::AVerifier);
});

/*
|--------------------------------------------------------------------------
| Statuts : À vérifier → Validé / Rupture, validation gardée par le CV
|--------------------------------------------------------------------------
*/

it('interdit la validation tant que le CV est manquant', function () {
    $admission = admissionSansCv();

    expect($admission->estComplet())->toBeFalse()
        ->and($admission->canTransitionTo(AdmissionStatut::Valide))->toBeFalse();

    expect(fn () => $admission->transitionTo(AdmissionStatut::Valide))
        ->toThrow(InvalidTransitionException::class);

    expect($admission->fresh()->statut)->toBe(AdmissionStatut::AVerifier);
});

it('autorise la validation quand le CV est fourni', function () {
    $admission = admissionAvecCv();

    expect($admission->estComplet())->toBeTrue()
        ->and($admission->canTransitionTo(AdmissionStatut::Valide))->toBeTrue();

    $admission->transitionTo(AdmissionStatut::Valide);

    $admission->refresh();
    expect($admission->statut)->toBe(AdmissionStatut::Valide)
        ->and($admission->validated_at)->not->toBeNull()
        ->and($admission->validated_by)->toBe(auth()->id());
});

it('limite les statuts à « À vérifier », « Validé » et « Rupture »', function () {
    expect(array_map(fn (AdmissionStatut $s) => $s->value, AdmissionStatut::cases()))
        ->toBe(['a_verifier', 'valide', 'rupture']);
});

it('passe en Rupture et ouvre automatiquement le dossier de rupture lié', function () {
    $admission = admissionAvecCv();

    $admission->transitionTo(AdmissionStatut::Rupture);

    expect($admission->fresh()->statut)->toBe(AdmissionStatut::Rupture)
        ->and($admission->contract->rupture)->not->toBeNull()
        ->and($admission->contract->rupture->statut)->toBe(RuptureStatut::ATraiter)
        ->and($admission->contract->refresh()->statut_contrat)->toBe(ContractStatut::Rompu);
});

it('refuse toute transition depuis l\'état terminal Rupture', function () {
    $admission = admissionAvecCv();
    $admission->transitionTo(AdmissionStatut::Rupture);

    expect($admission->fresh()->allowedTransitions())->toBe([]);
    expect(fn () => $admission->fresh()->transitionTo(AdmissionStatut::AVerifier))
        ->toThrow(InvalidTransitionException::class);
});

it('réserve les admissions aux rôles autorisés', function () {
    $admission = User::factory()->create();
    $admission->syncRoles(['Admission']);
    $this->actingAs($admission);
    expect(AdmissionResource::canAccess())->toBeTrue();

    $commercial = User::factory()->create();
    $commercial->syncRoles(['Commercial']);
    $this->actingAs($commercial);
    expect(AdmissionResource::canAccess())->toBeFalse();
});
