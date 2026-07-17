<?php

use App\Enums\CustomFieldType;
use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Filament\Resources\CustomTables\Pages\CreateCustomTable;
use App\Filament\Resources\CustomTables\Pages\EditCustomTable;
use App\Filament\Resources\CustomTables\RelationManagers\LignesRelationManager;
use App\Models\CustomTable;
use App\Models\Organisation;
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

it('crée un tableau personnalisé avec ses colonnes', function () {
    Livewire::test(CreateCustomTable::class)
        ->fillForm([
            'name' => 'Suivi partenariats',
            'colonnes' => [
                ['label' => 'Statut', 'type' => 'select', 'options' => ['Prospect', 'Signé'], 'visible_table' => true],
                ['label' => 'Montant', 'type' => 'number', 'visible_table' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $table = CustomTable::query()->where('name', 'Suivi partenariats')->first();

    expect($table)->not->toBeNull()
        ->and($table->colonnes)->toHaveCount(2);

    $colonnes = CustomFields::definitionsTableau($table->id);
    expect($colonnes->pluck('key')->all())->toBe(['statut', 'montant'])
        ->and($colonnes[0]->type)->toBe(CustomFieldType::Select)
        ->and($colonnes[0]->config['options'])->toBe(['Prospect', 'Signé']);
});

it('construit un formulaire et des colonnes dynamiques pour les lignes', function () {
    $table = CustomTable::create(['name' => 'T']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'Statut', 'type' => 'select', 'options' => ['A', 'B'], 'visible_table' => true],
        ['label' => 'Note', 'type' => 'textarea'],
    ]);

    $colonnes = $table->refresh()->colonnes;

    expect(CustomFields::champs($colonnes, 'data'))->toHaveCount(2)
        ->and(CustomFields::colonnes($colonnes, 'data'))->toHaveCount(2);
});

it('enregistre une ligne avec ses valeurs dans data (JSONB)', function () {
    $table = CustomTable::create(['name' => 'T']);
    CustomFields::synchroniserTableau($table->id, [['label' => 'Statut', 'type' => 'text']]);

    $ligne = $table->records()->create(['data' => ['statut' => 'Signé']]);

    expect($ligne->fresh()->data['statut'])->toBe('Signé')
        ->and($ligne->custom_table_id)->toBe($table->id)
        ->and($ligne->organisation_id)->not->toBeNull(); // rattachée au CFA courant
});

it('affiche le gestionnaire de lignes du tableau', function () {
    $table = CustomTable::create(['name' => 'T']);
    CustomFields::synchroniserTableau($table->id, [['label' => 'Statut', 'type' => 'text']]);
    $table->records()->create(['data' => ['statut' => 'Actif']]);

    Livewire::test(LignesRelationManager::class, [
        'ownerRecord' => $table->refresh(),
        'pageClass' => EditCustomTable::class,
    ])
        ->assertSuccessful()
        ->assertSee('Statut')
        ->assertSee('Actif');
});

it('répercute l\'ajout et la suppression de colonnes à l\'édition', function () {
    $table = CustomTable::create(['name' => 'T']);
    CustomFields::synchroniserTableau($table->id, [['label' => 'A', 'type' => 'text'], ['label' => 'B', 'type' => 'text']]);
    $ids = $table->colonnes()->pluck('id', 'key');

    // Renomme A, supprime B, ajoute C.
    CustomFields::synchroniserTableau($table->id, [
        ['id' => $ids['a'], 'label' => 'A renommé', 'type' => 'text'],
        ['label' => 'C', 'type' => 'number'],
    ]);

    $apres = CustomFields::definitionsTableau($table->id);
    expect($apres->pluck('label')->all())->toBe(['A renommé', 'C'])
        ->and($apres->firstWhere('label', 'A renommé')->id)->toBe($ids['a']); // clé/id stables
});

it('isole les tableaux personnalisés par CFA', function () {
    CustomTable::create(['name' => 'À nous']);

    $autre = Organisation::create(['slug' => 'autre-cfa', 'nom' => 'Autre CFA', 'actif' => true]);
    CustomTable::create(['organisation_id' => $autre->id, 'name' => 'Chez eux']);

    expect(CustomTable::query()->pluck('name')->all())->toBe(['À nous']);
});

it('réserve les tableaux personnalisés aux rôles Administrateur et Direction', function () {
    expect(CustomTableResource::canAccess())->toBeTrue();

    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles('Commercial');
    $this->actingAs($commercial);
    expect(CustomTableResource::canAccess())->toBeFalse();
});
