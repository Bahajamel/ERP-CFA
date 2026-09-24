<?php

namespace App\Support\TachesAlertes;

use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use App\Filament\Resources\QualiopiIndicators\QualiopiIndicatorResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use App\Models\QualiopiIndicator;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Assemble toutes les données de la page « Tâches & Alertes » à partir des
 * vraies données (aucune donnée mockée) : KPI, liste filtrée/paginée, colonnes
 * Kanban et catégories d'alertes automatiques.
 *
 * L'UI (page Livewire + Blade) ne fait que consommer ces méthodes : la logique
 * de sélection/agrégation reste ici, testable et rebranchable indépendamment.
 */
class TachesAlertesData
{
    /** Statuts « ouverts » (une tâche encore à traiter). */
    private const OUVERTS = [
        TaskStatut::AFaire->value,
        TaskStatut::EnCours->value,
        TaskStatut::EnAttente->value,
        TaskStatut::EnRetard->value,
    ];

    // ─── KPI ────────────────────────────────────────────────────────────

    /**
     * Les 6 cartes KPI, chacune cliquable (le champ `filtre` est appliqué au clic).
     *
     * @return array<int, array<string, mixed>>
     */
    public function kpis(): array
    {
        $ouvert = fn (Builder $q): Builder => $q->whereIn('statut', self::OUVERTS);

        return [
            [
                'cle' => 'aujourdhui',
                'label' => "À traiter aujourd'hui",
                'valeur' => Task::query()->tap($ouvert)->whereDate('due_date', now()->toDateString())->count(),
                'couleur' => 'bleu',
                'icone' => 'heroicon-o-calendar-days',
                'filtre' => ['echeance' => 'aujourdhui'],
            ],
            [
                'cle' => 'retard',
                'label' => 'En retard',
                'valeur' => $this->baseEnRetard()->count(),
                'couleur' => 'orange',
                'icone' => 'heroicon-o-clock',
                'filtre' => ['onglet' => 'en-retard'],
            ],
            [
                'cle' => 'urgentes',
                'label' => 'Urgentes',
                'valeur' => Task::query()->tap($ouvert)->where('priorite', TaskPriorite::Urgente->value)->count(),
                'couleur' => 'rouge',
                'icone' => 'heroicon-o-exclamation-triangle',
                'filtre' => ['onglet' => 'urgentes'],
            ],
            [
                'cle' => 'alertes',
                'label' => 'Alertes système',
                'valeur' => Task::query()->tap($ouvert)->where('source', 'auto')->count(),
                'couleur' => 'violet',
                'icone' => 'heroicon-o-bell-alert',
                'filtre' => ['onglet' => 'alertes-systeme'],
            ],
            [
                'cle' => 'non-assignees',
                'label' => 'Non assignées',
                'valeur' => Task::query()->tap($ouvert)->whereNull('assignee_id')->count(),
                'couleur' => 'gris',
                'icone' => 'heroicon-o-user',
                'filtre' => ['onglet' => 'non-assignees'],
            ],
            [
                // « Bloqué » de la maquette = statut « En attente » de l'ERP (choix validé).
                'cle' => 'bloques',
                'label' => 'Dossiers bloqués',
                'valeur' => Task::query()->where('statut', TaskStatut::EnAttente->value)->count(),
                'couleur' => 'ambre',
                'icone' => 'heroicon-o-lock-closed',
                'filtre' => ['statut' => TaskStatut::EnAttente->value],
            ],
        ];
    }

