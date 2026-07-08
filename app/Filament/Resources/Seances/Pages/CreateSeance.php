<?php

namespace App\Filament\Resources\Seances\Pages;

use App\Filament\Pages\EmploiDuTemps;
use App\Filament\Resources\Seances\SeanceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSeance extends CreateRecord
{
    protected static string $resource = SeanceResource::class;

    /** Apprenants cochés (champ virtuel — les options diffèrent au sein d'une cohorte). */
    protected array $participantsIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->participantsIds = $data['participants_ids'] ?? [];
        unset($data['participants_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->composerParticipants($this->participantsIds);
    }

    /** La séance créée doit se voir immédiatement : retour à l'emploi du temps de sa classe, sur sa semaine. */
    protected function getRedirectUrl(): string
    {
        return EmploiDuTemps::getUrl([
            'promotion' => $this->record->promotion_id,
            'semaine' => $this->record->date->toDateString(),
        ]);
    }
}
