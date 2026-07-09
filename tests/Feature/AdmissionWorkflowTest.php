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
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create());
});

/** Admission officielle prête à valider (chaîne contrat signé + OPCO accepté). */
function admissionPrete(): Admission
{
    return Admission::factory()->create();
}

/*
|--------------------------------------------------------------------------
| Création : l'admission naît de l'acceptation OPCO, jamais avant
|--------------------------------------------------------------------------
*/

it('ne crée plus d\'admission à la création du candidat (fin de la pré-admission)', function () {
    $candidate = Candidate::factory()->create();

    expect(Admission::query()->where('candidate_id', $candidate->id)->exists())->toBeFalse();
});

it('n\'ouvre pas l\'admission tant que le dossier OPCO n\'est pas accepté', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);

    // Dossier OPCO ouvert puis déposé : toujours pas d'admission (pas accepté).
    $contract->ouvrirDossierOpco();
    $contract->opcoFile->transitionTo(OpcoStatut::PretDepot);
    $contract->opcoFile->transitionTo(OpcoStatut::Depose);
    $contract->opcoFile->transitionTo(OpcoStatut::AttenteRetour);

    expect(Admission::query()->where('contract_id', $contract->id)->exists())->toBeFalse();
});

it('ouvre automatiquement l\'admission « À vérifier » dès que l\'OPCO accepte', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);

    $contract->ouvrirDossierOpco();
    $contract->opcoFile->transitionTo(OpcoStatut::PretDepot);
    $contract->opcoFile->transitionTo(OpcoStatut::Depose);
    $contract->opcoFile->transitionTo(OpcoStatut::AttenteRetour);
    $contract->opcoFile->transitionTo(OpcoStatut::Accepte); // financement validé

    $admission = Admission::query()->where('contract_id', $contract->id)->first();
    expect($admission)->not->toBeNull()
        ->and($admission->statut)->toBe(AdmissionStatut::AVerifier)
        ->and($admission->candidate_id)->toBe($contract->candidate_id);
});

it('n\'ouvre qu\'une seule admission par contrat (transitions OPCO rejouées)', function () {
    $admission = admissionPrete();
    $opco = $admission->contract->opcoFile;

    // Rejouer une transition qui redéclenche l'ouverture : aucun doublon.
    $opco->transitionTo(OpcoStatut::Cloture);

    expect(Admission::query()->where('contract_id', $admission->contract_id)->count())->toBe(1);
});

it('refuse de créer une admission si le contrat n\'est pas signé par les trois parties', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::EnCours,
        'statut_signature' => ContractSignatureStatut::NonSigne,
    ]);

    expect(fn () => Admission::query()->create(['contract_id' => $contract->id]))
        ->toThrow(ValidationException::class);
});

it('refuse de créer une admission tant que le dossier OPCO n\'est pas accepté', function () {
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);
    $contract->ouvrirDossierOpco(); // reste « À préparer »

    expect(fn () => Admission::query()->create(['contract_id' => $contract->id]))
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| Statuts : À vérifier → Validé / Rupture (plus de garde CV)
|--------------------------------------------------------------------------
*/

it('valide l\'admission sans exiger de CV (OPCO déjà accepté)', function () {
    $admission = admissionPrete();

    expect($admission->canTransitionTo(AdmissionStatut::Valide))->toBeTrue();

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
    $admission = admissionPrete();

    $admission->transitionTo(AdmissionStatut::Rupture);

    expect($admission->fresh()->statut)->toBe(AdmissionStatut::Rupture)
        ->and($admission->contract->rupture)->not->toBeNull()
        ->and($admission->contract->rupture->statut)->toBe(RuptureStatut::ATraiter)
        ->and($admission->contract->refresh()->statut_contrat)->toBe(ContractStatut::Rompu);
});

it('refuse toute transition depuis l\'état terminal Rupture', function () {
    $admission = admissionPrete();
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
