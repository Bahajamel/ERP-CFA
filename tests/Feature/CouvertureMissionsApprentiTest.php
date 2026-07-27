<?php

use App\Models\Candidate;
use App\Models\CfaMission;
use App\Models\Document;
use Database\Seeders\CfaMissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function documentDeCandidat(Candidate $candidate): Document
{
    return Document::factory()->create([
        'documentable_id' => $candidate->id,
        'documentable_type' => $candidate->getMorphClass(),
    ]);
}

it('marque comme couvertes les missions rattachées aux documents de l\'apprenti', function () {
    $this->seed(CfaMissionSeeder::class);
    $candidate = Candidate::factory()->create();

    $document = documentDeCandidat($candidate);
    $document->missions()->attach(CfaMission::whereIn('numero', [1, 4])->pluck('id'));

    $couverture = $candidate->couvertureMissions();

    expect($couverture)->toHaveCount(14)
        ->and($couverture->firstWhere('mission.numero', 1)->couverte)->toBeTrue()
        ->and($couverture->firstWhere('mission.numero', 4)->couverte)->toBeTrue()
        ->and($couverture->firstWhere('mission.numero', 2)->couverte)->toBeFalse();
});

it('calcule le taux de couverture des missions de l\'apprenti', function () {
    $this->seed(CfaMissionSeeder::class);
    $candidate = Candidate::factory()->create();

    documentDeCandidat($candidate)->missions()->attach(CfaMission::whereIn('numero', [1, 4])->pluck('id'));

    // 2 missions couvertes sur 14 => 14 %.
    expect($candidate->tauxCouvertureMissions())->toBe(14);
});

it('renvoie 0 % quand l\'apprenti n\'a aucun livrable rattaché', function () {
    $this->seed(CfaMissionSeeder::class);
    $candidate = Candidate::factory()->create();

    expect($candidate->tauxCouvertureMissions())->toBe(0);
});

it('ne compte pas les missions couvertes par les documents d\'un autre apprenti', function () {
    $this->seed(CfaMissionSeeder::class);
    $apprentiA = Candidate::factory()->create();
    $apprentiB = Candidate::factory()->create();

    documentDeCandidat($apprentiB)->missions()->attach(CfaMission::where('numero', 5)->pluck('id'));

    expect($apprentiA->couvertureMissions()->firstWhere('mission.numero', 5)->couverte)->toBeFalse()
        ->and($apprentiA->tauxCouvertureMissions())->toBe(0);
});

it('ne double-compte pas une mission couverte par deux documents', function () {
    $this->seed(CfaMissionSeeder::class);
    $candidate = Candidate::factory()->create();
    $mission = CfaMission::where('numero', 4)->first();

    documentDeCandidat($candidate)->missions()->attach($mission->id);
    documentDeCandidat($candidate)->missions()->attach($mission->id);

    expect($candidate->tauxCouvertureMissions())->toBe(7); // 1/14 ≈ 7 %
});
