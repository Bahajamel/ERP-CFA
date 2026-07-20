<?php

namespace App\Filament\Resources\CustomTables\Pages;

use App\Enums\CustomFieldType;
use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Models\CustomFieldDefinition;
use App\Models\CustomRecord;
use App\Support\CustomFields;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Vue Kanban d'un tableau personnalisé : les lignes sont réparties en colonnes
 * selon les valeurs d'une colonne « Statut » (ou « Liste »). On peut déplacer une
 * carte d'une colonne à l'autre (met à jour la valeur de statut de la ligne).
 */
class KanbanCustomTable extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CustomTableResource::class;

    protected string $view = 'filament.resources.custom-tables.pages.kanban';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(Auth::user()?->can('view', $this->record) ?? false, 403);
        abort_unless($this->colonneStatut() !== null, 404);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name.' — Kanban';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('board')
                ->label('Vue tableau')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(fn (): string => CustomTableResource::getUrl('board', ['record' => $this->getRecord()])),
        ];
    }

    /** Colonne de regroupement : première « Statut », sinon première « Liste ». */
    public function colonneStatut(): ?CustomFieldDefinition
    {
        $colonnes = $this->getRecord()->colonnes;

        return $colonnes->first(fn (CustomFieldDefinition $c): bool => $c->type === CustomFieldType::Statut)
            ?? $colonnes->first(fn (CustomFieldDefinition $c): bool => $c->type === CustomFieldType::Select);
    }

    /**
     * Colonnes du board Kanban : une par option de statut (+ « Sans statut » pour
     * les lignes sans valeur), chacune avec ses cartes.
     *
     * @return array<int, array{valeur: string, couleur: string, lignes: Collection}>
     */
    public function getColonnesKanban(): array
    {
        $statut = $this->colonneStatut();
        $options = collect($statut->config['options'] ?? [])->map(fn ($o): string => trim((string) $o))->filter()->values();

        $lignes = CustomRecord::query()
            ->where('custom_table_id', $this->getRecord()->getKey())
            ->orderByDesc('created_at')
            ->get();

        $colonnes = [];
        foreach ($options as $i => $option) {
            // Couleur choisie pour l'option (convertie en hex), sinon palette auto.
            $nomCouleur = $statut->config['colors'][$option] ?? null;
            $couleur = $nomCouleur !== null
                ? (CustomFields::COULEURS_HEX[$nomCouleur] ?? $this->couleur($i))
                : $this->couleur($i);

            $colonnes[] = [
                'valeur' => $option,
                'couleur' => $couleur,
                'lignes' => $lignes->filter(fn (CustomRecord $r): bool => data_get($r->data, $statut->key) === $option)->values(),
            ];
        }

        $sansStatut = $lignes->filter(fn (CustomRecord $r): bool => ! $options->contains(data_get($r->data, $statut->key)))->values();
        if ($sansStatut->isNotEmpty()) {
            $colonnes[] = ['valeur' => '', 'couleur' => '#94a3b8', 'lignes' => $sansStatut];
        }

        return $colonnes;
    }

    /** Colonnes affichées sur une carte (hors statut), pour un aperçu synthétique. */
    public function colonnesCarte(): Collection
    {
        $statut = $this->colonneStatut();

        return $this->getRecord()->colonnes
            ->reject(fn (CustomFieldDefinition $c): bool => $c->getKey() === $statut->getKey())
            ->take(3)
            ->values();
    }

    /**
     * Déplacement d'une carte = mise à jour de la valeur de statut de la ligne.
     * Appelé par le glisser-déposer (Alpine x-sort).
     */
    public function moveCard(int $recordId, string $versStatut): void
    {
        if (! (Auth::user()?->can('custom_records.update') ?? false)) {
            return;
        }

        $ligne = CustomRecord::query()
            ->where('custom_table_id', $this->getRecord()->getKey())
            ->find($recordId);

        if ($ligne === null) {
            return;
        }

        $statut = $this->colonneStatut();
        $data = $ligne->data ?? [];
        $data[$statut->key] = $versStatut === '' ? null : $versStatut;
        $ligne->update(['data' => $data]);

        Notification::make()->success()->title('Statut mis à jour')->send();
    }

    private function couleur(int $index): string
    {
        return ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#6366f1', '#14b8a6'][$index % 6];
    }
}
