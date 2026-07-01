<?php

use App\Enums\CandidateStatut;
use App\Enums\ChecklistItemStatut;
use App\Models\Admission;
use App\Models\AdmissionChecklistItem;
use App\Models\Candidate;
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
| P0-02-6 — Règle 2 : machine à états + « pas Complet si pièces manquantes »
|--------------------------------------------------------------------------
*/

it('autorise une transition déclarée', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);

    $candidate->transitionTo(CandidateStatut::EnRechercheEntreprise);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::EnRechercheEntreprise);
});

it('refuse une transition non déclarée', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);

    $candidate->transitionTo(CandidateStatut::ContratSigne);
})->throws(InvalidTransitionException::class);

it('bloque « Dossier complet » si une pièce obligatoire est manquante', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);
    $admission = Admission::factory()->create(['candidate_id' => $candidate->id]);
    AdmissionChecklistItem::factory()->create([
        'admission_id' => $admission->id,
        'est_obligatoire' => true,
        'statut' => ChecklistItemStatut::Manquante,
    ]);

    expect(fn () => $candidate->fresh()->transitionTo(CandidateStatut::Complet))
        ->toThrow(InvalidTransitionException::class);
});

it('autorise « Dossier complet » quand les pièces obligatoires sont présentes', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);
    $admission = Admission::factory()->create(['candidate_id' => $candidate->id]);
    AdmissionChecklistItem::factory()->create([
        'admission_id' => $admission->id,
        'est_obligatoire' => true,
        'statut' => ChecklistItemStatut::Presente,
    ]);

    $candidate->fresh()->transitionTo(CandidateStatut::Complet);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Complet);
});
