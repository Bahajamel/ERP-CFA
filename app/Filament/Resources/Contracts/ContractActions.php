<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\ContractStatut;
use App\Models\Contract;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Actions de workflow du contrat, pilotées par la machine à états
 * (colonne statut_contrat). Réutilisées table + en-tête d'édition.
 */
class ContractActions
{
    /**
     * Marquer signé. Visible dès que la structure l'autorise ; si la garde
     * métier bloque (pas de preuve de signature), l'utilisateur voit le motif.
     */
    public static function signer(): Action
    {
        return Action::make('signer')
            ->label('Marquer signé')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Confirmer la signature du contrat ? Un document contractuel ou une signature marquée « signée » est requis.')
            ->visible(fn (Contract $record) => $record->statut_contrat->canTransitionTo(ContractStatut::Signe))
            ->action(fn (Contract $record) => self::executer($record, ContractStatut::Signe));
    }

    /** Transition générique vers un état réellement atteignable. */
    public static function changerStatut(): Action
    {
        return Action::make('changerStatut')
            ->label('Faire évoluer')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Contract $record) => count($record->allowedTransitions()) > 0)
            ->schema([
                Select::make('statut')
                    ->label('Nouveau statut')
                    ->options(fn (Contract $record) => collect($record->allowedTransitions())
                        ->mapWithKeys(fn (ContractStatut $s) => [$s->value => $s->getLabel()])
                        ->all())
                    ->required(),
                Textarea::make('commentaire')
                    ->label('Commentaire (optionnel)'),
            ])
            ->action(fn (Contract $record, array $data) => self::executer(
                $record,
                ContractStatut::from($data['statut']),
                $data['commentaire'] ?? null,
            ));
    }

    private static function executer(Contract $record, ContractStatut $cible, ?string $comment = null): void
    {
        try {
            $record->transitionTo($cible, $comment);

            Notification::make()
                ->title('Statut du contrat : '.$cible->getLabel())
                ->success()
                ->send();
        } catch (InvalidTransitionException $e) {
            Notification::make()
                ->title('Transition refusée')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
