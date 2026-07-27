<?php

namespace App\Filament\Resources\Seances\Pages;

use App\Filament\Actions\FeuilleEmargementAction;
use App\Filament\Resources\Seances\SeanceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSeance extends EditRecord
{
    protected static string $resource = SeanceResource::class;

    /** Apprenants cochés (champ virtuel — les options diffèrent au sein d'une cohorte). */
    protected array $participantsIds = [];

    /** Rafraîchit la page (bouton d'en-tête) quand la feuille est déposée depuis la section Émargement. */
    protected function getListeners(): array
    {
        return array_merge(parent::getListeners(), ['feuille-emargement-maj' => '$refresh']);
    }

    /** Pré-coche les apprenants actuellement émargés sur la séance. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['participants_ids'] = $this->getRecord()->presences()->pluck('candidate_id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->participantsIds = $data['participants_ids'] ?? [];
        unset($data['participants_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->composerParticipants($this->participantsIds);
    }

    protected function getHeaderActions(): array
    {
        return [
            FeuilleEmargementAction::make()->record(fn () => $this->getRecord()),
            DeleteAction::make(),
        ];
    }
}