    // ─── Liste filtrée + paginée ─────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $etat  onglet, recherche, responsable, priorite, statut, module, echeance, type, page, parPage
     * @return array{items: Collection, total: int, page: int, parPage: int, pages: int, debut: int, fin: int}
     */
    public function liste(array $etat): array
    {
        $parPage = max(1, (int) ($etat['parPage'] ?? 5));
        $page = max(1, (int) ($etat['page'] ?? 1));

        $query = $this->requeteFiltree($etat);
        $total = (clone $query)->count();
        $pages = (int) max(1, ceil($total / $parPage));
        $page = min($page, $pages);

        $items = $query
            ->orderByRaw($this->ordrePriorite())
            ->orderByRaw('due_date is null, due_date asc')
            ->forPage($page, $parPage)
            ->get()
            ->map(fn (Task $t) => $this->ligne($t));

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'parPage' => $parPage,
            'pages' => $pages,
            'debut' => $total === 0 ? 0 : ($page - 1) * $parPage + 1,
            'fin' => min($page * $parPage, $total),
        ];
    }

    /** Construit la requête en appliquant l'onglet actif + tous les filtres. */
    private function requeteFiltree(array $etat): Builder
    {
        $query = Task::query()->with(['assignee', 'taskable']);

        // Onglets rapides.
        match ($etat['onglet'] ?? 'toutes') {
            'mes-taches' => $query->where('assignee_id', $etat['userId'] ?? null),
            'urgentes' => $query->whereIn('statut', self::OUVERTS)->where('priorite', TaskPriorite::Urgente->value),
            'en-retard' => $this->appliquerEnRetard($query),
            'alertes-systeme' => $query->where('source', 'auto'),
            'non-assignees' => $query->whereIn('statut', self::OUVERTS)->whereNull('assignee_id'),
            default => $query,
        };

        // Filtres détaillés.
        if ($recherche = trim((string) ($etat['recherche'] ?? ''))) {
            $query->where(fn (Builder $q) => $q
                ->where('titre', 'like', "%{$recherche}%")
                ->orWhere('description', 'like', "%{$recherche}%"));
        }

        $query
            ->when($etat['responsable'] ?? null, fn (Builder $q, $v) => $q->where('assignee_id', $v))
            ->when($etat['priorite'] ?? null, fn (Builder $q, $v) => $q->where('priorite', $v))
            ->when($etat['statut'] ?? null, fn (Builder $q, $v) => $q->where('statut', $v))
            ->when($etat['module'] ?? null, fn (Builder $q, $v) => $q->where('taskable_type', $v))
            ->when($etat['type'] ?? null, fn (Builder $q, $v) => $q->where('source', $v));

        match ($etat['echeance'] ?? null) {
            'aujourdhui' => $query->whereDate('due_date', now()->toDateString()),
            'semaine' => $query->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()]),
            'retard' => $this->appliquerEnRetard($query),
            default => null,
        };

        return $query;
    }

    // ─── Kanban rapide ───────────────────────────────────────────────────

    /**
     * Répartition par statut (hors filtre de statut/onglet), avec un échantillon
     * de tâches par colonne.
     *
     * @return array<int, array{statut: TaskStatut, total: int, apercu: Collection}>
     */
    public function kanban(array $etat): array
    {
        // On ignore l'onglet et le filtre statut pour montrer la vraie répartition.
        $base = $this->requeteFiltree(array_merge($etat, ['onglet' => 'toutes', 'statut' => null]));

        $colonnes = [
            TaskStatut::AFaire,
            TaskStatut::EnCours,
            TaskStatut::EnAttente,
            TaskStatut::EnRetard,
            TaskStatut::Terminee,
        ];

        return array_map(function (TaskStatut $statut) use ($base) {
            $q = (clone $base)->where('statut', $statut->value);

            return [
                'statut' => $statut,
                'total' => (clone $q)->count(),
                'apercu' => $q->orderByRaw($this->ordrePriorite())->limit(3)->get()->map(fn (Task $t) => $this->ligne($t)),
            ];
        }, $colonnes);
    }

    // ─── Alertes automatiques (regroupées par catégorie) ─────────────────

    /**
     * Catégories d'alertes système (tâches source=auto), déduites du préfixe de
     * la clé d'idempotence. Seules les catégories réellement présentes sont listées.
     *
     * @return array<int, array{cle: string, label: string, icone: string, total: int}>
     */
    public function alertesAuto(): array
    {
        $categories = [
            ['prefixe' => 'contrat:', 'label' => 'Contrat à signer', 'icone' => 'heroicon-o-document-text'],
            ['prefixe' => 'finance:', 'label' => 'Facture en retard', 'icone' => 'heroicon-o-banknotes'],
            ['prefixe' => 'opco:sans_retour:', 'label' => 'Dossier OPCO sans retour', 'icone' => 'heroicon-o-arrow-path'],
            ['prefixe' => 'opco:echeance:', 'label' => 'Versement OPCO à venir', 'icone' => 'heroicon-o-calendar'],
            ['prefixe' => 'opco:retard:', 'label' => 'Versement OPCO en retard', 'icone' => 'heroicon-o-exclamation-triangle'],
            ['prefixe' => 'qualiopi:', 'label' => 'Qualiopi non conforme', 'icone' => 'heroicon-o-shield-exclamation'],
            // TODO(métier) : « Absence injustifiée » et « Document obligatoire manquant »
            //  ne sont pas encore générées par AlerteService — à ajouter côté service.
        ];

        return collect($categories)
            ->map(fn (array $c): array => [
                'cle' => $c['prefixe'],
                'label' => $c['label'],
                'icone' => $c['icone'],
                'total' => Task::query()
                    ->where('source', 'auto')
                    ->whereIn('statut', self::OUVERTS)
                    ->where('cle', 'like', $c['prefixe'].'%')
                    ->count(),
            ])
            ->filter(fn (array $c): bool => $c['total'] > 0)
            ->values()
            ->all();
    }

    // ─── Options de filtres ──────────────────────────────────────────────

    /** @return array{responsables: array<int, string>, modules: array<string, string>} */
    public function options(): array
    {
        $responsables = User::query()
            ->whereIn('id', Task::query()->whereNotNull('assignee_id')->distinct()->pluck('assignee_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        $modules = Task::query()
            ->whereNotNull('taskable_type')
            ->distinct()
            ->pluck('taskable_type')
            ->mapWithKeys(fn (string $type): array => [$type => $this->moduleLabel($type)])
            ->all();

        return ['responsables' => $responsables, 'modules' => $modules];
    }

    // ─── Détail d'une tâche ──────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    public function detail(?int $taskId): ?array
    {
        if ($taskId === null) {
            return null;
        }

        $task = Task::query()->with(['assignee', 'createdBy', 'taskable'])->find($taskId);

        if ($task === null) {
            return null;
        }

        $ligne = $this->ligne($task);

        // Historique reconstruit à partir des données réelles disponibles.
        $historique = collect([
            $task->created_at ? [
                'date' => $task->created_at,
                'texte' => 'Tâche créée'.($task->createdBy ? ' par '.$task->createdBy->name : ($task->source === 'auto' ? ' automatiquement par le système' : '')),
                'type' => 'creation',
            ] : null,
            $task->updated_at && $task->updated_at->ne($task->created_at) ? [
                'date' => $task->updated_at,
                'texte' => 'Dernière mise à jour — statut « '.$task->statut->getLabel().' »',
                'type' => 'maj',
            ] : null,
        ])->filter()->values()->all();

        return array_merge($ligne, [
            'description' => $task->description,
            'historique' => $historique,
            'reassignerUrl' => $this->url(TaskResource::class, 'edit', $task),
        ]);
    }

    // ─── Mapping d'une tâche vers une ligne d'affichage ──────────────────

    /** @return array<string, mixed> */
    public function ligne(Task $task): array
    {
        $enRetard = $task->due_date
            && $task->due_date->isPast()
            && ! in_array($task->statut, [TaskStatut::Terminee, TaskStatut::Annulee], true);

        [$module, $dossierUrl] = $this->moduleEtUrl($task->taskable);

        return [
            'id' => $task->id,
            'titre' => $task->titre,
            'priorite' => $task->priorite,
            'statut' => $task->statut,
            'source' => $task->source,
            'module' => $module,
            'dossierLabel' => $this->dossierLabel($task->taskable),
            'dossierUrl' => $dossierUrl,
            'responsable' => $task->assignee?->name ?? 'Non assignée',
            'echeance' => $task->due_date,
            'echeanceLabel' => $this->echeanceLabel($task->due_date),
            'echeanceRouge' => $enRetard,
            'peutDemarrer' => $task->statut === TaskStatut::AFaire,
            'peutTerminer' => ! in_array($task->statut, [TaskStatut::Terminee, TaskStatut::Annulee], true),
        ];
    }

    // ─── Helpers privés ──────────────────────────────────────────────────

    private function baseEnRetard(): Builder
    {
        return $this->appliquerEnRetard(Task::query());
    }

    private function appliquerEnRetard(Builder $query): Builder
    {
        return $query
            ->whereIn('statut', self::OUVERTS)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    /** Tri par priorité décroissante (urgente → basse). */
    private function ordrePriorite(): string
    {
        return "case priorite
            when '".TaskPriorite::Urgente->value."' then 0
            when '".TaskPriorite::Haute->value."' then 1
            when '".TaskPriorite::Normale->value."' then 2
            else 3 end";
    }

    private function echeanceLabel(?Carbon $date): string
    {
        if ($date === null) {
            return '—';
        }

        return $date->isToday() ? "Aujourd'hui" : $date->format('d/m');
    }

    /** @return array{0: string, 1: ?string} [libellé module, url du dossier lié] */
    private function moduleEtUrl(?Model $taskable): array
    {
        return match (true) {
            $taskable instanceof Contract => ['Contrats', $this->url(ContractResource::class, 'edit', $taskable)],
            $taskable instanceof OpcoFile => ['OPCO', $this->url(OpcoFileResource::class, 'edit', $taskable)],
            $taskable instanceof OpcoPayment => ['OPCO', $taskable->opcoFile ? $this->url(OpcoFileResource::class, 'edit', $taskable->opcoFile) : null],
            $taskable instanceof Invoice => ['Finance', $this->urlRoute('filament.admin.pages.finance')],
            $taskable instanceof QualiopiIndicator => ['Qualité', $this->url(QualiopiIndicatorResource::class, 'index')],
            $taskable instanceof Candidate => ['Candidats', $this->url(CandidateResource::class, 'edit', $taskable)],
            $taskable instanceof Company => ['Entreprises', $this->url(CompanyResource::class, 'edit', $taskable)],
            default => ['—', null],
        };
    }

    private function moduleLabel(string $type): string
    {
        $instance = class_exists($type) ? new $type : null;

        return $this->moduleEtUrl($instance)[0];
    }

    private function dossierLabel(?Model $taskable): ?string
    {
        if ($taskable === null) {
            return null;
        }

        try {
            return $taskable->nom_complet
                ?? $taskable->numero
                ?? $taskable->reference
                ?? $taskable->libelle
                ?? class_basename($taskable).' #'.$taskable->getKey();
        } catch (\Throwable) {
            return class_basename($taskable).' #'.$taskable->getKey();
        }
    }

    /** URL de ressource Filament, tolérante aux erreurs (retourne null si non routable). */
    private function url(string $resource, string $page, ?Model $record = null): ?string
    {
        try {
            return $resource::getUrl($page, $record ? ['record' => $record] : []);
        } catch (\Throwable) {
            return null;
        }
    }

    private function urlRoute(string $name): ?string
    {
        try {
            return route($name);
        } catch (\Throwable) {
            return null;
        }
    }
}
