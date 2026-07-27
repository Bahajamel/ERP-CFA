<?php

use App\Filament\Resources\Companies\Pages\CreateCompany;
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

it('interdit la création d\'une entreprise dont le SIRET est déjà enregistré (active)', function () {
    Company::factory()->create(['raison_sociale' => 'Déjà Là SARL', 'siret' => '55208131766522']);

    Livewire::test(CreateCompany::class)
        ->fillForm([
            'raison_sociale' => 'Tentative Doublon',
            'siret' => '55208131766522',
        ])
        ->call('create')
        ->assertHasFormErrors(['siret']);

    expect(Company::query()->where('raison_sociale', 'Tentative Doublon')->exists())->toBeFalse();
});

it('interdit la création d\'une entreprise dont le SIRET est en corbeille', function () {
    $archivee = Company::factory()->create(['raison_sociale' => 'Archivée SARL', 'siret' => '38012986643097']);
    $archivee->delete(); // corbeille (soft delete)

    Livewire::test(CreateCompany::class)
        ->fillForm([
            'raison_sociale' => 'Tentative Corbeille',
            'siret' => '38012986643097',
        ])
        ->call('create')
        ->assertHasFormErrors(['siret']);

    expect(Company::query()->where('raison_sociale', 'Tentative Corbeille')->exists())->toBeFalse();
});

it('autorise la création avec un SIRET inédit', function () {
    Livewire::test(CreateCompany::class)
        ->fillForm([
            'raison_sociale' => 'Toute Neuve SARL',
            'siret' => '55204944776279',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Company::query()->where('raison_sociale', 'Toute Neuve SARL')->exists())->toBeTrue();
});
