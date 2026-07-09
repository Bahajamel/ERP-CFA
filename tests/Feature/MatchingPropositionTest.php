<?php

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use App\Parcours\CycleApprenant;
use App\Parcours\CycleBloqueException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cycleProp(): CycleApprenant
{
    return app(CycleApprenant::class);
}

function candidatAccepteProp(): Candidate
{
    return Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);
}

function offreOuverte(): Need
{
    return Need::factory()->create(['statut' => NeedStatut::ProfilsEnvoyes]);
}

/*
|--------------------------------------------------------------------------
| Envoi vers Matching SANS offre → « En recherche »
|--------------------------------------------------------------------------
*/

it('ouvre une recherche « En recherche » sans offre sélectionnée', function () {
    $matching = cycleProp()->envoyerVersMatching(candidatAccepteProp(), null);

    expect($matching->statut)->toBe(MatchingStatut::EnRecherche)
        ->and($matching->need_id)->toBeNull();
});

it('ne crée pas de seconde recherche « En recherche » pour le même candidat (anti-doublon)', function () {
    $candidat = candidatAccepteProp();

    $premier = cycleProp()->envoyerVersMatching($candidat, null);
    $second = cycleProp()->envoyerVersMatching($candidat->fresh(), null);

    expect($second->id)->toBe($premier->id)
        ->and(Matching::query()->where('candidate_id', $candidat->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Envoi vers Matching AVEC offre → « Proposition envoyée » (CV requis)
|--------------------------------------------------------------------------
*/

it('crée une proposition « Proposition envoyée » avec une offre et un CV', function () {
    $matching = cycleProp()->proposerSurOffre(candidatAccepteProp(), offreOuverte(), cvDisponible: true);

    expect($matching->statut)->toBe(MatchingStatut::PropositionEnvoyee)
        ->and($matching->cv_envoye)->toBeTrue()
        ->and($matching->need_id)->not->toBeNull();
});

it('bloque une proposition sur une offre sans CV', function () {
    expect(fn () => cycleProp()->proposerSurOffre(candidatAccepteProp(), offreOuverte(), cvDisponible: false))
        ->toThrow(CycleBloqueException::class, CycleApprenant::MSG_CV_OBLIGATOIRE);
});

it('réutilise la recherche déjà ouverte pour la proposition (pas de doublon)', function () {
    $candidat = candidatAccepteProp();
    $recherche = cycleProp()->envoyerVersMatching($candidat, null);

    $matching = cycleProp()->proposerSurOffre($candidat->fresh(), offreOuverte(), cvDisponible: true);

    expect($matching->id)->toBe($recherche->id)
        ->and($matching->statut)->toBe(MatchingStatut::PropositionEnvoyee)
        ->and(Matching::query()->where('candidate_id', $candidat->id)->count())->toBe(1);
});

it('bloque une seconde proposition du même candidat sur la même offre', function () {
    $candidat = candidatAccepteProp();
    $offre = offreOuverte();

    cycleProp()->proposerSurOffre($candidat, $offre, cvDisponible: true);

    expect(fn () => cycleProp()->proposerSurOffre($candidat->fresh(), $offre, cvDisponible: true))
        ->toThrow(CycleBloqueException::class, CycleApprenant::MSG_DEJA_PROPOSE);
});

it('refuse une proposition pour un candidat non accepté', function () {
    $candidat = Candidate::factory()->create(['statut' => CandidateStatut::EntretienRealise]);

    expect(fn () => cycleProp()->proposerSurOffre($candidat, offreOuverte(), cvDisponible: true))
        ->toThrow(CycleBloqueException::class, CycleApprenant::MSG_CANDIDAT_NON_ACCEPTE);
});
