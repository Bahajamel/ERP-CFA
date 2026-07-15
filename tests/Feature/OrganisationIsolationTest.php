<?php

use App\Models\Candidate;
use App\Models\Company;
use App\Models\Organisation;
use App\Models\Task;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Isolation réelle entre CFA. Ces tests ne passent PAS par Filament : ils
 * interrogent les modèles directement, comme le font les services, jobs,
 * commandes et widgets. C'est précisément là que le cloisonnement doit tenir —
 * l'ownership Filament ne protège que les Resources.
 */

/** Exécute une closure dans le contexte d'un CFA donné, puis restaure le précédent. */
function dansLeCfa(Organisation $cfa, Closure $closure): mixed
{
    $precedent = Filament::getTenant();

    Filament::setTenant($cfa, isQuiet: true);

    try {
        return $closure();
    } finally {
        Filament::setTenant($precedent, isQuiet: true);
    }
}

it('ne laisse pas un CFA lire les candidats d’un autre CFA', function () {
    $cfaA = Organisation::factory()->create();
    $cfaB = Organisation::factory()->create();

    $candidatA = dansLeCfa($cfaA, fn () => Candidate::factory()->create(['nom' => 'Dupont']));
    $candidatB = dansLeCfa($cfaB, fn () => Candidate::factory()->create(['nom' => 'Martin']));

    // Chaque candidat est bien rattaché à son CFA (trait BelongsToOrganisation).
    expect($candidatA->organisation_id)->toBe($cfaA->id)
        ->and($candidatB->organisation_id)->toBe($cfaB->id);

    // Depuis le CFA A, le candidat du CFA B est invisible.
    $vusDepuisA = dansLeCfa($cfaA, fn () => Candidate::query()->pluck('nom')->all());

    expect($vusDepuisA)->toBe(['Dupont']);
});

it('ne laisse pas un CFA retrouver une entreprise d’un autre CFA par son id', function () {
    $cfaA = Organisation::factory()->create();
    $cfaB = Organisation::factory()->create();

    $entrepriseB = dansLeCfa($cfaB, fn () => Company::factory()->create());

    // L'accès direct par identifiant doit échouer depuis le CFA A : sans cela,
    // une URL devinée (/admin/cfa-a/entreprises/{id}) exposerait la donnée.
    $trouvee = dansLeCfa($cfaA, fn () => Company::query()->find($entrepriseB->id));

    expect($trouvee)->toBeNull();
});

it('cloisonne les agrégats (count) utilisés par les widgets du cockpit', function () {
    $cfaA = Organisation::factory()->create();
    $cfaB = Organisation::factory()->create();

    dansLeCfa($cfaA, fn () => Task::factory()->count(2)->create());
    dansLeCfa($cfaB, fn () => Task::factory()->count(5)->create());

    expect(dansLeCfa($cfaA, fn () => Task::query()->count()))->toBe(2)
        ->and(dansLeCfa($cfaB, fn () => Task::query()->count()))->toBe(5);
});

it('permet aux traitements hors contexte CFA de voir toutes les données', function () {
    $cfaA = Organisation::factory()->create();
    $cfaB = Organisation::factory()->create();

    dansLeCfa($cfaA, fn () => Candidate::factory()->create());
    dansLeCfa($cfaB, fn () => Candidate::factory()->create());

    // Un job / une commande artisan s'exécute sans CFA courant : il doit pouvoir
    // balayer l'ensemble des CFA (purge, statistiques éditeur, migrations…).
    $precedent = Filament::getTenant();
    Filament::setTenant(null, isQuiet: true);

    try {
        expect(Candidate::query()->count())->toBe(2);
    } finally {
        Filament::setTenant($precedent, isQuiet: true);
    }
});

it('autorise deux CFA à référencer la même entreprise (SIRET identique)', function () {
    $cfaA = Organisation::factory()->create();
    $cfaB = Organisation::factory()->create();

    // Un même employeur travaille avec plusieurs centres : chacun tient sa fiche.
    $siret = '80295478500018';

    dansLeCfa($cfaA, fn () => Company::factory()->create(['siret' => $siret]));
    dansLeCfa($cfaB, fn () => Company::factory()->create(['siret' => $siret]));

    expect(Company::query()->tousLesCfa()->where('siret', $siret)->count())->toBe(2);
});

it('refuse deux fois le même SIRET à l’intérieur d’un même CFA', function () {
    $cfa = Organisation::factory()->create();
    $siret = '80295478500018';

    dansLeCfa($cfa, fn () => Company::factory()->create(['siret' => $siret]));

    dansLeCfa($cfa, fn () => Company::factory()->create(['siret' => $siret]));
})->throws(Illuminate\Database\QueryException::class);

it('offre une échappatoire explicite pour requêter tous les CFA depuis un contexte CFA', function () {
    $cfaA = Organisation::factory()->create();
    $cfaB = Organisation::factory()->create();

    dansLeCfa($cfaA, fn () => Candidate::factory()->create());
    dansLeCfa($cfaB, fn () => Candidate::factory()->create());

    $tous = dansLeCfa($cfaA, fn () => Candidate::query()->tousLesCfa()->count());

    expect($tous)->toBe(2);
});
