<?php

namespace App\Filament\Resources\Needs\Pages;

use App\Enums\NeedStatut;
use App\Filament\Resources\Needs\NeedResource;
use App\Models\Need;
use App\StateMachine\InvalidTransitionException;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class NeedsKanban extends Page
{
    protected static string $resource = NeedResource::class;

    protected string $view = 'filament.resources.needs.pages.needs-kanban';

    protected static ?string $title = 'Pipeline besoins';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $navigationLabel = 'Pipeline (Kanban)';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('liste')
                ->label('Vue liste')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(NeedResource::getUrl('index')),
        ];
    }

    /**
     * Colonnes du board (entonnoir) : un statut + les besoins correspondants.
     *
     * @return array<int, array{statut: NeedStatut, needs: Collection}>
     */
    public function getColumns(): array
    {
        $grouped = Need::query()
            ->with(['company', 'formation'])
            ->withCount('matchings')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn (Need $need): string => $need->statut->value);

        return array_map(
            fn (NeedStatut $statut): array => [
                'statut' => $statut,
                'needs' => $grouped->get($statut->value, collect()),
            ],
            NeedStatut::board(),
        );
    }

    /**
     * Déplacement d'une carte = transition d'état, validée par la machine à états.
     */
    public function moveCard(int $needId, string $toStatut): void
    {
        $need = Need::find($needId);

        if (! $need) {
            return;
        }

        $target = NeedStatut::from($toStatut);

        if ($need->statut === $target) {
            return;
        }

        try {
            $need->transitionTo($target);

            Notification::make()
                ->success()
                ->title('Statut mis à jour')
                ->body("{$need->intitule_poste} → {$target->getLabel()}")
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
