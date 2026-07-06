<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\ContractStatut;
use App\Filament\Resources\Contracts\ContractActions;
use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContractActions::signer(),
            ContractActions::envoyerSignature(),
            ContractActions::simulerSignature(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Le statut du contrat est modifiable depuis le formulaire, mais son évolution
     * passe par la machine à états : on sauvegarde d'abord les autres champs, puis
     * on applique la transition (gardes métier + effets, ex. ouverture du dossier
     * OPCO à la signature). Une transition refusée n'altère pas le reste.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $nouveauStatut = $data['statut_contrat'] ?? null;
        unset($data['statut_contrat']);

        /** @var Contract $record */
        $record = parent::handleRecordUpdate($record, $data);

        if ($nouveauStatut !== null && $record->statut_contrat->value !== $nouveauStatut) {
            try {
                $record->transitionTo(ContractStatut::from($nouveauStatut));

                Notification::make()
                    ->success()
                    ->title('Statut du contrat : '.$record->statut_contrat->getLabel())
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
