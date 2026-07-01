<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Enums\CandidateStatut;
use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use App\StateMachine\InvalidTransitionException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class CandidatesKanban extends Page
{
    protected static string $resource = CandidateResource::class;

    protected string $view = 'filament.resources.candidates.pages.candidates-kanban';

    protected static ?string $title = 'Pipeline candidats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $navigationLabel = 'Pipeline (Kanban)';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('liste')
                ->label('Vue liste')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(CandidateResource::getUrl('index')),
        ];
    }

    /**
     * Colonnes du board : un statut + les candidats correspondants.
     *
     * @return array<int, array{statut: CandidateStatut, candidates: Collection}>
     */
    public function getColumns(): array
    {
        $grouped = Candidate::query()
            ->with(['formationVisee', 'commercial'])
            ->orderBy('nom')
            ->get()
            ->groupBy(fn (Candidate $candidate): string => $candidate->statut->value);

        return array_map(
            fn (CandidateStatut $statut): array => [
                'statut' => $statut,
                'candidates' => $grouped->get($statut->value, collect()),
            ],
            CandidateStatut::board(),
        );
    }

    /**
     * Déplacement d'une carte = transition d'état, validée par la machine à états.
     * Appelé par le drag & drop (Alpine x-sort).
     */
    public function moveCard(int $candidateId, string $toStatut): void
    {
        $candidate = Candidate::find($candidateId);

        if (! $candidate) {
            return;
        }

        $target = CandidateStatut::from($toStatut);

        // Réordonnancement dans la même colonne : rien à faire.
        if ($candidate->statut === $target) {
            return;
        }

        try {
            $candidate->transitionTo($target);

            Notification::make()
                ->success()
                ->title('Statut mis à jour')
                ->body("{$candidate->nom_complet} → {$target->getLabel()}")
                ->send();
        } catch (InvalidTransitionException $e) {
            Notification::make()
                ->danger()
                ->title('Déplacement refusé')
                ->body($e->getMessage())
                ->send();
        }
    }
}
