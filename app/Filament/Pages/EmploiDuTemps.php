<?php

namespace App\Filament\Pages;

use App\Enums\PresenceStatut;
use App\Filament\Resources\Seances\SeanceResource;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Emploi du temps hebdomadaire d'une classe (cohorte formation + année) : ses
 * séances multi-matières posées sur une grille lundi → samedi. Navigation de
 * semaine en semaine, clic sur une séance pour l'ouvrir, bouton « + » pour en
 * créer une à la date voulue.
 */
class EmploiDuTemps extends Page
{
    protected string $view = 'filament.pages.emploi-du-temps';

    protected static string|\UnitEnum|null $navigationGroup = 'Formation & Scolarité';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Emploi du temps';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $title = 'Emploi du temps';

    /** Classe (cohorte) affichée. */
    public ?int $promotionId = null;

    /** Lundi de la semaine affichée (Y-m-d). */
    public string $semaine = '';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_attendance');
    }

    public function mount(): void
    {
        // Positionnables par l'URL (ex. retour après création d'une séance).
        $this->semaine = ($s = request('semaine')) ? CarbonImmutable::parse($s)->startOfWeek()->toDateString()
            : CarbonImmutable::now()->startOfWeek()->toDateString();
        $this->promotionId ??= ((int) request('promotion')) ?: (array_key_first($this->classes()) ?: null);
    }

    /** Les classes (cohortes) sélectionnables, libellées avec formation + année + année scolaire. */
    public function classes(): array
    {
        return Promotion::query()
            ->with('formation')
            ->whereNotNull('formation_id')
            ->get()
            ->sortBy([
                fn (Promotion $a, Promotion $b): int => strcmp($a->formation?->libelle ?? '', $b->formation?->libelle ?? ''),
                fn (Promotion $a, Promotion $b): int => strcmp($a->libelle ?? '', $b->libelle ?? ''),
            ])
            ->mapWithKeys(fn (Promotion $p): array => [$p->id => $p->nom_complet])
            ->all();
    }

    public function semainePrecedente(): void
    {
        $this->semaine = CarbonImmutable::parse($this->semaine)->subWeek()->toDateString();
    }

    public function semaineSuivante(): void
    {
        $this->semaine = CarbonImmutable::parse($this->semaine)->addWeek()->toDateString();
    }

    public function semaineCourante(): void
    {
        $this->semaine = CarbonImmutable::now()->startOfWeek()->toDateString();
    }

    /** Lien de création d'une séance pré-remplie (classe + date). */
    public function lienCreation(string $date): string
    {
        return SeanceResource::getUrl('create', [
            'promotion' => $this->promotionId,
            'date' => $date,
        ]);
    }

    /**
     * Aperçu + émargement d'une séance en pop-up (sans quitter l'emploi du
     * temps) : infos de la séance, statut de présence de chaque apprenant
     * modifiable, dépôt de la feuille et accès à la fiche complète.
     */
    public function voirSeanceAction(): Action
    {
        return Action::make('voirSeance')
            ->modalHeading('Séance')
            ->modalContent(fn (array $arguments) => ($s = Seance::with(['promotion.formation', 'formateur', 'presences'])->find($arguments['seance']))
                ? view('filament.seance-apercu', ['seance' => $s])
                : null)
            ->schema(fn (array $arguments): array => static::schemaEmargement($arguments['seance']))
            ->action(function (array $data, array $arguments): void {
                $seance = Seance::with('presences')->find($arguments['seance']);

                foreach ($seance?->presences ?? [] as $presence) {
                    if (isset($data['statut_'.$presence->id])) {
                        $presence->update(['statut' => $data['statut_'.$presence->id]]);
                    }
                }

                Notification::make()->success()->title('Émargement enregistré')->send();
            })
            ->modalSubmitActionLabel('Enregistrer l\'émargement')
            ->modalCancelActionLabel('Fermer')
            ->extraModalFooterActions(fn (array $arguments): array => [
                Action::make('ouvrirSeance')
                    ->label('Ouvrir la séance (feuille, participants…)')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(SeanceResource::getUrl('edit', ['record' => $arguments['seance']])),
            ])
            ->modalWidth('4xl');
    }

    /**
     * Émargement en pop-up, pensé pour les grands effectifs : bouton « Tout
     * présent » (on n'ajuste ensuite que les exceptions) + liste des apprenants
     * dans une zone à hauteur plafonnée qui défile (le pop-up ne s'allonge pas).
     */
    protected static function schemaEmargement(int $seanceId): array
    {
        $seance = Seance::with('presences.candidate')->find($seanceId);

        if ($seance === null || $seance->presences->isEmpty()) {
            return [
                Section::make('Émargement')
                    ->schema([])
                    ->description('Aucun apprenant sur cette séance — utilisez « Gérer les participants » sur la fiche de la séance.'),
            ];
        }

        $options = collect(PresenceStatut::cases())
            ->mapWithKeys(fn (PresenceStatut $s): array => [$s->value => $s->getLabel()])
            ->all();

        $presences = $seance->presences->sortBy(fn ($p) => $p->candidate?->nom_complet)->values();
        $champsNoms = $presences->map(fn ($p) => 'statut_'.$p->id)->all();

        $selecteurs = $presences
            ->map(fn ($p) => Select::make('statut_'.$p->id)
                ->label($p->candidate?->nom_complet ?? 'Apprenant')
                ->options($options)
                ->default($p->statut?->value ?? PresenceStatut::NonRenseigne->value)
                ->selectablePlaceholder(false)
                ->native(false))
            ->all();

        $nombre = $presences->count();

        return [
            Section::make("Émargement — {$nombre} apprenant".($nombre > 1 ? 's' : ''))
                ->description('Astuce : « Tout présent » puis n\'ajustez que les absences.')
                ->schema([
                    SchemaActions::make([
                        Action::make('toutPresent')
                            ->label('Tout présent')
                            ->icon('heroicon-m-check-circle')
                            ->color('success')
                            ->action(fn (Set $set) => collect($champsNoms)
                                ->each(fn (string $nom) => $set($nom, PresenceStatut::Present->value))),
                    ]),
                    // Liste des apprenants dans une zone défilante à hauteur
                    // plafonnée : reste utilisable quand les effectifs augmentent
                    // (le pop-up ne s'agrandit jamais).
                    Grid::make(['default' => 1, 'md' => 2])
                        ->extraAttributes([
                            'style' => 'max-height: 45vh; overflow-y: auto; gap: .75rem;'
                                .' padding: .75rem; border: 1px solid rgb(226 232 240);'
                                .' border-radius: .5rem; background: rgb(248 250 252);',
                            'class' => 'dark:!bg-white/5 dark:!border-white/10',
                        ])
                        ->schema($selecteurs),
                ]),
        ];
    }

    protected function getViewData(): array
    {
        $lundi = CarbonImmutable::parse($this->semaine)->startOfWeek();

        // Lundi → samedi (le dimanche n'est pas travaillé).
        $jours = collect(range(0, 5))->map(fn (int $i) => $lundi->addDays($i));

        $seances = Seance::query()
            ->with(['promotion.formation', 'formateur'])
            ->whereBetween('date', [$lundi->toDateString(), $lundi->addDays(5)->toDateString()])
            ->when($this->promotionId, fn ($q) => $q->where('promotion_id', $this->promotionId))
            ->orderBy('heure_debut')
            ->get()
            ->groupBy(fn (Seance $s) => $s->date->toDateString());

        return [
            'classes' => $this->classes(),
            'jours' => $jours,
            'seancesParJour' => $seances,
            'couleurs' => $this->couleursParMatiere($seances),
            'lundi' => $lundi,
            'samedi' => $lundi->addDays(5),
        ];
    }

    /** Une couleur stable par matière pour repérer les créneaux d'un coup d'œil. */
    private function couleursParMatiere(Collection $seancesParJour): array
    {
        $palette = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#f97316'];

        return $seancesParJour
            ->flatten()
            ->pluck('libelle')
            ->filter()
            ->unique()
            ->values()
            ->mapWithKeys(fn (string $matiere, int $i): array => [$matiere => $palette[$i % count($palette)]])
            ->all();
    }
}
