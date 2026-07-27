<?php

use App\Enums\CustomFieldType;
use App\Filament\Resources\Candidates\Pages\CreateCandidate;
use App\Models\Candidate;
use App\Models\CustomFieldDefinition;
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

it('génère un champ de formulaire et une colonne de tableau depuis une définition', function () {
    CustomFieldDefinition::create([
        'entity' => 'candidate',
        'key' => 'reference_interne',
        'label' => 'Référence interne',
        'type' => 'text',
        'visible_table' => true,
    ]);

    expect(CustomFields::formSchema('candidate'))->toHaveCount(1)
        ->and(CustomFields::tableColumns('candidate'))->toHaveCount(1)
        ->and(CustomFields::formSchema('company'))->toBe([]); // aucune définition → rien
});

it('enregistre la valeur d\'un champ personnalisé à la création d\'un candidat', function () {
    CustomFieldDefinition::create([
        'entity' => 'candidate',
        'key' => 'reference_interne',
        'label' => 'Référence interne',
        'type' => 'text',
    ]);

    Livewire::test(CreateCandidate::class)
        ->fillForm([
            'nom' => 'Test',
            'prenom' => 'Custom',
            'email' => 'custom@exemple.fr',
            'custom_fields' => ['reference_interne' => 'REF-2026-001'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Candidate::query()->where('email', 'custom@exemple.fr')->first()->custom_fields['reference_interne'])
        ->toBe('REF-2026-001');
});

it('synchronise les colonnes depuis le modal : création, mise à jour, suppression', function () {
    // Création de 2 colonnes.
    CustomFields::synchroniser('candidate', [
        ['label' => 'Référence', 'type' => 'text', 'visible_table' => true],
        ['label' => 'Priorité', 'type' => 'select', 'options' => ['Basse', 'Haute'], 'visible_table' => true],
    ]);

    $defs = CustomFieldDefinition::query()->where('entity', 'candidate')->orderBy('sort')->get();

    expect($defs)->toHaveCount(2)
        ->and($defs[0]->key)->toBe('reference')
        ->and($defs[1]->config['options'])->toBe(['Basse', 'Haute']);

    // Mise à jour de la 1re (id conservé), suppression de « Priorité », ajout de « Notes ».
    CustomFields::synchroniser('candidate', [
        ['id' => $defs[0]->id, 'label' => 'Référence interne', 'type' => 'text', 'visible_table' => false],
        ['label' => 'Notes', 'type' => 'textarea', 'visible_table' => true],
    ]);

    $apres = CustomFieldDefinition::query()->where('entity', 'candidate')->orderBy('sort')->get();

    expect($apres)->toHaveCount(2)
        ->and($apres[0]->id)->toBe($defs[0]->id)                 // clé/id stables
        ->and($apres[0]->label)->toBe('Référence interne')
        ->and($apres[0]->visible_table)->toBeFalse()
        ->and($apres->pluck('label')->all())->toBe(['Référence interne', 'Notes']);

    expect(CustomFieldDefinition::query()->where('key', 'priorite')->exists())->toBeFalse();
});

it('réserve la gestion des colonnes aux rôles Administrateur et Direction', function () {
    expect(CustomFields::peutGerer())->toBeTrue(); // Administrateur (beforeEach)

    $direction = User::factory()->create(['is_active' => true]);
    $direction->syncRoles('Direction');
    $this->actingAs($direction);
    expect(CustomFields::peutGerer())->toBeTrue();

    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles('Commercial');
    $this->actingAs($commercial);
    expect(CustomFields::peutGerer())->toBeFalse();
});

it('isole les colonnes personnalisées par CFA (multi-tenant)', function () {
    CustomFieldDefinition::create(['entity' => 'candidate', 'key' => 'a_nous', 'label' => 'À nous', 'type' => 'text']);

    $autre = Organisation::create(['slug' => 'autre-cfa', 'nom' => 'Autre CFA', 'actif' => true]);
    CustomFieldDefinition::create([
        'organisation_id' => $autre->id,
        'entity' => 'candidate', 'key' => 'chez_eux', 'label' => 'Chez eux', 'type' => 'text',
    ]);

    expect(CustomFields::definitions('candidate')->pluck('key')->all())->toBe(['a_nous']);
});

it('caste correctement le type et les options', function () {
    $def = CustomFieldDefinition::create([
        'entity' => 'candidate', 'key' => 'priorite', 'label' => 'Priorité', 'type' => 'select',
        'config' => ['options' => ['Basse', 'Haute', 'Urgente']],
    ]);

    expect($def->fresh()->type)->toBe(CustomFieldType::Select)
        ->and($def->fresh()->config['options'])->toBe(['Basse', 'Haute', 'Urgente']);
});
