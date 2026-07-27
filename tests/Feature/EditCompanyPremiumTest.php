<?php

use App\Filament\Resources\Companies\Pages\EditCompany;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

it('affiche la page « Modifier » premium (carte résumé + activité)', function () {
    $company = Company::factory()->create(['raison_sociale' => 'Webtech Solutions']);

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Modifier les informations')
        ->assertSee('Besoins ouverts')
        ->assertSee('Webtech Solutions');
});

it('enregistre les modifications via le formulaire embarqué (save intact)', function () {
    $company = Company::factory()->create(['raison_sociale' => 'Ancien Nom', 'siret' => '12345678900012']);

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->fillForm([
            'raison_sociale' => 'Nouveau Nom',
            'nom_commercial' => 'NouvelleMarque',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($company->fresh()->raison_sociale)->toBe('Nouveau Nom')
        ->and($company->fresh()->nom_commercial)->toBe('NouvelleMarque');
});

it('conserve la validation existante (raison sociale requise)', function () {
    $company = Company::factory()->create();

    Livewire::test(EditCompany::class, ['record' => $company->getRouteKey()])
        ->fillForm(['raison_sociale' => null])
        ->call('save')
        ->assertHasFormErrors(['raison_sociale']);
});
