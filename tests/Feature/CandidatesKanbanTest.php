<?php

use App\Enums\CandidateStatut;
use App\Filament\Resources\Candidates\Pages\CandidatesKanban;
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

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Commercial'); // dispose de la permission access_candidates
    $this->actingAs($user);
});

it('groupe les candidats par statut dans les colonnes', function () {
    Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);
    Candidate::factory()->count(2)->create(['statut' => CandidateStatut::EnRechercheEntreprise]);

    $columns = Livewire::test(CandidatesKanban::class)->instance()->getColumns();
    $parStatut = collect($columns)->keyBy(fn (array $c): string => $c['statut']->value);

    expect($parStatut[CandidateStatut::Incomplet->value]['candidates'])->toHaveCount(1)
        ->and($parStatut[CandidateStatut::EnRechercheEntreprise->value]['candidates'])->toHaveCount(2);
});

it('déplace une carte vers un statut autorisé (transition appliquée)', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);

    Livewire::test(CandidatesKanban::class)
        ->call('moveCard', $candidate->id, CandidateStatut::EnRechercheEntreprise->value)
        ->assertNotified();

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::EnRechercheEntreprise);
});

it('refuse un déplacement interdit et conserve le statut', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);

    Livewire::test(CandidatesKanban::class)
        ->call('moveCard', $candidate->id, CandidateStatut::ContratSigne->value)
        ->assertNotified();

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Incomplet);
});

it('ignore un dépôt dans la même colonne (aucune transition tentée)', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);

    Livewire::test(CandidatesKanban::class)
        ->call('moveCard', $candidate->id, CandidateStatut::Incomplet->value);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Incomplet);
});
