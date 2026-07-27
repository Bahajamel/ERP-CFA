<?php

use App\Enums\InteractionType;
use App\Filament\RelationManagers\InteractionsRelationManager;
use App\Filament\Resources\Candidates\Pages\EditCandidate;
use App\Models\Candidate;
use App\Models\Interaction;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('crée automatiquement une tâche de relance quand une action est datée', function () {
    $user = User::factory()->create();
    $candidate = Candidate::factory()->create();

    $interaction = Interaction::factory()->for($candidate, 'interactable')->create([
        'user_id' => $user->id,
        'prochaine_action' => 'Rappeler le candidat',
        'prochaine_action_le' => now()->addDays(3),
    ]);

    $task = Task::where('cle', $interaction->cleTacheRelance())->first();

    expect($task)->not->toBeNull()
        ->and($task->titre)->toBe('Rappeler le candidat')
        ->and($task->assignee_id)->toBe($user->id)
        ->and($task->taskable_id)->toBe($candidate->id)
        ->and($task->taskable_type)->toBe(Candidate::class);
});

it('ne crée pas de tâche sans action datée', function () {
    $candidate = Candidate::factory()->create();
    $interaction = Interaction::factory()->for($candidate, 'interactable')->create([
        'prochaine_action_le' => null,
    ]);

    expect(Task::where('cle', $interaction->cleTacheRelance())->exists())->toBeFalse();
});

it('met à jour puis supprime la tâche quand l\'action datée change ou disparaît', function () {
    $candidate = Candidate::factory()->create();
    $interaction = Interaction::factory()->for($candidate, 'interactable')->create([
        'prochaine_action' => 'Version 1',
        'prochaine_action_le' => now()->addWeek(),
    ]);

    $interaction->update(['prochaine_action' => 'Version 2']);
    expect(Task::where('cle', $interaction->cleTacheRelance())->value('titre'))->toBe('Version 2');

    $interaction->update(['prochaine_action_le' => null]);
    expect(Task::where('cle', $interaction->cleTacheRelance())->exists())->toBeFalse();
});

it('supprime la tâche de relance avec l\'interaction', function () {
    $candidate = Candidate::factory()->create();
    $interaction = Interaction::factory()->for($candidate, 'interactable')->create([
        'prochaine_action_le' => now()->addDay(),
    ]);
    $cle = $interaction->cleTacheRelance();

    $interaction->delete();

    expect(Task::where('cle', $cle)->exists())->toBeFalse();
});

it('expose la timeline candidat et consigne une interaction (auteur auto)', function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles('Commercial');
    $this->actingAs($user);

    $candidate = Candidate::factory()->create();

    Livewire::test(InteractionsRelationManager::class, [
        'ownerRecord' => $candidate,
        'pageClass' => EditCandidate::class,
    ])->callTableAction('create', data: [
        'type' => InteractionType::Appel->value,
        'date_interaction' => now()->toDateString(),
        'resume' => 'Point sur la recherche d\'entreprise.',
        'prochaine_action' => 'Envoyer 2 offres',
        'prochaine_action_le' => now()->addDays(5)->toDateString(),
    ]);

    $interaction = $candidate->interactions()->firstOrFail();

    expect($interaction->user_id)->toBe($user->id)
        ->and(Task::where('cle', $interaction->cleTacheRelance())->exists())->toBeTrue();
});
