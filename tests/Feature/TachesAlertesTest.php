<?php

use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Support\TachesAlertes\TachesAlertesData;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->syncRoles('Administrateur');
    $this->actingAs($this->admin);
});

/** Crée une tâche avec des attributs maîtrisés. */
function tache(array $attrs = []): Task
{
    return Task::factory()->create(array_merge([
        'assignee_id' => null,
        'created_by' => null,
        'source' => 'manuel',
        'statut' => TaskStatut::AFaire->value,
        'priorite' => TaskPriorite::Normale->value,
        'due_date' => now()->addWeek()->toDateString(),
    ], $attrs));
}

// ─── Couche données ─────────────────────────────────────────────────────

it('calcule correctement les KPI', function () {
    tache(['due_date' => now()->toDateString()]);                                   // à traiter aujourd'hui
    tache(['due_date' => now()->subDays(3)->toDateString()]);                        // en retard
    tache(['priorite' => TaskPriorite::Urgente->value]);                             // urgente
    tache(['source' => 'auto', 'cle' => 'contrat:signature:1']);                     // alerte système
    tache(['statut' => TaskStatut::EnAttente->value]);                               // bloqué (= en attente)
    tache(['assignee_id' => $this->admin->id]);                                      // assignée (pas dans « non assignées »)

    $kpis = collect(app(TachesAlertesData::class)->kpis())->keyBy('cle');

    expect($kpis['aujourdhui']['valeur'])->toBe(1)
        ->and($kpis['retard']['valeur'])->toBe(1)
        ->and($kpis['urgentes']['valeur'])->toBe(1)
        ->and($kpis['alertes']['valeur'])->toBe(1)
        ->and($kpis['bloques']['valeur'])->toBe(1)
        ->and($kpis['non-assignees']['valeur'])->toBeGreaterThanOrEqual(5); // toutes les non assignées ouvertes
});

it('filtre la liste par onglet « urgentes »', function () {
    tache(['titre' => 'ZZ Tâche urgente', 'priorite' => TaskPriorite::Urgente->value]);
    tache(['titre' => 'ZZ Tâche normale', 'priorite' => TaskPriorite::Normale->value]);

    $data = app(TachesAlertesData::class);
    $titres = collect($data->liste(['onglet' => 'urgentes'])['items'])->pluck('titre');

    expect($titres)->toContain('ZZ Tâche urgente')
        ->and($titres)->not->toContain('ZZ Tâche normale');
});

it('regroupe les alertes automatiques par catégorie', function () {
    tache(['source' => 'auto', 'cle' => 'finance:impaye:1']);
    tache(['source' => 'auto', 'cle' => 'finance:impaye:2']);
    tache(['source' => 'auto', 'cle' => 'opco:sans_retour:1']);

    $alertes = collect(app(TachesAlertesData::class)->alertesAuto())->keyBy('label');

    expect($alertes['Facture en retard']['total'])->toBe(2)
        ->and($alertes['Dossier OPCO sans retour']['total'])->toBe(1)
        ->and($alertes->has('Qualiopi non conforme'))->toBeFalse(); // aucune → masquée
});

it('pagine la liste', function () {
    Task::factory()->count(12)->create(['source' => 'manuel']);

    $page1 = app(TachesAlertesData::class)->liste(['page' => 1, 'parPage' => 5]);

    expect($page1['total'])->toBe(12)
        ->and($page1['pages'])->toBe(3)
        ->and($page1['items'])->toHaveCount(5)
        ->and($page1['debut'])->toBe(1)
        ->and($page1['fin'])->toBe(5);
});

// ─── Page Livewire ──────────────────────────────────────────────────────

it('affiche la page « Tâches & Alertes » avec ses sections', function () {
    tache(['titre' => 'Corriger dossier OPCO rejeté']);

    Livewire::test(ListTasks::class)
        ->assertSuccessful()
        ->assertSee('Tâches & Alertes')
        ->assertSee("À traiter aujourd'hui")
        ->assertSee('Alertes système')
        ->assertSee('Dossiers bloqués')
        ->assertSee('Liste des tâches et alertes')
        ->assertSee('Vue Kanban rapide')
        ->assertSee('Alertes automatiques')
        ->assertSee('Corriger dossier OPCO rejeté');
});

it('sélectionne une tâche et affiche son détail', function () {
    $t = tache(['titre' => 'Relancer AKTO', 'description' => 'Relance facture']);

    Livewire::test(ListTasks::class)
        ->call('selectionner', $t->id)
        ->assertSet('selectedTaskId', $t->id)
        ->assertSee('Détail de la tâche')
        ->assertSee('Relance facture');
});

it('termine une tâche via l\'action rapide', function () {
    $t = tache(['statut' => TaskStatut::EnCours->value]);

    Livewire::test(ListTasks::class)->call('terminer', $t->id);

    expect($t->refresh()->statut)->toBe(TaskStatut::Terminee);
});

it('reporte l\'échéance d\'une semaine', function () {
    $t = tache(['due_date' => now()->toDateString(), 'statut' => TaskStatut::EnRetard->value]);

    Livewire::test(ListTasks::class)->call('reporter', $t->id);

    expect($t->refresh()->due_date->toDateString())->toBe(now()->addWeek()->toDateString())
        ->and($t->refresh()->statut)->toBe(TaskStatut::AFaire); // en retard → à faire
});

it('réinitialise les filtres', function () {
    Livewire::test(ListTasks::class)
        ->set('onglet', 'urgentes')
        ->set('recherche', 'test')
        ->call('reinitialiser')
        ->assertSet('onglet', 'toutes')
        ->assertSet('recherche', '');
});

it('exporte la liste en CSV', function () {
    tache(['titre' => 'Tâche exportée']);

    Livewire::test(ListTasks::class)
        ->call('exporter')
        ->assertFileDownloaded();
});

it('reste protégé par la permission access_tasks', function () {
    $sansAcces = User::factory()->create(['is_active' => true]);
    $this->actingAs($sansAcces);

    expect(TaskResource::canAccess())->toBeFalse();
});
