<?php

use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Models\Candidate;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('empêche de valider une séance dont des présences ne sont pas renseignées', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->count(2)->create(['promotion_id' => $promo->id]);
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);

    expect(fn () => $seance->update(['statut' => SeanceStatut::Validee]))
        ->toThrow(ValidationException::class);

    expect($seance->fresh()->statut)->toBe(SeanceStatut::Planifiee);
});

it('autorise la validation une fois toutes les présences renseignées', function () {
    $promo = Promotion::factory()->create();
    Candidate::factory()->count(2)->create(['promotion_id' => $promo->id]);
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);
    $seance->presences()->update(['statut' => PresenceStatut::Present]);

    $seance->update(['statut' => SeanceStatut::Validee]);

    expect($seance->fresh()->statut)->toBe(SeanceStatut::Validee);
});

it('crée une tâche de suivi quand une absence injustifiée est saisie', function () {
    $commercial = User::factory()->create();
    $promo = Promotion::factory()->create();
    $c = Candidate::factory()->create(['promotion_id' => $promo->id, 'commercial_id' => $commercial->id]);
    $seance = Seance::factory()->create(['promotion_id' => $promo->id]);
    $presence = $seance->presences()->where('candidate_id', $c->id)->first();

    $presence->update(['statut' => PresenceStatut::AbsentInjustifie]);

    $task = Task::where('cle', $presence->cleAlerteAbsence())->first();
    expect($task)->not->toBeNull()
        ->and($task->assignee_id)->toBe($commercial->id)
        ->and($task->taskable_id)->toBe($c->id);

    // Requalifiée en justifiée → la tâche disparaît.
    $presence->update(['statut' => PresenceStatut::AbsentJustifie]);
    expect(Task::where('cle', $presence->cleAlerteAbsence())->exists())->toBeFalse();
});
