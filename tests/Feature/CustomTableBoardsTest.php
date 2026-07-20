<?php

use App\Filament\Resources\CustomTables\Pages\BoardCustomTable;
use App\Filament\Resources\CustomTables\Pages\KanbanCustomTable;
use App\Models\CustomRecord;
use App\Models\CustomTable;
use App\Models\CustomView;
use App\Models\User;
use App\Support\CustomFields;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->syncRoles('Administrateur');
    $this->actingAs($this->user);
});

/** Crée un tableau candidat avec colonnes Nom (texte requis) + Statut (statut). */
function tableauAvecStatut(): array
{
    $table = CustomTable::create(['name' => 'Vivier', 'context' => 'candidate']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'Nom', 'type' => 'text', 'is_required' => true, 'visible_table' => true],
        ['label' => 'Statut', 'type' => 'statut', 'options' => ['Nouveau', 'Traité'], 'visible_table' => true],
    ]);
    $table->refresh();
    $cles = $table->colonnes->keyBy('label');

    return [$table, $cles['Nom']->key, $cles['Statut']->key];
}

it('génère un lien de candidature (jeton public) à la création du tableau', function () {
    [$table] = tableauAvecStatut();

    expect($table->public_token)->not->toBeNull()
        ->and($table->lienCandidature())->toContain($table->public_token);
});

it('affiche le formulaire public d\'un tableau via son jeton', function () {
    [$table] = tableauAvecStatut();

    $this->get(route('tableau.candidature', ['token' => $table->public_token]))
        ->assertOk()
        ->assertSee('Vivier')
        ->assertSee('Nom')
        ->assertSee('Statut');
});

it('crée une ligne dans le tableau depuis le formulaire public (sans accès ERP)', function () {
    [$table, $cleNom, $cleStatut] = tableauAvecStatut();

    auth()->logout(); // le déposant est un visiteur anonyme

    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), [
        'champs' => [$cleNom => 'Léa Martin', $cleStatut => 'Nouveau'],
    ])->assertRedirect(route('tableau.candidature.merci'));

    $ligne = CustomRecord::withoutGlobalScopes()->where('custom_table_id', $table->id)->first();

    expect($ligne)->not->toBeNull()
        ->and($ligne->data[$cleNom])->toBe('Léa Martin')
        ->and($ligne->data[$cleStatut])->toBe('Nouveau')
        ->and($ligne->organisation_id)->toBe($table->organisation_id); // rattachée au bon CFA
});

it('rejette le formulaire public si un champ obligatoire manque', function () {
    [$table, $cleNom, $cleStatut] = tableauAvecStatut();
    auth()->logout();

    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), [
        'champs' => [$cleStatut => 'Nouveau'], // « Nom » requis manquant
    ])->assertSessionHasErrors("champs.{$cleNom}");

    expect(CustomRecord::withoutGlobalScopes()->where('custom_table_id', $table->id)->count())->toBe(0);
});

it('renvoie 404 pour un tableau archivé ou un jeton inconnu', function () {
    [$table] = tableauAvecStatut();
    $table->update(['is_active' => false]);

    $this->get(route('tableau.candidature', ['token' => $table->public_token]))->assertNotFound();
    $this->get(route('tableau.candidature', ['token' => 'jeton-bidon']))->assertNotFound();
});

it('affiche le kanban groupé par statut et déplace une carte (change le statut)', function () {
    [$table, $cleNom, $cleStatut] = tableauAvecStatut();
    $ligne = CustomRecord::create(['custom_table_id' => $table->id, 'data' => [$cleNom => 'Léa', $cleStatut => 'Nouveau']]);

    Livewire::test(KanbanCustomTable::class, ['record' => $table->id])
        ->assertSuccessful()
        ->assertSee('Nouveau')
        ->assertSee('Traité')
        ->assertSee('Léa')
        ->call('moveCard', $ligne->id, 'Traité');

    expect($ligne->fresh()->data[$cleStatut])->toBe('Traité');
});

it('supprime un tableau depuis le board (mise en corbeille)', function () {
    [$table] = tableauAvecStatut();

    Livewire::test(BoardCustomTable::class, ['record' => $table->id])
        ->callAction('supprimer');

    expect(CustomTable::query()->find($table->id))->toBeNull()            // hors des listes
        ->and(CustomTable::withTrashed()->find($table->id))->not->toBeNull(); // récupérable
});

it('n\'expose pas la suppression de tableau à un rôle sans le droit', function () {
    [$table] = tableauAvecStatut();

    // Direction/Commercial n'ont pas custom_tables.delete → action masquée.
    $direction = User::factory()->create(['is_active' => true]);
    $direction->syncRoles('Direction');
    $this->actingAs($direction);

    Livewire::test(BoardCustomTable::class, ['record' => $table->id])
        ->assertActionHidden('supprimer');
});

