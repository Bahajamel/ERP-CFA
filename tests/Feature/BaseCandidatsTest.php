<?php

use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Models\Candidate;
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

it('affiche la page « Base Candidats » avec les colonnes de la maquette', function () {
    Candidate::factory()->create(['nom' => 'Benali', 'prenom' => 'Inès', 'ville' => 'Lyon', 'disponibilite' => 'Immédiate']);

    Livewire::test(ListCandidates::class)
        ->assertSuccessful()
        ->assertSee('Base Candidats')
        ->assertSee('Suivi des candidats et de leur avancement')
        ->assertCanRenderTableColumn('identite')
        ->assertCanRenderTableColumn('statut')
        ->assertCanRenderTableColumn('ville')
        ->assertCanRenderTableColumn('disponibilite')
        ->assertCanRenderTableColumn('documents_count');
});

it('recherche un candidat par ville', function () {
    Candidate::factory()->create(['nom' => 'Martin', 'prenom' => 'Lucas', 'ville' => 'Nantes']);
    Candidate::factory()->create(['nom' => 'Dubois', 'prenom' => 'Camille', 'ville' => 'Strasbourg']);

    Livewire::test(ListCandidates::class)
        ->searchTable('Nantes')
        ->assertCanSeeTableRecords(Candidate::query()->where('ville', 'Nantes')->get())
        ->assertCanNotSeeTableRecords(Candidate::query()->where('ville', 'Strasbourg')->get());
});

it('groupe les candidats par disponibilité', function () {
    Candidate::factory()->create(['disponibilite' => 'Immédiate']);
    Candidate::factory()->create(['disponibilite' => 'Sous 1 mois']);

    // Le regroupement « disponibilite » est proposé et applicable sans erreur.
    Livewire::test(ListCandidates::class)
        ->set('tableGrouping', 'disponibilite')
        ->assertSuccessful();
});
