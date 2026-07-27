<?php

use App\Models\Candidate;
use App\Models\Company;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Garde-fou du seeding. Les seeders tournent hors panel : si l'OrganisationSeeder
 * ne pose pas le CFA courant EN PREMIER, tout naît avec `organisation_id` nul et
 * devient invisible de tous les CFA — la démo s'affiche entièrement vide alors
 * que la base est pleine. Panne silencieuse : rien ne casse, tout disparaît.
 */
beforeEach(function () {
    // On se place dans les conditions réelles d'un `migrate:fresh --seed` :
    // aucun CFA courant (le socle de tests en fixe un, on le retire).
    Filament::setTenant(null, isQuiet: true);
});

it('rattache toutes les données de démonstration au CFA maison', function () {
    $this->seed(DatabaseSeeder::class);

    $cfa = Organisation::where('slug', 'cfa-v2s')->sole();

    $orphelins = collect([
        'candidates' => Candidate::class,
        'companies' => Company::class,
    ])->map(fn (string $model) => $model::query()->tousLesCfa()->whereNull('organisation_id')->count());

    expect($orphelins['candidates'])->toBe(0)
        ->and($orphelins['companies'])->toBe(0)
        ->and(Candidate::query()->tousLesCfa()->count())->toBeGreaterThan(0)
        ->and(Candidate::query()->tousLesCfa()->where('organisation_id', $cfa->id)->count())
        ->toBe(Candidate::query()->tousLesCfa()->count());
});

it('rend les données de démonstration visibles depuis le CFA maison', function () {
    $this->seed(DatabaseSeeder::class);

    $cfa = Organisation::where('slug', 'cfa-v2s')->sole();
    Filament::setTenant($cfa, isQuiet: true);

    // Le test qui compte vraiment : ce que voit l'écran une fois connecté.
    expect(Candidate::query()->count())->toBeGreaterThan(0)
        ->and(Company::query()->count())->toBeGreaterThan(0);
});

it('rattache tout le personnel au CFA maison', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->whereDoesntHave('organisations')->count())->toBe(0)
        ->and(User::query()->count())->toBeGreaterThan(0);
});
