<?php

use App\Filament\Resources\Formations\Pages\ListFormations;
use App\Models\Formation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminFormations(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);

    return $user;
}

it('persiste un RNCP inactif lors de la vérification', function () {
    $this->seed(RolePermissionSeeder::class);
    config(['services.livretrs.url' => 'http://livretrs.test']);
    Http::fake(['*' => Http::response([
        'found' => true, 'actif' => false, 'etat' => 'Inactive', 'intitule' => 'Moniteur sportif', 'niveau' => '4',
    ], 200)]);

    $formation = Formation::factory()->create(['code_rncp' => 'RNCP34061']);

    Livewire::actingAs(adminFormations())
        ->test(ListFormations::class)
        ->callTableAction('verifierRncp', $formation);

    $formation->refresh();

    expect($formation->rncp_actif)->toBeFalse()
        ->and($formation->rncp_etat)->toBe('Inactive')
        ->and($formation->rncp_niveau)->toBe('4')
        ->and($formation->rncp_verifie_at)->not->toBeNull();
});

it('persiste un RNCP valide lors de la vérification', function () {
    $this->seed(RolePermissionSeeder::class);
    config(['services.livretrs.url' => 'http://livretrs.test']);
    Http::fake(['*' => Http::response([
        'found' => true, 'actif' => true, 'etat' => 'Active', 'intitule' => 'BTS MCO', 'niveau' => '5',
    ], 200)]);

    $formation = Formation::factory()->create(['code_rncp' => 'RNCP38362']);

    Livewire::actingAs(adminFormations())
        ->test(ListFormations::class)
        ->callTableAction('verifierRncp', $formation);

    expect($formation->refresh()->rncp_actif)->toBeTrue();
});
