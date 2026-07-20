<?php

use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use App\Models\CustomTable;
use App\Models\CustomView;
use App\Models\Organisation;
use App\Models\User;
use App\Support\CustomFields;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

it('propose les nouveaux types Statut et Utilisateur', function () {
    expect(CustomFieldType::options())->toHaveKeys(['statut', 'user'])
        ->and(CustomFieldType::Statut->needsOptions())->toBeTrue()   // liste d'options
        ->and(CustomFieldType::Utilisateur->needsOptions())->toBeFalse();
});

it('construit un filtre par colonne Liste/Statut (et aucun pour les autres types)', function () {
    $table = CustomTable::create(['name' => 'Suivi']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'Statut', 'type' => 'statut', 'options' => ['Ouvert', 'Fermé']],
        ['label' => 'Ville', 'type' => 'text'],
        ['label' => 'Catégorie', 'type' => 'select', 'options' => ['A', 'B']],
        ['label' => 'Responsable', 'type' => 'user'],
    ]);

    $defs = CustomFields::definitionsTableau($table->id);

    // Statut + Select => 2 filtres ; Texte et Utilisateur => aucun.
    expect(CustomFields::filtres($defs, 'data'))->toHaveCount(2);
});

it('génère champs et colonnes pour Statut et Utilisateur sans erreur', function () {
    $table = CustomTable::create(['name' => 'Suivi']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'Statut', 'type' => 'statut', 'options' => ['Ouvert', 'Fermé']],
        ['label' => 'Responsable', 'type' => 'user'],
    ]);

    $defs = CustomFields::definitionsTableau($table->id);

    expect(CustomFields::champs($defs, 'data'))->toHaveCount(2)
        ->and(CustomFields::colonnes($defs, 'data'))->toHaveCount(2);
});

it('permet d\'ajouter une option à une liste/statut à la volée (façon Monday)', function () {
    $table = CustomTable::create(['name' => 'Suivi']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'Statut', 'type' => 'statut', 'options' => ['À contacter', 'Chaud']],
    ]);
    $def = CustomFieldDefinition::query()->where('custom_table_id', $table->id)->firstOrFail();

    $retour = CustomFields::ajouterOption($def, 'Perdu');

    expect($retour)->toBe('Perdu')                                      // valeur sélectionnée
        ->and($def->fresh()->config['options'])->toBe(['À contacter', 'Chaud', 'Perdu']);

    // Pas de doublon, ni valeur vide.
    CustomFields::ajouterOption($def->fresh(), 'Chaud');
    CustomFields::ajouterOption($def->fresh(), '   ');
    expect($def->fresh()->config['options'])->toBe(['À contacter', 'Chaud', 'Perdu']);
});

it('persiste le caractère obligatoire (is_required) d\'une colonne', function () {
    $table = CustomTable::create(['name' => 'Suivi']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'Nom', 'type' => 'text', 'is_required' => true],
        ['label' => 'Note', 'type' => 'text', 'is_required' => false],
    ]);

    $defs = CustomFieldDefinition::query()->where('custom_table_id', $table->id)->orderBy('sort')->get();

    expect($defs[0]->is_required)->toBeTrue()
        ->and($defs[1]->is_required)->toBeFalse();
});

it('ne conserve qu\'une seule vue par défaut par tableau', function () {
    $table = CustomTable::create(['name' => 'Suivi']);

    $v1 = CustomView::create(['custom_table_id' => $table->id, 'name' => 'Vue 1', 'is_default' => true]);
    $v2 = CustomView::create(['custom_table_id' => $table->id, 'name' => 'Vue 2', 'is_default' => true]);

    expect($v1->fresh()->is_default)->toBeFalse()      // détrônée
        ->and($v2->fresh()->is_default)->toBeTrue()
        ->and($v2->created_by)->toBe($this->user->id); // auteur tracé
});

it('isole les vues enregistrées par CFA', function () {
    $table = CustomTable::create(['name' => 'Suivi']);
    CustomView::create(['custom_table_id' => $table->id, 'name' => 'À nous']);

    $autre = Organisation::create(['slug' => 'autre-cfa', 'nom' => 'Autre CFA', 'actif' => true]);
    CustomView::create([
        'organisation_id' => $autre->id,
        'custom_table_id' => $table->id,
        'name' => 'Chez eux',
    ]);

    expect(CustomView::query()->pluck('name')->all())->toBe(['À nous']);
});

it('journalise la création et l\'archivage d\'un tableau (historique)', function () {
    $table = CustomTable::create(['name' => 'Historisé']);
    $table->update(['is_active' => false]); // archivage

    $activites = Activity::query()
        ->where('log_name', 'table_personnalisee')
        ->where('subject_id', $table->id)
        ->count();

    expect($activites)->toBeGreaterThanOrEqual(1);
});
