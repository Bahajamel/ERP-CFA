<?php

use App\Enums\CandidateStatut;
use App\Enums\NeedStatut;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Formation;
use App\Models\Need;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cible une entreprise ayant un besoin ouvert compatible', function () {
    $formation = Formation::factory()->create();
    $candidate = Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::Accepte,
    ]);

    $company = Company::factory()->create();
    Need::factory()->for($company)->create([
        'formation_id' => $formation->id,
        'statut' => NeedStatut::ProfilsRecherches,
    ]);

    $cibles = $candidate->entreprisesACibler();

    expect($cibles)->toHaveCount(1)
        ->and($cibles->first()['company']->id)->toBe($company->id)
        ->and($cibles->first()['score'])->toBeGreaterThan(0);
});

it('ignore les besoins clôturés (non ouverts)', function () {
    $formation = Formation::factory()->create();
    $candidate = Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::Accepte,
    ]);

    Need::factory()->create([
        'formation_id' => $formation->id,
        'statut' => NeedStatut::Pourvu, // clôturé
    ]);

    expect($candidate->entreprisesACibler())->toBeEmpty();
});

it('cible une entreprise ayant déjà recruté dans la formation visée (sans besoin ouvert)', function () {
    $formation = Formation::factory()->create();
    $candidate = Candidate::factory()->create(['formation_visee_id' => $formation->id]);

    $company = Company::factory()->create();
    Contract::factory()->create([
        'company_id' => $company->id,
        'formation_id' => $formation->id,
    ]);

    $cibles = $candidate->entreprisesACibler();

    expect($cibles)->toHaveCount(1)
        ->and($cibles->first()['company']->id)->toBe($company->id)
        ->and($cibles->first()['raison'])->toContain('déjà recruté');
});

it('fusionne les deux signaux pour une entreprise à la fois partenaire et avec besoin ouvert', function () {
    $formation = Formation::factory()->create();
    $candidate = Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::Accepte,
    ]);

    $company = Company::factory()->create();
    Need::factory()->for($company)->create(['formation_id' => $formation->id, 'statut' => NeedStatut::ProfilsRecherches]);
    Contract::factory()->create(['company_id' => $company->id, 'formation_id' => $formation->id]);

    $cibles = $candidate->entreprisesACibler();

    expect($cibles)->toHaveCount(1)
        ->and($cibles->first()['score'])->toBeGreaterThan(0)
        ->and($cibles->first()['raison'])->toContain('Besoin ouvert')
        ->and($cibles->first()['raison'])->toContain('déjà recruté');
});

it('classe les besoins ouverts compatibles avant les simples partenaires', function () {
    $formation = Formation::factory()->create();
    $candidate = Candidate::factory()->create([
        'formation_visee_id' => $formation->id,
        'statut' => CandidateStatut::Accepte,
    ]);

    // Partenaire sans besoin ouvert (score 0)
    $partenaire = Company::factory()->create();
    Contract::factory()->create(['company_id' => $partenaire->id, 'formation_id' => $formation->id]);

    // Entreprise avec besoin ouvert compatible (score > 0)
    $avecBesoin = Company::factory()->create();
    Need::factory()->for($avecBesoin)->create([
        'formation_id' => $formation->id,
        'statut' => NeedStatut::ProfilsRecherches,
    ]);

    $cibles = $candidate->entreprisesACibler();

    expect($cibles)->toHaveCount(2)
        ->and($cibles->first()['company']->id)->toBe($avecBesoin->id)
        ->and($cibles->last()['company']->id)->toBe($partenaire->id);
});
