<?php

use App\Enums\CandidateStatut;
use App\Models\Candidate;
use App\Parcours\CycleApprenant;
use App\StateMachine\InvalidTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| P0-02-6 — Règle 1 : email OU téléphone obligatoire
|--------------------------------------------------------------------------
*/

it('refuse un candidat sans email ni téléphone', function () {
    Candidate::factory()->sansContact()->create();
})->throws(ValidationException::class);

it('accepte un candidat avec seulement un téléphone', function () {
    $candidate = Candidate::factory()->create(['email' => null, 'telephone' => '0612345678']);

    expect($candidate->exists)->toBeTrue();
});

it('accepte un candidat avec seulement un email', function () {
    $candidate = Candidate::factory()->create(['email' => 'jean@exemple.fr', 'telephone' => null]);

    expect($candidate->exists)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Cycle apprenant — décision CFA : Entretien prévu → Accepté / Refusé
|--------------------------------------------------------------------------
*/

it('démarre tout nouveau candidat au statut « Entretien à planifier » (défaut)', function () {
    $candidate = Candidate::query()->create([
        'nom' => 'Nouveau', 'prenom' => 'Candidat', 'email' => 'nouveau@exemple.fr',
    ]);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::EntretienAPlanifier);
});

it('autorise le passage d\'« Entretien prévu » à « Accepté » après un entretien réalisé', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::EntretienPrevu]);
    \App\Models\Entretien::factory()->realise()->create(['candidate_id' => $candidate->id]);

    $candidate->transitionTo(CandidateStatut::Accepte);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Accepte);
});

it('autorise le passage d\'« Entretien prévu » à « Refusé »', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::EntretienPrevu]);

    $candidate->transitionTo(CandidateStatut::Refuse);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Refuse);
});

it('interdit le retour à « Entretien prévu » après acceptation, avec un message dédié', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);

    expect($candidate->transitionBlockReason(CandidateStatut::EntretienPrevu))
        ->toBe(CycleApprenant::MSG_RETOUR_ENTRETIEN);

    expect(fn () => $candidate->transitionTo(CandidateStatut::EntretienPrevu))
        ->toThrow(InvalidTransitionException::class, CycleApprenant::MSG_RETOUR_ENTRETIEN);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Accepte);
});

it('interdit le retour à « Entretien prévu » après refus', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Refuse]);

    expect(fn () => $candidate->transitionTo(CandidateStatut::EntretienPrevu))
        ->toThrow(InvalidTransitionException::class, CycleApprenant::MSG_RETOUR_ENTRETIEN);
});

it('bloque aussi le retour arrière par écriture directe (hors machine à états)', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);

    expect(fn () => $candidate->forceFill(['statut' => CandidateStatut::EntretienPrevu])->save())
        ->toThrow(ValidationException::class);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Accepte);
});

it('fige toute décision finale (pas de bascule Accepté ↔ Refusé)', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Refuse]);

    expect(fn () => $candidate->transitionTo(CandidateStatut::Accepte))
        ->toThrow(InvalidTransitionException::class);
});
