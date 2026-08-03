<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Seances\SeanceResource;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
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

    protected static ?int $navigationSort = 3;

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
     * Aperçu d'une séance en pop-up (sans quitter l'emploi du temps) : infos de
     * la séance et liste des apprenants prévus. L'émargement se fait sur la
     * fiche complète de la séance (« Ouvrir la séance »).
     */
    public function voirSeanceAction(): Action
    {
        return Action::make('voirSeance')
            ->modalHeading('Séance')
            ->modalContent(fn (array $arguments) => ($s = Seance::with(['promotion.formation', 'formateur', 'presences.candidate'])->find($arguments['seance']))
                ? view('filament.seance-apercu', ['seance' => $s])
                : null)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->extraModalFooterActions(fn (array $arguments): array => [
                Action::make('emargementDirect')
                    ->label('Émargement en direct')
                    ->icon('heroicon-o-bolt')
                    ->color('primary')
                    ->url(CockpitSeance::getUrl(['seance' => $arguments['seance']])),
                Action::make('ouvrirSeance')
                    ->label('Ouvrir la séance (feuille…)')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(SeanceResource::getUrl('edit', ['record' => $arguments['seance']])),
            ])
            ->modalWidth('2xl');
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
