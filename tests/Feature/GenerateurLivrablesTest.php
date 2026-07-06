<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Filament\Pages\GenerateurLivrables;
use App\Jobs\GenererLivrablesJob;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lance la génération en tâche de fond depuis le hub', function () {
    $this->seed(RolePermissionSeeder::class);
    config(['services.livretrs.url' => 'http://livretrs.test']);
    Bus::fake();

    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['Administrateur']);

    $candidate = Candidate::factory()->create();
    $contract = Contract::factory()->create([
        'candidate_id' => $candidate->id,
        'statut_signature' => ContractSignatureStatut::Signe,
        'statut_contrat' => ContractStatut::Signe,
    ]);

    Livewire::actingAs($admin)
        ->test(GenerateurLivrables::class)
        ->set('data.contract_id', $contract->id)
        ->set('data.theme_code', 'institutionnel')
        ->set('data.format', 'pdf')
        ->set('data.verifier_rncp', false)
        ->call('generer');

    Bus::assertDispatched(GenererLivrablesJob::class, fn ($job) => $job->contractId === $contract->id);
});

it('refuse la génération pour un contrat non signé (dossier incomplet)', function () {
    $this->seed(RolePermissionSeeder::class);
    config(['services.livretrs.url' => 'http://livretrs.test']);
    Bus::fake();

    $admin = User::factory()->create(['is_active' => true]);
    $admin->syncRoles(['Administrateur']);

    $contract = Contract::factory()->create([
        'candidate_id' => Candidate::factory()->create()->id,
        'statut_signature' => ContractSignatureStatut::NonSigne,
        'statut_contrat' => ContractStatut::Brouillon,
    ]);

    Livewire::actingAs($admin)
        ->test(GenerateurLivrables::class)
        ->set('data.contract_id', $contract->id)
        ->set('data.theme_code', 'institutionnel')
        ->set('data.format', 'pdf')
        ->call('generer');

    Bus::assertNotDispatched(GenererLivrablesJob::class);
});

it('réserve le hub aux rôles ayant accès aux documents', function () {
    $this->seed(RolePermissionSeeder::class);

    $qualite = User::factory()->create(['is_active' => true]);
    $qualite->syncRoles(['Qualité']);
    $this->actingAs($qualite);
    expect(GenerateurLivrables::canAccess())->toBeTrue();

    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles(['Commercial']);
    $this->actingAs($commercial);
    expect(GenerateurLivrables::canAccess())->toBeFalse();
});
