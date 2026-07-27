<?php

use App\Enums\CompanyStatut;
use App\Enums\NeedStatut;
use App\Filament\Resources\Needs\Pages\ListNeeds;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Need;
use App\Models\User;
use App\Prospecting\LaBonneAlternanceClient;
use App\Prospecting\ProspectionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.labonnealternance', [
        'api_key' => 'test-key',
        'base_url' => 'https://lba.test',
        'search_path' => '/api/job/v1/search',
        'results_keys' => ['jobs', 'recruiters'],
        'verify_ssl' => true,
        'timeout' => 5,
        'default_radius' => 30,
        'center' => ['lat' => 48.85, 'lon' => 2.35],
    ]);
});

/** Un recruteur au format API LBA (schéma workplace/apply). */
function recruteur(string $name, ?string $siret, string $naf = 'Développement informatique'): array
{
    return [
        'workplace' => [
            'name' => $name,
            'siret' => $siret,
            'domain' => ['naf' => ['label' => $naf]],
            'location' => [
                'address' => '10 rue des Lilas, 75010 Paris',
                'geopoint' => ['coordinates' => [2.36, 48.87]],
            ],
            'size' => '20 à 49 salariés',
            'website' => 'https://exemple.fr',
        ],
        'apply' => ['phone' => '0102030405', 'url' => 'https://lba/apply/1'],
    ];
}

it('mappe les entreprises qui recrutent depuis la réponse LBA', function () {
    Http::fake(['lba.test/*' => Http::response(['jobs' => [recruteur('Acme SAS', '12345678900011')]])]);
    $formation = Formation::factory()->create(['code_rncp' => 'RNCP34029']);

    $resultats = app(LaBonneAlternanceClient::class)->searchForFormation($formation, 48.85, 2.35, 30);

    expect($resultats)->toHaveCount(1);
    $c = $resultats->first();
    expect($c->name)->toBe('Acme SAS')
        ->and($c->siret)->toBe('12345678900011')
        ->and($c->secteur)->toBe('Développement informatique')
        ->and($c->latitude)->toBe(48.87)
        ->and($c->longitude)->toBe(2.36)
        ->and($c->telephone)->toBe('0102030405');
});

it('agrège les offres (jobs) et les entreprises susceptibles de recruter (recruiters)', function () {
    Http::fake(['lba.test/*' => Http::response([
        'jobs' => [recruteur('Avec offre', '11111111100011')],
        'recruiters' => [recruteur('Sans offre', '22222222200022')],
    ])]);
    $formation = Formation::factory()->create(['code_rncp' => 'RNCP34029']);

    $resultats = app(LaBonneAlternanceClient::class)->searchForFormation($formation, 48.85, 2.35, 30);

    expect($resultats->pluck('siret'))->toContain('11111111100011', '22222222200022');
});

it('importe les prospects comme entreprises (statut Prospect) + besoins à qualifier', function () {
    Http::fake(['lba.test/*' => Http::response(['jobs' => [
        recruteur('Acme SAS', '12345678900011'),
        recruteur('Beta SARL', '98765432100022'),
    ]])]);
    $formation = Formation::factory()->create(['code_rncp' => 'RNCP34029', 'libelle' => 'BTS SIO']);

    $r = app(ProspectionService::class)->prospectForFormation($formation, 48.85, 2.35, 30);

    expect($r)->toMatchArray(['found' => 2, 'imported' => 2, 'linked' => 0, 'skipped' => 0])
        ->and(Company::where('statut', CompanyStatut::Prospect->value)->count())->toBe(2);

    $acme = Company::where('siret', '12345678900011')->first();
    expect($acme->raison_sociale)->toBe('Acme SAS')
        ->and($acme->needs()->where('formation_id', $formation->id)->where('statut', NeedStatut::Cree->value)->exists())->toBeTrue();
});

it('reporte la date de début souhaitée sur les besoins créés', function () {
    Http::fake(['lba.test/*' => Http::response(['jobs' => [recruteur('Acme SAS', '12345678900011')]])]);
    $formation = Formation::factory()->create(['code_rncp' => 'RNCP34029']);

    app(ProspectionService::class)->prospectForFormation($formation, 48.85, 2.35, 30, '2026-09-01');

    $need = Need::where('formation_id', $formation->id)->first();
    expect($need)->not->toBeNull()
        ->and($need->date_demarrage->format('Y-m-d'))->toBe('2026-09-01');
});

it('ne recrée pas une entreprise au SIRET déjà connu (dédoublonnage)', function () {
    $existante = Company::factory()->create(['siret' => '12345678900011', 'statut' => CompanyStatut::Partenaire]);
    Http::fake(['lba.test/*' => Http::response(['jobs' => [recruteur('Acme SAS', '12345678900011')]])]);
    $formation = Formation::factory()->create(['code_rncp' => 'RNCP34029']);

    $r = app(ProspectionService::class)->prospectForFormation($formation, 48.85, 2.35, 30);

    expect($r)->toMatchArray(['imported' => 0, 'linked' => 1])
        ->and(Company::where('siret', '12345678900011')->count())->toBe(1)
        ->and($existante->fresh()->statut)->toBe(CompanyStatut::Partenaire) // non écrasée
        ->and($existante->needs()->where('formation_id', $formation->id)->exists())->toBeTrue();
});

it('ignore les entreprises sans SIRET', function () {
    Http::fake(['lba.test/*' => Http::response(['jobs' => [recruteur('Sans Siret', null)]])]);
    $formation = Formation::factory()->create(['code_rncp' => 'RNCP34029']);

    $r = app(ProspectionService::class)->prospectForFormation($formation, 48.85, 2.35, 30);

    expect($r)->toMatchArray(['found' => 1, 'imported' => 0, 'skipped' => 1])
        ->and(Company::count())->toBe(0);
});

it('n\'appelle pas l\'API pour une formation sans code RNCP', function () {
    Http::fake();
    $formation = Formation::factory()->create(['code_rncp' => null]);

    $resultats = app(LaBonneAlternanceClient::class)->searchForFormation($formation, 48.85, 2.35, 30);

    expect($resultats)->toBeEmpty();
    Http::assertNothingSent();
});

it('monte le formulaire de prospection (carte + calendrier + lieu) sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->syncRoles(['Commercial']);
    $this->actingAs($user);

    Livewire::test(ListNeeds::class)
        ->mountAction('prospecterLba')
        ->assertActionMounted('prospecterLba');
});

it('signale une clé API absente', function () {
    config()->set('services.labonnealternance.api_key', null);
    $client = app(LaBonneAlternanceClient::class);

    expect($client->isConfigured())->toBeFalse()
        ->and(fn () => $client->search(['rncp' => 'RNCP34029']))->toThrow(RuntimeException::class);
});
