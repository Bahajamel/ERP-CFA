<?php

use App\Filament\Resources\Candidates\Pages\ListCandidates;
use App\Models\Candidate;
use App\Models\CustomColumnSetting;
use App\Models\CustomFieldDefinition;
use App\Models\Organisation;
use App\Models\User;
use App\Support\CustomFields;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
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

it('renomme et redimensionne une colonne native via les surcharges', function () {
    CustomColumnSetting::create([
        'entity' => 'candidate',
        'column_key' => 'commercial.name',
        'label' => 'Chargé de compte',
        'width' => 200,
    ]);

    $colonnes = CustomFields::appliquerReglages([
        TextColumn::make('commercial.name')->label('Référent'),
        TextColumn::make('ville')->label('Ville'),
    ], 'candidate');

    // La colonne surchargée prend le nouveau libellé + la largeur imposée.
    expect($colonnes[0]->getLabel())->toBe('Chargé de compte')
        ->and($colonnes[0]->getWidth())->toBe('200px')
        // Toutes les colonnes reçoivent un data-col-key (cible du drag souris).
        ->and($colonnes[0]->getExtraHeaderAttributeBag()->get('data-col-key'))->toBe('commercial.name')
        ->and($colonnes[1]->getExtraHeaderAttributeBag()->get('data-col-key'))->toBe('ville')
        // La colonne non surchargée garde son libellé d'origine, sans largeur.
        ->and($colonnes[1]->getLabel())->toBe('Ville')
        ->and($colonnes[1]->getWidth())->toBeNull();
});

it('enregistre puis réinitialise la largeur d\'une colonne', function () {
    CustomFields::definirLargeur('candidate', 'ville', 180);

    expect(CustomColumnSetting::query()->where('column_key', 'ville')->value('width'))->toBe(180);

    // Réinitialisation (double-clic) : la ligne disparaît si plus aucune surcharge.
    CustomFields::definirLargeur('candidate', 'ville', null);

    expect(CustomColumnSetting::query()->where('column_key', 'ville')->exists())->toBeFalse();
});

it('borne la largeur entre 80 et 720 px', function () {
    CustomFields::definirLargeur('candidate', 'ville', 5000);
    expect(CustomColumnSetting::query()->where('column_key', 'ville')->value('width'))->toBe(720);

    CustomFields::definirLargeur('candidate', 'ville', 10);
    expect(CustomColumnSetting::query()->where('column_key', 'ville')->value('width'))->toBe(80);
});

it('conserve le libellé quand on ne touche qu\'à la largeur', function () {
    CustomColumnSetting::create([
        'entity' => 'candidate', 'column_key' => 'ville', 'label' => 'Localité',
    ]);

    CustomFields::definirLargeur('candidate', 'ville', 220);

    $reglage = CustomColumnSetting::query()->where('column_key', 'ville')->first();
    expect($reglage->label)->toBe('Localité')       // libellé préservé
        ->and($reglage->width)->toBe(220);
});

it('mémorise la largeur depuis la page via setLargeurColonne', function () {
    Livewire::test(ListCandidates::class)
        ->call('setLargeurColonne', 'ville', 240)
        ->assertSuccessful();

    expect(CustomColumnSetting::query()->where('column_key', 'ville')->value('width'))->toBe(240);

    // Une clé inconnue (hors colonnes personnalisables) est ignorée.
    Livewire::test(ListCandidates::class)
        ->call('setLargeurColonne', 'colonne_bidon', 300);

    expect(CustomColumnSetting::query()->where('column_key', 'colonne_bidon')->exists())->toBeFalse();
});

it('réserve la personnalisation des colonnes aux rôles autorisés', function () {
    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles('Commercial');
    $this->actingAs($commercial);

    CustomFields::definirLargeur('candidate', 'ville', 200);

    expect(CustomColumnSetting::query()->where('column_key', 'ville')->exists())->toBeFalse();
});

it('renomme une colonne native depuis le modal (surcharge par CFA)', function () {
    Livewire::test(ListCandidates::class)
        ->callAction('renommerColonnes', data: [
            'renoms' => ['commercial__name' => 'Chargé de compte'],
        ])
        ->assertHasNoActionErrors();

    expect(CustomColumnSetting::query()->where('column_key', 'commercial.name')->value('label'))
        ->toBe('Chargé de compte');
});

it('nomme les actions colonnes comme leur méthode de page (résolution du modal)', function () {
    // Filament résout une action mountée en cherchant la méthode « {nom}Action() ».
    // Si le nom interne diffère du nom de méthode, le modal ne s'ouvre pas.
    expect(CustomFields::gererAction('candidate', 'Candidats', 'ajouterColonne')->getName())
        ->toBe('ajouterColonne')
        ->and(CustomFields::personnaliserAction('candidate', 'Candidats', [], 'renommerColonnes')->getName())
        ->toBe('renommerColonnes');
});

