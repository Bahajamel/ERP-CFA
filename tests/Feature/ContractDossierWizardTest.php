<?php

use App\Enums\ContractStatut;
use App\Enums\ModaliteSuivi;
use App\Enums\TypeContrat;
use App\Filament\Resources\Contracts\Pages\CreateContract;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Formation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Création d'un dossier contrat via l'assistant en 4 étapes.
 *
 * Exigence structurante : le dossier n'est créé qu'à la soumission finale
 * (étape 4). L'assistant retrouve/réutilise l'étudiant et l'entreprise déjà
 * connus plutôt que de les dupliquer, et refuse un doublon de dossier.
 */
function adminWizard(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->syncRoles(['Administrateur']);

    return $user;
}

/**
 * Jeu de données valide pour l'ensemble de l'assistant. Surcharge possible par
 * clé pour couvrir les cas particuliers.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function donneesWizard(Formation $formation, array $overrides = []): array
{
    return array_merge([
        'mode_dossier' => 'autonome',
        'etudiant_email' => 'lea.martin@example.com',
        'etudiant_prenom' => 'Léa',
        'etudiant_nom' => 'Martin',
        'formation_id' => $formation->id,
        'promotion_id' => null,

        'type_contrat' => TypeContrat::Apprentissage->value,
        'duree_formation_heures' => 455,
        'nombre_organismes_formation' => 1,
        'modalite_suivi' => ModaliteSuivi::Distance->value,
        'heures_elearning' => 400,
        'heures_classe_virtuelle' => 55,
        'cout_formation' => 8445,
        'duree_diplome' => 'jusqu_1_an',
        'annee_cycle' => '1',
        'reste_a_charge_zero' => 0,
        'date_debut' => '2026-09-01',
        'date_fin' => '2027-08-31',

        'contact_email' => 'contact@bidule.fr',
        'contact_prenom' => 'Paul',
        'contact_nom' => 'Durand',

        'entreprise_siret' => '12345678900012',
        'entreprise_raison_sociale' => 'Bidule SARL',
        'entreprise_nom_commercial' => 'Chez Bidule',
        'entreprise_forme_juridique' => 'SARL',
        'entreprise_siren' => '123456789',
        'entreprise_siret_etablissement' => null,
        'entreprise_ville_rcs' => 'Lyon',
        'entreprise_numero_siege' => '15',
        'entreprise_adresse' => 'rue Garibaldi',
        'entreprise_complement_adresse' => null,
        'entreprise_code_postal' => '69003',
        'entreprise_ville' => 'Lyon',

        'representant_prenom' => 'Sophie',
        'representant_nom' => 'Bidule',
        'representant_email' => 'sophie@bidule.fr',
        'representant_poste' => 'Gérante',
    ], $overrides);
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(adminWizard());
});

it('crée un dossier complet à la soumission finale, avec toutes les entités liées', function () {
    $formation = Formation::factory()->create(['libelle' => 'BTS SIO']);

    Livewire::test(CreateContract::class)
        ->fillForm(donneesWizard($formation))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Contract::count())->toBe(1)
        ->and(Candidate::count())->toBe(1)
        ->and(Company::count())->toBe(1);

    $contract = Contract::first();

    // Rattachements
    expect($contract->candidate->email)->toBe('lea.martin@example.com')
        ->and($contract->candidate->formation_visee_id)->toBe($formation->id)
        ->and($contract->company->siret)->toBe('12345678900012')
        ->and($contract->company->siren)->toBe('123456789')
        ->and($contract->company->forme_juridique)->toBe('SARL')
        ->and($contract->formation_id)->toBe($formation->id);

    // Champs du dossier (destinés au CERFA / à la convention)
    expect($contract->type_contrat)->toBe(TypeContrat::Apprentissage)
        ->and($contract->modalite_suivi)->toBe(ModaliteSuivi::Distance)
        ->and($contract->duree_formation_heures)->toBe(455)
        ->and($contract->heures_elearning)->toBe(400)
        ->and((float) $contract->cout_formation)->toBe(8445.0)
        ->and($contract->statut_contrat)->toBe(ContractStatut::EnCours);

    // Contacts entreprise
    $company = $contract->company;
    expect($company->contactPrincipal()->where('email', 'contact@bidule.fr')->exists())->toBeTrue()
        ->and($company->representantsLegaux()->where('email', 'sophie@bidule.fr')->where('fonction', 'Gérante')->exists())->toBeTrue();
});

it('ne crée aucun dossier tant que la soumission finale n’est pas déclenchée', function () {
    $formation = Formation::factory()->create();

    Livewire::test(CreateContract::class)
        ->fillForm(donneesWizard($formation));
    // Pas d'appel à create() : rien ne doit être écrit.

    expect(Contract::count())->toBe(0)
        ->and(Candidate::count())->toBe(0)
        ->and(Company::count())->toBe(0);
});

it('réutilise l’entreprise déjà enregistrée pour le même SIRET au lieu de la dupliquer', function () {
    $formation = Formation::factory()->create();
    $existante = Company::factory()->create([
        'siret' => '12345678900012',
        'raison_sociale' => 'Bidule SARL',
    ]);

    Livewire::test(CreateContract::class)
        ->fillForm(donneesWizard($formation))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Company::count())->toBe(1)
        ->and(Contract::first()->company_id)->toBe($existante->id);
});

it('retrouve l’étudiant déjà enregistré par email au lieu de le dupliquer', function () {
    $formation = Formation::factory()->create();
    $existant = Candidate::factory()->create([
        'email' => 'lea.martin@example.com',
        'nom' => 'Martin',
        'prenom' => 'Léa',
    ]);

    Livewire::test(CreateContract::class)
        ->fillForm(donneesWizard($formation))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Candidate::count())->toBe(1)
        ->and(Contract::first()->candidate_id)->toBe($existant->id);
});

it('refuse un second dossier pour le même étudiant et la même entreprise', function () {
    $formation = Formation::factory()->create();
    $candidate = Candidate::factory()->create(['email' => 'lea.martin@example.com']);
    $company = Company::factory()->create(['siret' => '12345678900012']);

    Contract::factory()->create([
        'candidate_id' => $candidate->id,
        'company_id' => $company->id,
        'statut_contrat' => ContractStatut::EnCours,
    ]);

    Livewire::test(CreateContract::class)
        ->fillForm(donneesWizard($formation))
        ->call('create');

    // Le dossier existant reste seul : aucun doublon créé.
    expect(Contract::count())->toBe(1);
});

it('exige les champs obligatoires (email étudiant)', function () {
    $formation = Formation::factory()->create();

    Livewire::test(CreateContract::class)
        ->fillForm(donneesWizard($formation, ['etudiant_email' => null]))
        ->call('create')
        ->assertHasFormErrors(['etudiant_email' => 'required']);

    expect(Contract::count())->toBe(0);
});

it('refuse une date de fin antérieure à la date de début', function () {
    $formation = Formation::factory()->create();

    Livewire::test(CreateContract::class)
        ->fillForm(donneesWizard($formation, [
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-08-01',
        ]))
        ->call('create')
        ->assertHasFormErrors(['date_fin']);

    expect(Contract::count())->toBe(0);
});
