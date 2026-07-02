<?php

use App\Enums\CandidateStatut;
use App\Enums\ContractStatut;
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

/** Contrat actif complet (candidat signé + OPCO + ligne financière) prêt à rompre. */
function contratActif(): Contract
{
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::ContratSigne]);
    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Actif,
        'candidate_id' => $candidate->id,
    ]);
    OpcoFile::factory()->create(['contract_id' => $contract->id]);
    FinanceLine::factory()->create(['contract_id' => $contract->id]);

    return $contract;
}

it('bascule le contrat en Rompu à l\'ouverture de la rupture', function () {
    $contract = contratActif();

    Rupture::factory()->create(['contract_id' => $contract->id]);

    expect($contract->refresh()->statut_contrat)->toBe(ContractStatut::Rompu);
});

it('bascule le candidat en Rupture', function () {
    $contract = contratActif();

    Rupture::factory()->create(['contract_id' => $contract->id]);

    expect($contract->candidate->refresh()->statut)->toBe(CandidateStatut::Rupture);
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

it('suit le cycle d\'accompagnement jusqu\'à la clôture', function () {
    $contract = contratActif();
    $rupture = Rupture::factory()->create(['contract_id' => $contract->id]);

    $rupture->transitionTo(RuptureStatut::EnAccompagnement);
    $rupture->transitionTo(RuptureStatut::RechercheEmployeur);
    $rupture->transitionTo(RuptureStatut::Reclasse);
    $rupture->transitionTo(RuptureStatut::Cloturee);

    expect($rupture->refresh()->statut)->toBe(RuptureStatut::Cloturee);
});

it('refuse une transition d\'accompagnement non autorisée', function () {
    $contract = contratActif();
    $rupture = Rupture::factory()->create(['contract_id' => $contract->id]);

    // Ouverte → Reclasse directement n'est pas permis.
    expect(fn () => $rupture->transitionTo(RuptureStatut::Reclasse))
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