it('réordonne les colonnes selon les positions enregistrées', function () {
    CustomFields::definirOrdre('candidate', ['ville', 'identite', 'statut']);

    $colonnes = CustomFields::appliquerReglages([
        TextColumn::make('identite'),
        TextColumn::make('statut'),
        TextColumn::make('ville'),
    ], 'candidate');

    expect(collect($colonnes)->map->getName()->all())->toBe(['ville', 'identite', 'statut']);
});

it('place les colonnes sans position après celles ordonnées (ordre d\'origine)', function () {
    CustomFields::definirOrdre('candidate', ['ville']); // seule « ville » repositionnée

    $colonnes = CustomFields::appliquerReglages([
        TextColumn::make('identite'),
        TextColumn::make('statut'),
        TextColumn::make('ville'),
    ], 'candidate');

    // « ville » (position 0) en tête, puis les autres dans leur ordre d'origine.
    expect(collect($colonnes)->map->getName()->all())->toBe(['ville', 'identite', 'statut']);
});

it('n\'efface pas le libellé ni la largeur quand on ne change que l\'ordre', function () {
    CustomColumnSetting::create(['entity' => 'candidate', 'column_key' => 'ville', 'label' => 'Localité', 'width' => 200]);

    CustomFields::definirOrdre('candidate', ['ville', 'identite']);

    $reglage = CustomColumnSetting::query()->where('column_key', 'ville')->first();
    expect($reglage->label)->toBe('Localité')
        ->and($reglage->width)->toBe(200)
        ->and($reglage->position)->toBe(0);
});

it('réserve le réordonnancement des colonnes aux rôles autorisés', function () {
    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles('Commercial');
    $this->actingAs($commercial);

    CustomFields::definirOrdre('candidate', ['ville', 'identite']);

    expect(CustomColumnSetting::query()->where('column_key', 'ville')->exists())->toBeFalse();
});

it('mémorise l\'ordre depuis la page et ignore les clés inconnues', function () {
    Livewire::test(ListCandidates::class)
        ->call('setOrdreColonnes', ['ville', 'identite', 'colonne_bidon', 'statut'])
        ->assertSuccessful();

    expect(CustomColumnSetting::query()->where('column_key', 'ville')->value('position'))->toBe(0)
        ->and(CustomColumnSetting::query()->where('column_key', 'identite')->value('position'))->toBe(1)
        // « colonne_bidon » filtrée → statut reste en position 2 (l'inconnue est retirée avant persistance).
        ->and(CustomColumnSetting::query()->where('column_key', 'statut')->value('position'))->toBe(2)
        ->and(CustomColumnSetting::query()->where('column_key', 'colonne_bidon')->exists())->toBeFalse();
});

it('supprime une colonne personnalisée et purge ses valeurs', function () {
    CustomFields::synchroniser('candidate', [
        ['label' => 'Priorité', 'type' => 'text', 'visible_table' => true],
    ]);
    $def = CustomFieldDefinition::query()->where('key', 'priorite')->firstOrFail();

    // Un candidat porte une valeur dans cette colonne.
    $candidat = Candidate::factory()->create(['custom_fields' => ['priorite' => 'Haute']]);

    CustomFields::supprimerColonne('candidate', $def->id);

    // La définition disparaît…
    expect(CustomFieldDefinition::query()->where('key', 'priorite')->exists())->toBeFalse()
        // …et la valeur orpheline est purgée de la ligne.
        ->and($candidat->fresh()->custom_fields)->not->toHaveKey('priorite');
});

it('réserve la suppression de colonne aux rôles autorisés', function () {
    CustomFields::synchroniser('candidate', [['label' => 'Priorité', 'type' => 'text']]);
    $def = CustomFieldDefinition::query()->where('key', 'priorite')->firstOrFail();

    $commercial = User::factory()->create(['is_active' => true]);
    $commercial->syncRoles('Commercial');
    $this->actingAs($commercial);

    CustomFields::supprimerColonne('candidate', $def->id);

    // Non autorisé → la colonne subsiste.
    expect(CustomFieldDefinition::query()->where('key', 'priorite')->exists())->toBeTrue();
});

it('isole les surcharges de colonnes par CFA (multi-tenant)', function () {
    CustomColumnSetting::create(['entity' => 'candidate', 'column_key' => 'ville', 'label' => 'À nous']);

    $autre = Organisation::create(['slug' => 'autre-cfa', 'nom' => 'Autre CFA', 'actif' => true]);
    CustomColumnSetting::create([
        'organisation_id' => $autre->id,
        'entity' => 'candidate', 'column_key' => 'ville', 'label' => 'Chez eux',
    ]);

    expect(CustomFields::reglagesColonnes('candidate')->get('ville')->label)->toBe('À nous');
});
