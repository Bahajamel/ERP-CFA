<?php

use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Models\Matching;
use App\Models\Need;
use App\StateMachine\InvalidTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuse le passage à « Pourvu » sans candidat accepté', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::CandidatRetenu]);

    expect(fn () => $need->transitionTo(NeedStatut::Pourvu))
        ->toThrow(InvalidTransitionException::class);

    expect($need->fresh()->statut)->toBe(NeedStatut::CandidatRetenu);
});

it('autorise « Pourvu » dès qu\'un candidat est accepté', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::CandidatRetenu]);
    Matching::factory()->for($need)->create(['statut' => MatchingStatut::Accepte]);

    $need->transitionTo(NeedStatut::Pourvu);

    expect($need->fresh()->statut)->toBe(NeedStatut::Pourvu);
});

it('date la clôture automatiquement en passant à « Pourvu »', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::CandidatRetenu, 'date_cloture' => null]);
    Matching::factory()->for($need)->create(['statut' => MatchingStatut::Accepte]);

    $need->transitionTo(NeedStatut::Pourvu);

    expect($need->fresh()->date_cloture)->not->toBeNull();
});

it('date la clôture automatiquement en passant à « Annulé »', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::Cree, 'date_cloture' => null]);

    $need->transitionTo(NeedStatut::Annule);

    expect($need->fresh()->date_cloture)->not->toBeNull();
});

it('abandonne les matchings ouverts et préserve l\'accepté quand le besoin est pourvu', function () {
    $need = Need::factory()->create(['statut' => NeedStatut::CandidatRetenu]);

    $accepte = Matching::factory()->for($need)->create(['statut' => MatchingStatut::Accepte]);
    $propose = Matching::factory()->for($need)->create(['statut' => MatchingStatut::EnRecherche]);
    $cvEnvoye = Matching::factory()->for($need)->create(['statut' => MatchingStatut::PropositionEnvoyee]);
    $dejaRefuse = Matching::factory()->for($need)->create(['statut' => MatchingStatut::Refuse]);

    $need->transitionTo(NeedStatut::Pourvu);

    expect($accepte->fresh()->statut)->toBe(MatchingStatut::Accepte)
        ->and($propose->fresh()->statut)->toBe(MatchingStatut::Abandonne)
        ->and($cvEnvoye->fresh()->statut)->toBe(MatchingStatut::Abandonne)
        ->and($dejaRefuse->fresh()->statut)->toBe(MatchingStatut::Refuse);
});
