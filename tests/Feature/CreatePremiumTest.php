<?php

use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('public');

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

it('affiche la page « Nouveau candidat » premium (résumé du dossier)', function () {
    Livewire::test(CreateCandidate::class)
        ->assertSuccessful()
        ->assertSee('Nouveau candidat')
        ->assertSee('Résumé du dossier')
        ->assertSee('Complétude du dossier');
});

it('crée toujours un candidat via le formulaire embarqué (create intact)', function () {
    Livewire::test(CreateCandidate::class)
        ->fillForm([
            'nom' => 'Nouveau',
            'prenom' => 'Candidat',
            'email' => 'nouveau.candidat@exemple.fr',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Candidate::query()->where('email', 'nouveau.candidat@exemple.fr')->exists())->toBeTrue();
});

it('affiche la page « Nouvelle entreprise » premium (résumé du dossier)', function () {
    Livewire::test(CreateCompany::class)
        ->assertSuccessful()
        ->assertSee('Nouvelle entreprise')
        ->assertSee('Résumé du dossier');
});

it('crée toujours une entreprise via le formulaire embarqué (create intact)', function () {
    Livewire::test(CreateCompany::class)
        ->fillForm([
            'raison_sociale' => 'Nouvelle Boîte SARL',
            'siret' => '12345678900012',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Company::query()->where('raison_sociale', 'Nouvelle Boîte SARL')->exists())->toBeTrue();
});
