<?php

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Enums\NeedStatut;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Le statut d'une offre suit l'activité réelle de ses candidats.
 *
 * Avant : le seul endroit qui faisait avancer une offre était le bouton
 * « Changer le statut ». Personne ne le tenait à jour — les offres restaient
 * figées sur « Créé » et la colonne ne voulait plus rien dire.
 */
function offreAuto(int $postes = 1): Need
{
    return Need::factory()->create([
        'nb_postes' => $postes,
        'statut' => NeedStatut::Cree,
    ]);
}

function candidatPourOffre(): Candidate
{
    // Un matching ne peut naître que sur un candidat accepté (invariant du cycle).
    return Candidate::factory()->create(['statut' => CandidateStatut::Accepte]);
}

it('passe l’offre à « Profils recherchés » dès qu’un candidat lui est rattaché', function () {
    $offre = offreAuto();

    Matching::create([
        'need_id' => $offre->id,
        'candidate_id' => candidatPourOffre()->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);

    // L'offre traverse les étapes intermédiaires une à une : la machine à états
    // n'autorise pas « Créé » → « Profils recherchés » d'un bond.
    expect($offre->refresh()->statut)->toBe(NeedStatut::ProfilsRecherches);
});

it('passe l’offre à « Candidat retenu » quand un candidat est accepté', function () {
    $offre = offreAuto(postes: 2); // 2 postes : un seul accepté ne pourvoit pas l'offre

    $m = Matching::create([
        'need_id' => $offre->id,
        'candidate_id' => candidatPourOffre()->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);
    $m->update(['cv_envoye' => true, 'statut' => MatchingStatut::PropositionEnvoyee]);
    $m->update(['statut' => MatchingStatut::Accepte]);

    expect($offre->refresh()->statut)->toBe(NeedStatut::CandidatRetenu)
        ->and($offre->postesRestants())->toBe(1)
        ->and($offre->estCloture())->toBeFalse();
});

it('ne clôt l’offre que lorsque TOUS les postes sont pourvus', function () {
    $offre = offreAuto(postes: 2);

    foreach (range(1, 2) as $i) {
        $m = Matching::create([
            'need_id' => $offre->id,
            'candidate_id' => candidatPourOffre()->id,
            'statut' => MatchingStatut::EnRecherche,
        ]);
        $m->update(['cv_envoye' => true, 'statut' => MatchingStatut::PropositionEnvoyee]);
        $m->update(['statut' => MatchingStatut::Accepte]);

        // Après le premier accepté, l'entreprise recrute toujours.
        if ($i === 1) {
            expect($offre->refresh()->estCloture())->toBeFalse();
        }
    }

    expect($offre->refresh()->statut)->toBe(NeedStatut::Pourvu)
        ->and($offre->estCloture())->toBeTrue()
        ->and($offre->date_cloture)->not->toBeNull();
});

it('clôt l’offre à un seul poste dès le premier candidat accepté', function () {
    $offre = offreAuto(postes: 1);

    $m = Matching::create([
        'need_id' => $offre->id,
        'candidate_id' => candidatPourOffre()->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);
    $m->update(['cv_envoye' => true, 'statut' => MatchingStatut::PropositionEnvoyee]);
    $m->update(['statut' => MatchingStatut::Accepte]);

    expect($offre->refresh()->statut)->toBe(NeedStatut::Pourvu);
});

it('ne fait jamais reculer une offre ni ne rouvre une offre close', function () {
    $offre = offreAuto(postes: 1);

    $m = Matching::create([
        'need_id' => $offre->id,
        'candidate_id' => candidatPourOffre()->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);
    $m->update(['cv_envoye' => true, 'statut' => MatchingStatut::PropositionEnvoyee]);
    $m->update(['statut' => MatchingStatut::Accepte]);

    expect($offre->refresh()->statut)->toBe(NeedStatut::Pourvu);

    // Le candidat se désiste après coup : l'offre reste close, elle ne se rouvre
    // pas toute seule (ce serait à un humain de décider de relancer un recrutement).
    $m->update(['statut' => MatchingStatut::Abandonne]);

    expect($offre->refresh()->statut)->toBe(NeedStatut::Pourvu);
});

it('laisse tranquille une offre annulée', function () {
    $offre = offreAuto();
    $offre->transitionTo(NeedStatut::Annule, 'L\'entreprise a gelé son recrutement.');

    // « Annulé » est hors trajectoire nominale : aucune activité candidat ne doit
    // ressusciter l'offre.
    Matching::create([
        'need_id' => $offre->id,
        'candidate_id' => candidatPourOffre()->id,
        'statut' => MatchingStatut::EnRecherche,
    ]);

    expect($offre->refresh()->statut)->toBe(NeedStatut::Annule);
});