it('désactive le formulaire public d\'un tableau (lien inopérant)', function () {
    [$table] = tableauAvecStatut();
    $table->update(['public_enabled' => false]);

    $this->get(route('tableau.candidature', ['token' => $table->public_token]))->assertNotFound();

    auth()->logout();
    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), ['champs' => []])
        ->assertNotFound();
});

it('régénère le jeton public : l\'ancien lien cesse de fonctionner', function () {
    [$table] = tableauAvecStatut();
    $ancien = $table->public_token;

    $table->regenererToken();

    expect($table->public_token)->not->toBe($ancien);
    $this->get(route('tableau.candidature', ['token' => $ancien]))->assertNotFound();       // ancien lien mort
    $this->get(route('tableau.candidature', ['token' => $table->public_token]))->assertOk(); // nouveau lien OK
});

it('active/désactive le formulaire public depuis le board', function () {
    [$table] = tableauAvecStatut();

    Livewire::test(BoardCustomTable::class, ['record' => $table->id])
        ->callAction('lienCandidature', data: ['public_enabled' => false]);

    expect($table->fresh()->public_enabled)->toBeFalse();
});

it('notifie le créateur du tableau à la réception d\'une soumission publique', function () {
    [$table, $cleNom, $cleStatut] = tableauAvecStatut();
    expect($table->created_by)->toBe($this->user->id);

    auth()->logout();
    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), [
        'champs' => [$cleNom => 'Léa', $cleStatut => 'Nouveau'],
    ])->assertRedirect(route('tableau.candidature.merci'));

    expect($this->user->fresh()->notifications()->count())->toBe(1);
});

it('applique la validation d\'une colonne au formulaire public (longueur max)', function () {
    $table = CustomTable::create(['name' => 'Codes', 'context' => 'candidate']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'Code', 'type' => 'text', 'is_required' => true, 'val_max_length' => 3, 'visible_table' => true],
    ]);
    $cle = $table->refresh()->colonnes->first()->key;

    auth()->logout();

    // Trop long → rejeté.
    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), [
        'champs' => [$cle => 'TROPLONG'],
    ])->assertSessionHasErrors("champs.{$cle}");

    // Dans la limite → accepté.
    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), [
        'champs' => [$cle => 'AB1'],
    ])->assertRedirect(route('tableau.candidature.merci'));

    expect(CustomRecord::withoutGlobalScopes()->where('custom_table_id', $table->id)->count())->toBe(1);
});

it('enregistre une vue en capturant l\'état des colonnes (ordre + visibilité)', function () {
    [$table] = tableauAvecStatut();

    Livewire::test(BoardCustomTable::class, ['record' => $table->id])
        ->callTableAction('enregistrerVue', data: ['name' => 'Ma vue', 'is_default' => true])
        ->assertHasNoTableActionErrors();

    $vue = CustomView::query()->where('custom_table_id', $table->id)->first();

    expect($vue)->not->toBeNull()
        ->and($vue->name)->toBe('Ma vue')
        ->and($vue->is_default)->toBeTrue()
        ->and($vue->column_order)->toBeArray()      // état des colonnes capturé
        ->and($vue->column_order)->not->toBeEmpty();
});

it('restaure une vue enregistrée (colonnes) sans erreur', function () {
    [$table] = tableauAvecStatut();
    $vue = CustomView::create([
        'custom_table_id' => $table->id,
        'name' => 'V',
        'sort' => ['tableSort' => null],
        'filters' => [],
        'column_order' => [[
            'type' => 'column', 'name' => 'data.'.$table->colonnes->first()->key, 'label' => 'Nom',
            'isHidden' => false, 'isToggled' => true, 'isToggleable' => true, 'isToggledHiddenByDefault' => false,
        ]],
    ]);

    Livewire::test(BoardCustomTable::class, ['record' => $table->id])
        ->callTableAction('vue_'.$vue->id)
        ->assertSuccessful();
});

it('regroupe le board par une colonne statut sans erreur (façon Monday)', function () {
    [$table, $cleNom, $cleStatut] = tableauAvecStatut();
    CustomRecord::create(['custom_table_id' => $table->id, 'data' => [$cleNom => 'Léa', $cleStatut => 'Nouveau']]);

    Livewire::test(BoardCustomTable::class, ['record' => $table->id])
        ->set('tableGrouping', $cleStatut)
        ->assertSuccessful()
        ->assertSee('Nouveau');
});

it('édite une ligne depuis une carte Kanban (détail de carte)', function () {
    [$table, $cleNom, $cleStatut] = tableauAvecStatut();
    $ligne = CustomRecord::create(['custom_table_id' => $table->id, 'data' => [$cleNom => 'Léa', $cleStatut => 'Nouveau']]);

    Livewire::test(KanbanCustomTable::class, ['record' => $table->id])
        ->callAction('modifierCarte',
            data: ['data' => [$cleNom => 'Léa Martin', $cleStatut => 'Traité']],
            arguments: ['record' => $ligne->id],
        )
        ->assertHasNoActionErrors();

    expect($ligne->fresh()->data[$cleNom])->toBe('Léa Martin')
        ->and($ligne->fresh()->data[$cleStatut])->toBe('Traité');
});

