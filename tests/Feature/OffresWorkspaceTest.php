<?php

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Filament\Resources\Needs\Pages\ListNeeds;
use App\Filament\Resources\Needs\Tables\NeedsTable;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function offresWorkspaceAdmin(): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles(['Administrateur']);

    return $u;
}

it('rend le workspace des offres proposées avec ses filtres rapides', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(offresWorkspaceAdmin());
    Need::factory()->count(3)->create(['statut' => NeedStatut::ProfilsRecherches]);

    Livewire::test(ListNeeds::class)
        ->assertOk()
        ->assertSee('offres à pourvoir')
        ->assertSee('offres sans candidat')
        ->assertSee('offres en cours de matching');
});

it('bascule le filtre rapide et le désactive au second clic', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(offresWorkspaceAdmin());

    Livewire::test(ListNeeds::class)
        ->assertSet('quickScope', null)
        ->call('setQuickScope', 'sans_candidat')
        ->assertSet('quickScope', 'sans_candidat')
        ->call('setQuickScope', 'sans_candidat')
        ->assertSet('quickScope', null);
});

it('le filtre « sans candidat » ne garde que les offres ouvertes sans matching', function () {
    $this->seed(RolePermissionSeeder::class);

    $avecCandidat = Need::factory()->create(['statut' => NeedStatut::ProfilsRecherches]);
    Matching::factory()->create([
        'need_id' => $avecCandidat->id,
        'candidate_id' => Candidate::factory()->create(['statut' => CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);
    Need::factory()->create(['statut' => NeedStatut::ProfilsRecherches]); // sans candidat

    $count = Need::query()
        ->tap(fn ($q) => NeedsTable::appliquerScopeRapide($q, 'sans_candidat'))
        ->count();

    expect($count)->toBe(1);
});

it('affiche le nombre de candidatures par offre', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(offresWorkspaceAdmin());

    $need = Need::factory()->create([
        'intitule_poste' => 'Boulanger en alternance',
        'statut' => NeedStatut::ProfilsRecherches,
    ]);
    Matching::factory()->count(2)->create([
        'need_id' => $need->id,
        'candidate_id' => fn () => Candidate::factory()->create(['statut' => CandidateStatut::Accepte])->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);

    Livewire::test(ListNeeds::class)
        ->assertOk()
        ->assertSee('Boulanger en alternance')
        ->assertSee('2 candidats');
});
