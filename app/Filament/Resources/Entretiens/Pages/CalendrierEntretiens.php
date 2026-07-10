<?php

namespace App\Filament\Resources\Entretiens\Pages;

use App\Filament\Resources\Entretiens\EntretienResource;
use App\Models\Entretien;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Vue calendrier des entretiens : grille hebdomadaire lundi → samedi,
 * navigation de semaine en semaine, clic sur un créneau pour ouvrir
 * l'entretien, bouton « + » pour en planifier un à la date voulue.
 */
class CalendrierEntretiens extends Page
{
    protected static string $resource = EntretienResource::class;

    protected string $view = 'filament.resources.entretiens.pages.calendrier-entretiens';

    protected static ?string $title = 'Calendrier des entretiens';

    /** Lundi de la semaine affichée (Y-m-d). */
    public string $semaine = '';

    public function mount(): void
    {
        $this->semaine = CarbonImmutable::now()->startOfWeek()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('liste')
                ->label('Vue liste')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(EntretienResource::getUrl('index')),
        ];
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

        $entretiens = Entretien::query()
            ->with(['candidate', 'responsable'])
            ->whereBetween('date_entretien', [$lundi->toDateString(), $lundi->addDays(5)->toDateString()])
            ->orderBy('heure_debut')
            ->get()
            ->groupBy(fn (Entretien $e) => $e->date_entretien->toDateString());

        return [
            'jours' => $jours,
            'entretiensParJour' => $entretiens,
            'lundi' => $lundi,
            'samedi' => $lundi->addDays(5),
        ];
    }
}
