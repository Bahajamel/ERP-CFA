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
    Candidate::factory()->create(['statut' => CandidateStatut::EntretienPrevu]);
    Candidate::factory()->count(2)->create(['statut' => CandidateStatut::Accepte]);

    $columns = Livewire::test(CandidatesKanban::class)->instance()->getColumns();
    $parStatut = collect($columns)->keyBy(fn (array $c): string => $c['statut']->value);

    expect($parStatut[CandidateStatut::EntretienPrevu->value]['candidates'])->toHaveCount(1)
        ->and($parStatut[CandidateStatut::Accepte->value]['candidates'])->toHaveCount(2);
});

it('déplace une carte vers un statut autorisé (transition appliquée)', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::EntretienPrevu]);

    Livewire::test(CandidatesKanban::class)
        ->call('moveCard', $candidate->id, CandidateStatut::Accepte->value)
        ->assertNotified();

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Accepte);
});

it('refuse un déplacement interdit (retour à Entretien prévu) et conserve le statut', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);

    Livewire::test(CandidatesKanban::class)
        ->call('moveCard', $candidate->id, CandidateStatut::EntretienPrevu->value)
        ->assertNotified();

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Accepte);
});

it('ignore un dépôt dans la même colonne (aucune transition tentée)', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::EntretienPrevu]);

    Livewire::test(CandidatesKanban::class)
        ->call('moveCard', $candidate->id, CandidateStatut::EntretienPrevu->value);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::EntretienPrevu);
});
