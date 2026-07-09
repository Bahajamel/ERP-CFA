<?php

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\OpcoStatut;
use App\Filament\Resources\OpcoFiles\Pages\EditOpcoFile;
use App\Models\Contract;
use App\Models\OpcoFile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('fait évoluer le statut du dossier OPCO depuis le formulaire d\'édition (via la machine à états)', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administratif']);
    $this->actingAs($user);

    $contract = Contract::factory()->create([
        'statut_contrat' => ContractStatut::Complet,
        'statut_signature' => ContractSignatureStatut::Signe,
    ]);
    $opco = OpcoFile::create([
        'contract_id' => $contract->id,
        'statut' => OpcoStatut::APreparer->value,
    ]);

    Livewire::test(EditOpcoFile::class, ['record' => $opco->getRouteKey()])
        ->fillForm(['statut' => OpcoStatut::PretDepot->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($opco->fresh()->statut)->toBe(OpcoStatut::PretDepot);
});
