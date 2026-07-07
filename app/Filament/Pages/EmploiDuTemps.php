<?php

namespace App\Filament\Pages;

use App\Models\Formation;
use App\Models\Seance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Emploi du temps hebdomadaire par formation : les séances de toutes les
 * classes (matières) de la formation, posées sur une grille lundi → samedi.
 * Navigation de semaine en semaine, clic sur une séance pour l'ouvrir,
 * bouton « + » pour en créer une à la date voulue.
 */
class EmploiDuTemps extends Page
{
    protected string $view = 'filament.pages.emploi-du-temps';

    protected static string|\UnitEnum|null $navigationGroup = 'Formation & Scolarité';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Emploi du temps';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $title = 'Emploi du temps';

    public ?int $formationId = null;

    /** Lundi de la semaine affichée (Y-m-d). */
    public string $semaine = '';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_attendance');
    }

    public function mount(): void
    {
        $this->semaine = CarbonImmutable::now()->startOfWeek()->toDateString();
        $this->formationId ??= Formation::query()->orderBy('libelle')->value('id');
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

    protected function getViewData(): array
    {
        $lundi = CarbonImmutable::parse($this->semaine)->startOfWeek();

        // Lundi → samedi (le dimanche n'est pas travaillé).
        $jours = collect(range(0, 5))->map(fn (int $i) => $lundi->addDays($i));

        $seances = Seance::query()
            ->with(['promotion.formation', 'formateur'])
            ->whereBetween('date', [$lundi->toDateString(), $lundi->addDays(5)->toDateString()])
            ->when($this->formationId, fn ($q) => $q
                ->whereHas('promotion', fn ($p) => $p->where('formation_id', $this->formationId)))
            ->orderBy('heure_debut')
            ->get()
            ->groupBy(fn (Seance $s) => $s->date->toDateString());

        return [
            'formations' => Formation::query()->orderBy('libelle')->pluck('libelle', 'id'),
            'jours' => $jours,
            'seancesParJour' => $seances,
            'couleurs' => $this->couleursParClasse($seances),
            'lundi' => $lundi,
            'samedi' => $lundi->addDays(5),
        ];
    }

    /** Une couleur stable par classe (matière) pour repérer les créneaux d'un coup d'œil. */
    private function couleursParClasse(Collection $seancesParJour): array
    {
        $palette = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#f97316'];

        return $seancesParJour
            ->flatten()
            ->pluck('promotion_id')
            ->unique()
            ->values()
            ->mapWithKeys(fn (int $id, int $i): array => [$id => $palette[$i % count($palette)]])
            ->all();
    }
}
