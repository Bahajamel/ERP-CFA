<?php

use App\Enums\CandidateStatut;
use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Filament\Resources\Candidates\Tables\CandidatesTable;
use App\Models\Candidate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function workspaceAdmin(): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles(['Administrateur']);

    return $u;
}

it('rend le workspace candidats avec ses filtres rapides', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(workspaceAdmin());
    Candidate::factory()->count(3)->create(['statut' => CandidateStatut::EntretienAPlanifier]);

    Livewire::test(ListCandidates::class)
        ->assertOk()
        ->assertSee('entretiens à planifier')
        ->assertSee('décisions en attente')
        ->assertSee('acceptés à orienter');
});

it('le filtre « décisions en attente » ne garde que les entretiens réalisés sans décision', function () {
    $this->seed(RolePermissionSeeder::class);
    Candidate::factory()->create(['statut' => CandidateStatut::EntretienRealise, 'nom' => 'Adecider']);
    Candidate::factory()->create(['statut' => CandidateStatut::Accepte, 'nom' => 'Accepte']);
    Candidate::factory()->create(['statut' => CandidateStatut::EntretienAPlanifier, 'nom' => 'Aplanifier']);

    $count = Candidate::query()
        ->tap(fn ($q) => CandidatesTable::appliquerScopeRapide($q, 'a_decider'))
        ->count();

    expect($count)->toBe(1);
});

it('n\'affiche le panneau Focus qu\'après sélection d\'un candidat', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(workspaceAdmin());
    $c = Candidate::factory()->create(['prenom' => 'Raslen', 'nom' => 'Saadi']);

    // Rien de sélectionné : pas de panneau.
    Livewire::test(ListCandidates::class)
        ->assertDontSee('Focus du jour')
        // Sélection → le panneau apparaît.
        ->set('focusId', $c->id)
        ->assertSee('Focus du jour')
        ->assertSee('Raslen Saadi')
        // Fermeture → le panneau disparaît.
        ->call('unfocus')
        ->assertSet('focusId', null)
        ->assertDontSee('Focus du jour');
});

it('bascule le filtre rapide et le désactive au second clic', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(workspaceAdmin());

    Livewire::test(ListCandidates::class)
        ->assertSet('quickScope', null)
        ->call('setQuickScope', 'a_planifier')
        ->assertSet('quickScope', 'a_planifier')
        ->call('setQuickScope', 'a_planifier')
        ->assertSet('quickScope', null);
});

it('le filtre rapide « à planifier » ne garde que les candidats à planifier', function () {
    $this->seed(RolePermissionSeeder::class);
    Candidate::factory()->create(['statut' => CandidateStatut::EntretienAPlanifier, 'nom' => 'Aplanifier']);
    Candidate::factory()->create(['statut' => CandidateStatut::Accepte, 'nom' => 'Accepte']);

    $count = Candidate::query()
        ->tap(fn ($q) => CandidatesTable::appliquerScopeRapide($q, 'a_planifier'))
        ->count();

    expect($count)->toBe(1);
});

it('un clic sur une ligne (action focus) ouvre le panneau sans quitter la page', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(workspaceAdmin());
    $c = Candidate::factory()->create(['prenom' => 'Raslen', 'nom' => 'Saadi']);

    Livewire::test(ListCandidates::class)
        ->callTableAction('focus', $c)
        ->assertSet('focusId', $c->id)
        ->assertSee('Focus du jour');
});

it('le panneau Focus cible le candidat sélectionné', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(workspaceAdmin());
    $cible = Candidate::factory()->create(['prenom' => 'Raslen', 'nom' => 'Saadi']);
    Candidate::factory()->count(2)->create();

    $page = new ListCandidates;
    $page->focusId = $cible->id;

    expect($page->getFocusCandidate()->id)->toBe($cible->id);
});

it('propose un entretien pour un nouveau candidat', function () {
    $this->seed(RolePermissionSeeder::class);
    $c = Candidate::factory()->create(['statut' => CandidateStatut::EntretienAPlanifier]);

    expect($c->parcoursFocus()['cle'])->toBe('entretien');
});

it('ne propose PAS un entretien pour un candidat accepté déjà en matching', function () {
    $this->seed(RolePermissionSeeder::class);
    $c = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);
    \App\Models\Matching::factory()->create([
        'candidate_id' => $c->id,
        'statut' => \App\Enums\MatchingStatut::EnRecherche,
    ]);

    $focus = $c->parcoursFocus();

    expect($focus['cle'])->toBe('matching')
        ->and($focus['cle'])->not->toBe('entretien');
});

it('calcule les pièces manquantes et la progression du candidat', function () {
    $this->seed(RolePermissionSeeder::class);
    $c = Candidate::factory()->create(['statut' => CandidateStatut::EntretienAPlanifier]);

    // Aucune pièce déposée → les trois pièces attendues manquent.
    expect($c->piecesManquantes())->toHaveCount(3);

    $etapes = collect($c->progressionEtapes());
    expect($etapes->firstWhere('cle', 'candidature')['etat'])->toBe('done')
        ->and($etapes->firstWhere('cle', 'entretien')['etat'])->toBe('current')
        ->and($etapes->firstWhere('cle', 'admission')['etat'])->toBe('todo');
});
