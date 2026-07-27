<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Enums\TaskStatut;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use App\Support\TachesAlertes\TachesAlertesData;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Centre d'action opérationnel « Tâches & Alertes ».
 *
 * Reste la page « index » de la ressource Tasks (route et menu inchangés) mais
 * remplace la table standard par un tableau de bord orienté action : KPI,
 * filtres/onglets, liste + détail, Kanban rapide et alertes automatiques.
 * Toute l'agrégation de données vit dans [TachesAlertesData] ; ici on ne gère
 * que l'état d'interface et les actions rapides.
 */
class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    /** Vue custom en lieu et place de la table Filament standard. */
    protected string $view = 'filament.resources.tasks.pages.list-tasks';

    // ─── État d'interface ────────────────────────────────────────────────
    public string $vue = 'liste';           // liste | kanban

    public string $onglet = 'toutes';

    public string $recherche = '';

    public ?string $responsable = null;

    public ?string $priorite = null;

    public ?string $statut = null;

    public ?string $module = null;

    public ?string $echeance = null;

    public ?string $type = null;

    public ?int $selectedTaskId = null;

    public int $pageCourante = 1;

    public int $parPage = 5;

    /** La ressource fournit toujours les routes create/edit : on n'ajoute pas d'action d'en-tête Filament (l'en-tête est custom dans la vue). */
    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Tâches & Alertes';
    }

    // ─── Données (mémoïsées le temps du rendu) ───────────────────────────

    #[Computed]
    public function donnees(): array
    {
        $data = app(TachesAlertesData::class);
        $liste = $data->liste($this->etat());

        // Sélection par défaut : la première tâche de la liste courante.
        $detailId = $this->selectedTaskId ?? ($liste['items']->first()['id'] ?? null);

        return [
            'kpis' => $data->kpis(),
            'liste' => $liste,
            'kanban' => $data->kanban($this->etat()),
            'alertesAuto' => $data->alertesAuto(),
            'options' => $data->options(),
            'detail' => $data->detail($detailId),
            'detailId' => $detailId,
        ];
    }

    /** @return array<string, mixed> */
    private function etat(): array
    {
        return [
            'onglet' => $this->onglet,
            'recherche' => $this->recherche,
            'responsable' => $this->responsable,
            'priorite' => $this->priorite,
            'statut' => $this->statut,
            'module' => $this->module,
            'echeance' => $this->echeance,
            'type' => $this->type,
            'page' => $this->pageCourante,
            'parPage' => $this->parPage,
            'userId' => auth()->id(),
        ];
    }

    // ─── Interactions ────────────────────────────────────────────────────

    public function selectionner(int $id): void
    {
        $this->selectedTaskId = $id;
    }

    public function choisirOnglet(string $onglet): void
    {
        $this->onglet = $onglet;
        $this->pageCourante = 1;
        $this->selectedTaskId = null;
    }

    public function basculerVue(string $vue): void
    {
        $this->vue = in_array($vue, ['liste', 'kanban'], true) ? $vue : 'liste';
    }

    /** Applique le filtre porté par une carte KPI cliquée. */
    public function appliquerKpi(array $filtre): void
    {
        $this->reinitialiser();
        $this->onglet = $filtre['onglet'] ?? 'toutes';
        $this->echeance = $filtre['echeance'] ?? null;
        $this->statut = $filtre['statut'] ?? null;
    }

    public function reinitialiser(): void
    {
        $this->reset(['recherche', 'responsable', 'priorite', 'statut', 'module', 'echeance', 'type']);
        $this->onglet = 'toutes';
        $this->pageCourante = 1;
        $this->selectedTaskId = null;
    }

    public function allerPage(int $page): void
    {
        $this->pageCourante = max(1, $page);
        $this->selectedTaskId = null;
    }

    /** Réinitialise la pagination dès qu'un filtre change. */
    public function updated(string $name): void
    {
        if (! in_array($name, ['selectedTaskId', 'vue', 'pageCourante'], true)) {
            $this->pageCourante = 1;
            $this->selectedTaskId = null;
        }
    }

    // ─── Actions rapides sur une tâche ───────────────────────────────────

    public function demarrer(int $id): void
    {
        $this->changerStatut($id, TaskStatut::EnCours, 'Tâche démarrée');
    }

    public function terminer(int $id): void
    {
        $this->changerStatut($id, TaskStatut::Terminee, 'Tâche terminée');
    }

    /** Reporte l'échéance d'une semaine (et repasse « en retard » → « à faire »). */
    public function reporter(int $id): void
    {
        $task = Task::find($id);

        if ($task === null) {
            return;
        }

        $task->update([
            'due_date' => ($task->due_date ?? now())->copy()->addWeek(),
            'statut' => $task->statut === TaskStatut::EnRetard ? TaskStatut::AFaire->value : $task->statut->value,
        ]);

        Notification::make()->title('Échéance reportée d\'une semaine')->success()->send();
    }

    private function changerStatut(int $id, TaskStatut $statut, string $message): void
    {
        $task = Task::find($id);

        if ($task === null || in_array($task->statut, [TaskStatut::Terminee, TaskStatut::Annulee], true)) {
            return;
        }

        $task->update(['statut' => $statut->value]);

        Notification::make()->title($message)->success()->send();
    }

    // ─── Export CSV de la liste filtrée ──────────────────────────────────

    public function exporter(): StreamedResponse
    {
        $lignes = app(TachesAlertesData::class)
            ->liste(array_merge($this->etat(), ['page' => 1, 'parPage' => 100000]))['items'];

        return response()->streamDownload(function () use ($lignes): void {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF"); // BOM UTF-8 (Excel)
            fputcsv($sortie, ['Titre', 'Priorité', 'Statut', 'Module', 'Dossier lié', 'Responsable', 'Échéance', 'Origine']);

            foreach ($lignes as $l) {
                fputcsv($sortie, [
                    $l['titre'],
                    $l['priorite']->getLabel(),
                    $l['statut']->getLabel(),
                    $l['module'],
                    $l['dossierLabel'] ?? '—',
                    $l['responsable'],
                    $l['echeanceLabel'],
                    $l['source'] === 'auto' ? 'Alerte système' : 'Tâche manuelle',
                ]);
            }

            fclose($sortie);
        }, 'taches-alertes-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
