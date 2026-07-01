<?php

use App\Enums\CandidateStatut;
use App\Matching\CompatibilityScorer;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Matching;
use App\Models\Need;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('additionne formation (50) + disponibilité (25) + niveau (10)', function () {
    $formation = Formation::factory()->create();
    $need = Need::factory()->create(['formation_id' => $formation->id, 'localisation' => 'Lyon']);
    $candidate = Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::EnRechercheEntreprise,
        'niveau_actuel' => 'Bac+2',
        'mobilite' => 'Locale',
    ]);

    expect((new CompatibilityScorer())->score($candidate, $need))->toBe(85);
});

it('ajoute la mobilité (15) quand elle contient la localisation du besoin', function () {
    $formation = Formation::factory()->create();
    $need = Need::factory()->create(['formation_id' => $formation->id, 'localisation' => 'Lyon']);
    $candidate = Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::EnRechercheEntreprise,
        'niveau_actuel' => 'Bac+2',
        'mobilite' => 'Lyon et périphérie',
    ]);

    expect((new CompatibilityScorer())->score($candidate, $need))->toBe(100);
});

it('donne un score nul sans formation ni disponibilité', function () {
    $need = Need::factory()->create(['formation_id' => Formation::factory(), 'localisation' => 'Paris']);
    $candidate = Candidate::factory()->create([
        'formation_visee_id' => Formation::factory(),
        'statut' => CandidateStatut::Incomplet,
        'niveau_actuel' => null,
        'mobilite' => null,
    ]);

    expect((new CompatibilityScorer())->score($candidate, $need))->toBe(0);
});

it('classe les candidats compatibles par score décroissant', function () {
    $formation = Formation::factory()->create();
    $need = Need::factory()->create(['formation_id' => $formation->id]);

    $fort = Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::EnRechercheEntreprise,
    ]);
    $faible = Candidate::factory()->create([
        'formation_visee_id' => Formation::factory(),
        'statut' => CandidateStatut::Incomplet,
        'niveau_actuel' => 'Bac',
    ]);

    $compat = $need->candidatsCompatibles();

    expect($compat->first()['candidate']->id)->toBe($fort->id)
        ->and($compat->pluck('candidate.id'))->toContain($faible->id);
});

it('exclut les candidats déjà proposés sur ce besoin', function () {
    $formation = Formation::factory()->create();
    $need = Need::factory()->create(['formation_id' => $formation->id]);
    $propose = Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::EnRechercheEntreprise,
    ]);
    Matching::factory()->create(['need_id' => $need->id, 'candidate_id' => $propose->id]);

    expect($need->candidatsCompatibles()->pluck('candidate.id'))->not->toContain($propose->id);
});

it('exclut les candidats en rupture', function () {
    $formation = Formation::factory()->create();
    $need = Need::factory()->create(['formation_id' => $formation->id]);
    Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::Rupture,
    ]);

    expect($need->candidatsCompatibles())->toBeEmpty();
});
