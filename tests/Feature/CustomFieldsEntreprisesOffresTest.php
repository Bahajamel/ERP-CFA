<?php

use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Filament\Resources\Needs\Pages\ListNeeds;
use App\Models\Company;
use App\Models\CustomFieldDefinition;
use App\Models\Need;
use App\Models\User;
use App\Support\CustomFields;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

it('ajoute une colonne personnalisée aux Entreprises (champ + colonne + valeur par défaut)', function () {
    CustomFields::synchroniser('company', [
        ['label' => 'Priorité', 'type' => 'select', 'options' => ['Haute', 'Basse'], 'visible_table' => true],
    ]);

    $defs = CustomFields::definitions('company');

    expect($defs)->toHaveCount(1)
        ->and($defs->first()->entity)->toBe('company')
        ->and(CustomFields::tableColumns('company'))->toHaveCount(1)
        ->and(CustomFields::formSchema('company'))->not->toBeEmpty();
});

it('ajoute une colonne personnalisée aux Offres', function () {
    CustomFields::synchroniser('need', [
        ['label' => 'Budget', 'type' => 'amount', 'visible_table' => true],
    ]);

    expect(CustomFields::definitions('need'))->toHaveCount(1)
        ->and(CustomFields::tableColumns('need'))->toHaveCount(1)
        ->and(CustomFields::formSchema('need'))->not->toBeEmpty();
});

it('supprime une colonne personnalisée et nettoie la valeur sur les Entreprises', function () {
    CustomFields::synchroniser('company', [
        ['label' => 'Note interne', 'type' => 'text'],
    ]);
    $def = CustomFieldDefinition::query()->where('entity', 'company')->firstOrFail();

    $company = Company::factory()->create(['custom_fields' => [$def->key => 'À rappeler']]);

    CustomFields::supprimerColonne('company', $def->id);

    expect(CustomFieldDefinition::query()->where('entity', 'company')->count())->toBe(0)
        ->and($company->fresh()->custom_fields ?? [])->not->toHaveKey($def->key);
});

it('affiche la barre « Configurer / Ajouter une colonne » sur les Entreprises', function () {
    Company::factory()->create();

    Livewire::test(ListCompanies::class)
        ->assertSuccessful()
        ->assertSee('Configurer')
        ->assertSee('Ajouter une colonne');
});

it('affiche la barre « Configurer / Ajouter une colonne » sur les Offres', function () {
    Need::factory()->create();

    Livewire::test(ListNeeds::class)
        ->assertSuccessful()
        ->assertSee('Configurer')
        ->assertSee('Ajouter une colonne');
});

it('renomme une colonne native des Entreprises (surcharge par CFA)', function () {
    Livewire::test(ListCompanies::class)
        ->callAction('renommerColonnes', data: [
            'renoms' => ['secteur' => 'Domaine'],
        ]);

    // La surcharge est mémorisée et réappliquée au tableau.
    expect(CustomFields::reglagesColonnes('company')->get('secteur')?->label)->toBe('Domaine');
});
