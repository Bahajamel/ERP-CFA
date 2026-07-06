<?php

use App\Enums\CandidateStatut;
use App\Models\Candidate;
use App\StateMachine\InvalidTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** Attache un CV (PDF minimal) au candidat, dans la collection média « cv ». */
function attacherCv(Candidate $candidate): void
{
    Storage::fake('public');
    $candidate->addMediaFromString("%PDF-1.4\ntrailer<</Root 1 0 R>>\n%%EOF")
        ->usingFileName('cv.pdf')
        ->toMediaCollection('cv');
}

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

it('bloque « Dossier complet » si le CV est manquant', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);

    expect(fn () => $candidate->fresh()->transitionTo(CandidateStatut::Complet))
        ->toThrow(InvalidTransitionException::class);
});

it('autorise « Dossier complet » quand le CV est fourni', function () {
    $candidate = Candidate::factory()->create(['statut' => CandidateStatut::Incomplet]);
    attacherCv($candidate);

    $candidate->fresh()->transitionTo(CandidateStatut::Complet);

    expect($candidate->fresh()->statut)->toBe(CandidateStatut::Complet);
});
