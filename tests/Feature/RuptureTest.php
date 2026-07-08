<?php

use App\Enums\AdmissionStatut;
use App\Enums\CandidateStatut;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Filament\Resources\Ruptures\Pages\CreateRupture;
use App\Filament\Resources\Ruptures\RuptureResource;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\FinanceLine;
use App\Models\OpcoFile;
use App\Models\Rupture;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Contrat actif complet (candidat accepté, signé, OPCO déposé, finance) prêt à rompre. */
function contratActif(): Contract
{
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Actif,
        'statut_signature' => ContractSignatureStatut::Signe,
        'candidate_id' => $candidate->id,
    ]);
    // Dossier OPCO déposé → l'admission officielle « À vérifier » s'ouvre.
    OpcoFile::factory()->create(['contract_id' => $contract->id, 'statut' => OpcoStatut::Depose, 'motif_rejet' => null]);
    FinanceLine::factory()->create(['contract_id' => $contract->id]);

    return $contract;
}

it('bascule le contrat en Rompu à l\'ouverture de la rupture', function () {
    $contract = contratActif();

    Rupture::factory()->create(['contract_id' => $contract->id]);

    expect($contract->refresh()->statut_contrat)->toBe(ContractStatut::Rompu);
});

it('bascule l\'admission officielle en Rupture (le statut candidat reste acquis)', function () {
    $contract = contratActif();

    Rupture::factory()->create(['contract_id' => $contract->id]);

    expect($contract->admission()->first()->statut)->toBe(AdmissionStatut::Rupture)
        ->and($contract->candidate->refresh()->statut)->toBe(CandidateStatut::Accepte);
});

it('crée les actions de suivi OPCO et finance (traces + preuves)', function () {
    $contract = contratActif();

    $rupture = Rupture::factory()->create(['contract_id' => $contract->id]);

    expect($contract->opcoFile->tasks()->where('cle', "rupture:opco:{$rupture->id}")->exists())->toBeTrue()
        ->and($contract->tasks()->where('cle', "rupture:finance:{$rupture->id}")->exists())->toBeTrue();
});

it('ne duplique pas les actions si la propagation est rejouée (idempotence)', function () {
    $contract = contratActif();
    $rupture = Rupture::factory()->create(['contract_id' => $contract->id]);

    $rupture->propagerTraces(); // rejoue

    expect($contract->tasks()->where('cle', "rupture:finance:{$rupture->id}")->count())->toBe(1);
});

it('ne crée qu\'un seul dossier de rupture par contrat (unicité)', function () {
    $contract = contratActif();
    Rupture::factory()->create(['contract_id' => $contract->id]);

    expect(fn () => Rupture::factory()->create(['contract_id' => $contract->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('suit le traitement administratif : à traiter → documents générés → clôturée', function () {
    $contract = contratActif();
    $rupture = Rupture::factory()->create(['contract_id' => $contract->id]);

    expect($rupture->statut)->toBe(RuptureStatut::ATraiter);

    $rupture->transitionTo(RuptureStatut::DocumentsGeneres);
    $rupture->transitionTo(RuptureStatut::Cloturee);

    expect($rupture->refresh()->statut)->toBe(RuptureStatut::Cloturee);
});

it('refuse une transition de traitement non autorisée', function () {
    $contract = contratActif();
    $rupture = Rupture::factory()->create(['contract_id' => $contract->id]);

    $rupture->transitionTo(RuptureStatut::Cloturee);

    // Clôturée est terminale : aucun retour possible.
    expect(fn () => $rupture->fresh()->transitionTo(RuptureStatut::ATraiter))
        ->toThrow(App\StateMachine\InvalidTransitionException::class);
});

it('réserve le module rupture aux profils autorisés', function () {
    $this->seed(RolePermissionSeeder::class);

    $pedagogie = User::factory()->create(['is_active' => true]);
    $pedagogie->syncRoles(['Pédagogie']);
    $this->actingAs($pedagogie);
    expect(RuptureResource::canAccess())->toBeTrue();

    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles(['Commercial']);
    $this->actingAs($commercial);
    expect(RuptureResource::canAccess())->toBeFalse();
});

it('ouvre une rupture depuis le formulaire et propage la trace au contrat', function () {
    $this->seed(RolePermissionSeeder::class);
    $contract = contratActif();

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Pédagogie']);
    $this->actingAs($user);

    Livewire::test(CreateRupture::class)
        ->fillForm([
            'contract_id' => $contract->id,
            'date_rupture' => now()->toDateString(),
            'motif' => RuptureMotif::Demission->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($contract->refresh()->statut_contrat)->toBe(ContractStatut::Rompu)
        ->and(Rupture::where('contract_id', $contract->id)->value('created_by'))->toBe($user->id);
});
