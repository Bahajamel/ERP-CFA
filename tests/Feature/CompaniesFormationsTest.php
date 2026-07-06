<?php

use App\Enums\NeedStatut;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Need;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('liste les formations recherchées à partir des besoins ouverts', function () {
    $company = Company::factory()->create();
    $dev = Formation::factory()->create(['libelle' => 'BTS SIO']);
    $compta = Formation::factory()->create(['libelle' => 'BTS CG']);

    Need::factory()->for($company)->create(['formation_id' => $dev->id, 'statut' => NeedStatut::ProfilsEnvoyes]);
    Need::factory()->for($company)->create(['formation_id' => $compta->id, 'statut' => NeedStatut::Cree]);

    expect($company->formationsRecherchees()->all())
        ->toEqualCanonicalizing(['BTS SIO', 'BTS CG']);
});

it('dédoublonne les formations quand plusieurs besoins ouverts visent la même', function () {
    $company = Company::factory()->create();
    $dev = Formation::factory()->create(['libelle' => 'BTS SIO']);

    Need::factory()->count(2)->for($company)->create(['formation_id' => $dev->id, 'statut' => NeedStatut::ProfilsEnvoyes]);

    expect($company->formationsRecherchees()->all())->toBe(['BTS SIO']);
});

it('exclut les formations des besoins clôturés (entreprise qui ne recrute plus)', function () {
    $company = Company::factory()->create();
    $formation = Formation::factory()->create(['libelle' => 'BTS MCO']);

    Need::factory()->for($company)->create(['formation_id' => $formation->id, 'statut' => NeedStatut::Pourvu]);

    expect($company->formationsRecherchees())->toBeEmpty();
});

it('filtre les entreprises par formation recherchée (besoin ouvert)', function () {
    $formation = Formation::factory()->create();
    $autre = Formation::factory()->create();

    $cible = Company::factory()->create();
    Need::factory()->for($cible)->create(['formation_id' => $formation->id, 'statut' => NeedStatut::ProfilsEnvoyes]);

    $horsCible = Company::factory()->create();
    Need::factory()->for($horsCible)->create(['formation_id' => $autre->id, 'statut' => NeedStatut::ProfilsEnvoyes]);

    // Entreprise avec un besoin sur la bonne formation mais clôturé → exclue.
    $fermee = Company::factory()->create();
    Need::factory()->for($fermee)->create(['formation_id' => $formation->id, 'statut' => NeedStatut::Archive]);

    $resultats = Company::query()
        ->whereHas('needs', fn (Builder $n): Builder => $n->ouverts()->where('formation_id', $formation->id))
        ->pluck('id');

    expect($resultats)->toContain($cible->id)
        ->and($resultats)->not->toContain($horsCible->id)
        ->and($resultats)->not->toContain($fermee->id);
});
