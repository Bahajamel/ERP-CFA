<?php

namespace App\Filament\Resources\OpcoFiles\Pages;

use App\Enums\OpcoStatut;
use App\Filament\Resources\OpcoFiles\OpcoFileActions;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use App\Models\OpcoFile;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOpcoFile extends EditRecord
{
    protected static string $resource = OpcoFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OpcoFileActions::preparerDepot(),
            OpcoFileActions::accepter(),
            OpcoFileActions::rejeter(),
            OpcoFileActions::genererEcheancier(),
            DeleteAction::make(),
        ];
    }

    /**
     * Le statut du dossier est modifiable depuis le formulaire, mais son évolution
     * passe par la machine à états : on sauvegarde d'abord les autres champs, puis
     * on applique la transition (gardes métier + effets). Une transition refusée
     * n'altère pas le reste.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $nouveauStatut = $data['statut'] ?? null;
        unset($data['statut']);

        /** @var OpcoFile $record */
        $record = parent::handleRecordUpdate($record, $data);

        if ($nouveauStatut !== null && $record->statut->value !== $nouveauStatut) {
            try {
                $record->transitionTo(OpcoStatut::from($nouveauStatut));

                Notification::make()
                    ->success()
                    ->title('Dossier OPCO : '.$record->statut->getLabel())
                    ->send();
            } catch (InvalidTransitionException $e) {
                Notification::make()
                    ->danger()
                    ->title('Changement de statut refusé')
                    ->body($e->getMessage())
                    ->send();
            }
        }

        return $record;
    }
}
