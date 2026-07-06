<?php

use App\Enums\CompanyStatut;
use App\Filament\Resources\Candidates\Pages\ViewCandidate;
use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Needs\Pages\CreateNeed;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Models\Formation;
use App\Models\Need;
use App\Models\User;
use App\Support\AdresseBan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function adminUser(): User
{
    $u = User::factory()->create(['is_active' => true]);
    $u->syncRoles(['Administrateur']);

    return $u;
}

/*
|--------------------------------------------------------------------------
| AdresseBan — coordonnées GPS (partagé Entreprises / Besoins)
|--------------------------------------------------------------------------
*/

it('expose les coordonnées GPS renvoyées par la Base Adresse Nationale', function () {
    Http::fake([
        'api-adresse.data.gouv.fr/*' => Http::response([
            'features' => [[
                'geometry' => ['type' => 'Point', 'coordinates' => [4.8357, 45.7640]],
                'properties' => ['label' => '5 Avenue de la République 69003 Lyon', 'name' => '5 Avenue de la République', 'postcode' => '69003', 'city' => 'Lyon'],
            ]],
        ]),
    ]);

    $options = app(AdresseBan::class)->options('5 avenue republique lyon');
    expect($options)->toHaveCount(1);

    $data = AdresseBan::decode(array_key_first($options));
    expect($data['ville'])->toBe('Lyon')
        ->and($data['code_postal'])->toBe('69003')
        ->and($data['latitude'])->toBe(45.7640)
        ->and($data['longitude'])->toBe(4.8357);
});

/*
|--------------------------------------------------------------------------
| Candidats — disponibilités affichées dans la fiche + persistance
|--------------------------------------------------------------------------
*/

it('affiche la date de disponibilité du candidat dans la fiche 360°', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());

    $candidate = Candidate::factory()->create(['date_disponibilite' => '2026-09-01']);

    Livewire::test(ViewCandidate::class, ['record' => $candidate->getKey()])
        ->assertOk()
        ->assertSee('Disponible à partir du')
        ->assertSee('01/09/2026');
});

it('conserve la date de disponibilité après une modification du candidat', function () {
    $candidate = Candidate::factory()->create(['date_disponibilite' => '2026-09-01']);

    $candidate->update(['nom' => 'Nom modifié']);

    expect($candidate->fresh()->date_disponibilite->format('Y-m-d'))->toBe('2026-09-01');
});

/*
|--------------------------------------------------------------------------
| Entreprises — création sans active/inactive + adresse structurée
|--------------------------------------------------------------------------
*/

it('crée une entreprise sans demander le statut : défaut « Prospect »', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());

    Livewire::test(CreateCompany::class)
        ->fillForm([
            'raison_sociale' => 'Boulangerie Test SARL',
            'siret' => '12345678900012',
            'adresse' => '5 avenue de la République',
            'code_postal' => '69003',
            'ville' => 'Lyon',
            'latitude' => 45.7640,
            'longitude' => 4.8357,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $company = Company::query()->where('siret', '12345678900012')->firstOrFail();

    expect($company->statut)->toBe(CompanyStatut::Prospect)
        ->and($company->ville)->toBe('Lyon')
        ->and($company->code_postal)->toBe('69003')
        ->and((float) $company->latitude)->toBe(45.7640);
});

/*
|--------------------------------------------------------------------------
| Besoins — rayon + géolocalisation
|--------------------------------------------------------------------------
*/

it('enregistre le rayon de recherche et les coordonnées d\'un besoin', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());

    $company = Company::factory()->create();
    $formation = Formation::factory()->create();

    Livewire::test(CreateNeed::class)
        ->fillForm([
            'company_id' => $company->id,
            'intitule_poste' => 'Apprenti boulanger',
            'formation_id' => $formation->id,
            'nb_postes' => 2,
            'localisation' => 'Lyon 3e',
            'latitude' => 45.7640,
            'longitude' => 4.8357,
            'rayon_km' => 25,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $need = Need::query()->where('intitule_poste', 'Apprenti boulanger')->firstOrFail();

    expect($need->rayon_km)->toBe(25)
        ->and((float) $need->latitude)->toBe(45.7640)
        ->and((float) $need->longitude)->toBe(4.8357);
});

it('rend le formulaire de besoin (carte du rayon) sans erreur', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());

    Livewire::test(CreateNeed::class)->assertOk();
});

/*
|--------------------------------------------------------------------------
| Contrats — tuteurs filtrés par entreprise (garde backend)
|--------------------------------------------------------------------------
*/

it('refuse un tuteur qui n\'appartient pas à l\'entreprise du contrat', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());

    $entrepriseA = Company::factory()->create();
    $entrepriseB = Company::factory()->create();
    $tuteurA = CompanyContact::factory()->tuteur()->create(['company_id' => $entrepriseA->id]);
    $tuteurB = CompanyContact::factory()->tuteur()->create(['company_id' => $entrepriseB->id]);

    $contract = Contract::factory()->create([
        'company_id' => $entrepriseA->id,
        'tuteur_id' => $tuteurA->id,
    ]);

    // Tuteur d'une autre entreprise → rejeté (UI + backend).
    Livewire::test(EditContract::class, ['record' => $contract->getKey()])
        ->fillForm(['tuteur_id' => $tuteurB->id])
        ->call('save')
        ->assertHasFormErrors(['tuteur_id']);

    expect($contract->fresh()->tuteur_id)->toBe($tuteurA->id);
});

it('accepte un tuteur de la bonne entreprise', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminUser());

    $entreprise = Company::factory()->create();
    $tuteur1 = CompanyContact::factory()->tuteur()->create(['company_id' => $entreprise->id]);
    $tuteur2 = CompanyContact::factory()->tuteur()->create(['company_id' => $entreprise->id]);

    $contract = Contract::factory()->create([
        'company_id' => $entreprise->id,
        'tuteur_id' => $tuteur1->id,
    ]);

    Livewire::test(EditContract::class, ['record' => $contract->getKey()])
        ->fillForm(['tuteur_id' => $tuteur2->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contract->fresh()->tuteur_id)->toBe($tuteur2->id);
});
