<?php

use App\Filament\Resources\CustomTables\Pages\KanbanCustomTable;
use App\Models\CustomRecord;
use App\Models\CustomTable;
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
