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
        // Sous-titre retiré (gain de place) : il ne doit plus apparaître.
        ->assertDontSee('Suivi des candidats et de leur avancement')
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

it('modifie un candidat via le modal d\'édition rapide (clic sur la ligne)', function () {
    $candidat = Candidate::factory()->create([
        'nom' => 'Ancien', 'prenom' => 'Nom', 'email' => 'edit@exemple.fr', 'ville' => 'Paris',
        'telephone' => null, // évite qu'un numéro legacy de la factory bloque le submit
    ]);

    Livewire::test(ListCandidates::class)
        ->callTableAction('modifierLigne', $candidat, data: [
            'nom' => 'Nouveau',
            'prenom' => 'Nom',
            'email' => 'edit@exemple.fr',
            'ville' => 'Lyon',
            'disponibilite' => 'Immédiate',
        ])
        ->assertHasNoTableActionErrors();

    $candidat->refresh();
    expect($candidat->nom)->toBe('Nouveau')
        ->and($candidat->ville)->toBe('Lyon')
        ->and($candidat->disponibilite)->toBe('Immédiate');
});

it('refuse un téléphone sans indicatif dans le modal d\'édition rapide', function () {
    $candidat = Candidate::factory()->create(['email' => 'tel@exemple.fr']);

    Livewire::test(ListCandidates::class)
        ->callTableAction('modifierLigne', $candidat, data: [
            'nom' => 'Test', 'prenom' => 'Tel', 'email' => 'tel@exemple.fr',
            'telephone' => '0612345678', // sans « + » indicatif → rejeté
        ])
        ->assertHasTableActionErrors(['telephone']);
});

it('regroupe les candidats par statut par défaut (board façon Monday)', function () {
    Candidate::factory()->create(['disponibilite' => 'Immédiate']);

    // Ouverture directe sur une vue groupée par statut (groupes repliables).
    Livewire::test(ListCandidates::class)
        ->assertSuccessful()
        ->assertSet('tableGrouping', 'statut:asc');
});

it('groupe les candidats par disponibilité', function () {
    Candidate::factory()->create(['disponibilite' => 'Immédiate']);
    Candidate::factory()->create(['disponibilite' => 'Sous 1 mois']);

    // Le regroupement « disponibilite » est proposé et applicable sans erreur.
    Livewire::test(ListCandidates::class)
        ->set('tableGrouping', 'disponibilite')
        ->assertSuccessful();
});
