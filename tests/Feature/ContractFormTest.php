<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('monte le formulaire de création de contrat (CERFA + champs requis) sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administratif']);
    $this->actingAs($user);

    Livewire::test(CreateContract::class)->assertSuccessful();
});

it('fait évoluer le statut du contrat depuis le formulaire d\'édition (via la machine à états)', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administratif']);
    $this->actingAs($user);

    $company = Company::factory()->create();
    $tuteur = CompanyContact::factory()->create(['company_id' => $company->id]);
    $contract = Contract::factory()->create([
        'company_id' => $company->id,
        'tuteur_id' => $tuteur->id,
        'statut_contrat' => ContractStatut::ManqueSignature,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm(['statut_contrat' => ContractStatut::Complet->value])
        ->call('save')
        ->assertHasNoFormErrors();

    // La transition passe par la machine à états → effet de bord : dossier OPCO ouvert.
    $fresh = $contract->fresh();
    expect($fresh->statut_contrat)->toBe(ContractStatut::Complet)
        ->and($fresh->opcoFile()->exists())->toBeTrue();
});