it('accepte e-mail et multi-sélection via le formulaire public (types avancés)', function () {
    $table = CustomTable::create(['name' => 'Avancé', 'context' => 'candidate']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'Email', 'type' => 'email', 'is_required' => true, 'visible_table' => true],
        ['label' => 'Tags', 'type' => 'multiselect', 'options' => ['A', 'B', 'C'], 'visible_table' => true],
    ]);
    $table->refresh();
    $cleEmail = $table->colonnes->firstWhere('label', 'Email')->key;
    $cleTags = $table->colonnes->firstWhere('label', 'Tags')->key;

    auth()->logout();

    // E-mail invalide → rejeté.
    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), [
        'champs' => [$cleEmail => 'pas-un-email'],
    ])->assertSessionHasErrors("champs.{$cleEmail}");

    // Valide (e-mail + multi-sélection).
    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), [
        'champs' => [$cleEmail => 'a@b.fr', $cleTags => ['A', 'C']],
    ])->assertRedirect(route('tableau.candidature.merci'));

    $ligne = CustomRecord::withoutGlobalScopes()->where('custom_table_id', $table->id)->first();
    expect($ligne->data[$cleEmail])->toBe('a@b.fr')
        ->and($ligne->data[$cleTags])->toBe(['A', 'C']);
});

it('reçoit une pièce jointe (CV) via le formulaire public et la stocke', function () {
    Storage::fake(CustomFields::DISQUE_FICHIERS);

    $table = CustomTable::create(['name' => 'Vivier', 'context' => 'candidate']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'CV', 'type' => 'file', 'is_required' => true, 'visible_table' => true],
    ]);
    $table->refresh();
    $cleCv = $table->colonnes->firstWhere('label', 'CV')->key;

    auth()->logout();

    // Sans fichier alors qu'il est requis → rejeté.
    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), ['champs' => []])
        ->assertSessionHasErrors("champs.{$cleCv}");

    // Avec un PDF valide → ligne créée + fichier stocké sur le disque.
    $pdf = File::create('cv.pdf', 20);
    $this->post(route('tableau.candidature.store', ['token' => $table->public_token]), [
        'champs' => [$cleCv => $pdf],
    ])->assertRedirect(route('tableau.candidature.merci'));

    $ligne = CustomRecord::withoutGlobalScopes()->where('custom_table_id', $table->id)->first();

    expect($ligne)->not->toBeNull()
        ->and($ligne->data[$cleCv])->toStartWith(CustomFields::DOSSIER_FICHIERS.'/');
    Storage::disk(CustomFields::DISQUE_FICHIERS)->assertExists($ligne->data[$cleCv]);
});

it('n\'expose pas les colonnes Relation dans le formulaire public', function () {
    $table = CustomTable::create(['name' => 'Suivi', 'context' => 'candidate']);
    CustomFields::synchroniserTableau($table->id, [
        ['label' => 'Nom', 'type' => 'text', 'visible_table' => true],
        ['label' => 'Entreprise liée', 'type' => 'relation', 'relation_cible' => 'company', 'visible_table' => true],
    ]);
    $table->refresh();

    $this->get(route('tableau.candidature', ['token' => $table->public_token]))
        ->assertOk()
        ->assertSee('Nom')
        ->assertDontSee('Entreprise liée'); // relation masquée au public
});

it('exporte les lignes du tableau en CSV', function () {
    [$table, $cleNom, $cleStatut] = tableauAvecStatut();
    CustomRecord::create(['custom_table_id' => $table->id, 'data' => [$cleNom => 'Léa', $cleStatut => 'Nouveau']]);

    $page = Livewire::test(BoardCustomTable::class, ['record' => $table->id])->instance();
    $response = $page->exporterCsv($table->refresh());

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Nom')->toContain('Statut')->toContain('Léa')->toContain('Nouveau');
});

it('importe des lignes depuis un CSV (en-têtes = noms de colonnes)', function () {
    [$table, $cleNom, $cleStatut] = tableauAvecStatut();

    $chemin = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($chemin, "\xEF\xBB\xBF"."Nom;Statut\nLéa;Nouveau\nTom;Traité\n");

    $page = Livewire::test(BoardCustomTable::class, ['record' => $table->id])->instance();
    $compte = $page->importerDepuisChemin($table->refresh(), $chemin);
    @unlink($chemin);

    expect($compte)->toBe(2)
        ->and(CustomRecord::query()->where('custom_table_id', $table->id)->count())->toBe(2)
        ->and(CustomRecord::query()->where('custom_table_id', $table->id)->get()->pluck("data.{$cleNom}")->all())
        ->toContain('Léa', 'Tom');
});
