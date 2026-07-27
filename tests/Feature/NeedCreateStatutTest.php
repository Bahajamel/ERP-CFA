<?php

use App\Enums\NeedStatut;
use App\Filament\Resources\Needs\Pages\CreateNeed;
use App\Models\Company;
use App\Models\Need;
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

it('n\'affiche pas le champ Statut à la création d\'une offre', function () {
    Livewire::test(CreateNeed::class)
        ->assertFormFieldIsHidden('statut');
});

it('crée une offre en « Besoin créé » sans avoir à choisir le statut', function () {
    $company = Company::factory()->create();

    Livewire::test(CreateNeed::class)
        ->fillForm([
            'company_id' => $company->id,
            'intitule_poste' => 'Alternance développeur web',
            'nb_postes' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $need = Need::query()->where('intitule_poste', 'Alternance développeur web')->first();

    expect($need)->not->toBeNull()
        ->and($need->statut)->toBe(NeedStatut::Cree);
});
