<?php

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Livewire\ProposerCandidatsModal;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Matching;
use App\Models\Need;
use App\Models\Task;
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
    $this->user->syncRoles('Commercial');
    $this->actingAs($this->user);
});

/** Un besoin + un candidat accepté compatible (même formation). */
function besoinAvecCandidat(): array
{
    $formation = Formation::factory()->create();
    $company = Company::factory()->create(['raison_sociale' => 'Restaurant Alpha']);
    $need = Need::factory()->create([
        'company_id' => $company->id,
        'formation_id' => $formation->id,
        'intitule_poste' => 'Employé Polyvalent en Restauration',
        'nb_postes' => 2,
    ]);
    $candidate = Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::Accepte->value,
    ]);

    return [$need, $candidate];
}

it('affiche les candidats compatibles du besoin', function () {
    [$need, $candidate] = besoinAvecCandidat();

    Livewire::test(ProposerCandidatsModal::class, ['needId' => $need->id])
        ->assertSuccessful()
        ->assertSee($candidate->nom_complet)
        ->assertSee('Restaurant Alpha')
        ->assertSee('Résumé de la proposition');
});

it('valide la proposition : matching « Proposition envoyée » + tâche de relance', function () {
    [$need, $candidate] = besoinAvecCandidat();

    Livewire::test(ProposerCandidatsModal::class, ['needId' => $need->id])
        ->set('selection', [$candidate->id])
        ->set('canal', 'email')
        ->set('dateRelance', now()->addDays(7)->format('Y-m-d'))
        ->set('responsableId', $this->user->id)
        ->set('commentaire', 'Profil prometteur')
        ->call('valider');

    $matching = Matching::where('need_id', $need->id)->where('candidate_id', $candidate->id)->first();

    expect($matching)->not->toBeNull()
        ->and($matching->statut)->toBe(MatchingStatut::PropositionEnvoyee)
        ->and($matching->canal)->toBe('email')
        ->and($matching->responsable_suivi_id)->toBe($this->user->id)
        ->and($matching->commentaire_interne)->toBe('Profil prometteur')
        ->and($matching->next_action_at->format('Y-m-d'))->toBe(now()->addDays(7)->format('Y-m-d'))
        ->and($matching->date_proposition)->not->toBeNull();

    // Tâche de relance créée, assignée au responsable.
    $tache = Task::where('taskable_type', $matching->getMorphClass())->where('taskable_id', $matching->id)->first();
    expect($tache)->not->toBeNull()
        ->and($tache->assignee_id)->toBe($this->user->id)
        ->and($tache->due_date->format('Y-m-d'))->toBe(now()->addDays(7)->format('Y-m-d'));
});

it('enregistre un brouillon en « En recherche » sans tâche de relance', function () {
    [$need, $candidate] = besoinAvecCandidat();

    Livewire::test(ProposerCandidatsModal::class, ['needId' => $need->id])
        ->set('selection', [$candidate->id])
        ->call('brouillon');

    $matching = Matching::first();
    expect($matching->statut)->toBe(MatchingStatut::EnRecherche)
        ->and($matching->next_action_at)->toBeNull()
        ->and(Task::count())->toBe(0);
});

it('refuse la validation sans candidat sélectionné', function () {
    [$need] = besoinAvecCandidat();

    Livewire::test(ProposerCandidatsModal::class, ['needId' => $need->id])
        ->set('selection', [])
        ->call('valider');

    expect(Matching::count())->toBe(0);
});

it('refuse la validation sans date de relance', function () {
    [$need, $candidate] = besoinAvecCandidat();

    Livewire::test(ProposerCandidatsModal::class, ['needId' => $need->id])
        ->set('selection', [$candidate->id])
        ->set('dateRelance', null)
        ->call('valider');

    expect(Matching::count())->toBe(0);
});

it('ignore un candidat déjà proposé sur ce besoin (pas de doublon)', function () {
    [$need, $candidate] = besoinAvecCandidat();
    // Déjà proposé.
    $need->matchings()->create([
        'candidate_id' => $candidate->id,
        'statut' => MatchingStatut::EnRecherche->value,
        'assigned_by' => $this->user->id,
    ]);

    // Il ne doit plus apparaître dans les compatibles.
    Livewire::test(ProposerCandidatsModal::class, ['needId' => $need->id])
        ->assertDontSee($candidate->nom_complet);

    expect(Matching::where('need_id', $need->id)->count())->toBe(1);
});
