<?php

namespace App\Filament\Resources\OpcoFiles;

use App\Enums\OpcoStatut;
use App\Models\OpcoFile;
use App\Models\User;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Actions de workflow du dossier OPCO, pilotées par la machine à états.
 */
class OpcoFileActions
{
    /** Marquer prêt au dépôt (garde : contrat signé). */
    public static function preparerDepot(): Action
    {
        return Action::make('preparerDepot')
            ->label('Prêt au dépôt')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('info')
            ->requiresConfirmation()
            ->visible(fn (OpcoFile $record) => $record->statut->canTransitionTo(OpcoStatut::PretDepot))
            ->action(fn (OpcoFile $record) => self::executer($record, OpcoStatut::PretDepot));
    }

    /** Enregistrer l'acceptation OPCO avec le montant accepté. */
    public static function accepter(): Action
    {
        return Action::make('accepter')
            ->label('Enregistrer l\'acceptation')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (OpcoFile $record) => $record->statut->canTransitionTo(OpcoStatut::Accepte))
            ->schema([
                TextInput::make('montant_accepte')
                    ->label('Montant accepté (€)')
                    ->numeric()
                    ->prefix('€')
                    ->required(),
            ])
            ->action(function (OpcoFile $record, array $data) {
                $record->forceFill(['montant_accepte' => $data['montant_accepte']])->save();
                self::executer($record, OpcoStatut::Accepte);
            });
    }

    /** Enregistrer un rejet (motif obligatoire) → crée une action corrective. */
    public static function rejeter(): Action
    {
        return Action::make('rejeter')
            ->label('Enregistrer un rejet')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (OpcoFile $record) => $record->statut->canTransitionTo(OpcoStatut::Rejete))
            ->schema([
                Textarea::make('motif_rejet')
                    ->label('Motif du rejet')
                    ->required(),
                Select::make('responsable_correction_id')
                    ->label('Responsable de la correction')
                    ->options(fn () => User::query()->pluck('name', 'id')->all())
                    ->searchable(),
            ])
            ->action(function (OpcoFile $record, array $data) {
                $record->forceFill([
                    'motif_rejet' => $data['motif_rejet'],
                    'responsable_correction_id' => $data['responsable_correction_id'] ?? null,
                ])->save();

                self::executer($record, OpcoStatut::Rejete);
            });
    }

    /** Transition générique vers un état réellement atteignable. */
    public static function changerStatut(): Action
    {
        return Action::make('changerStatut')
            ->label('Faire évoluer')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (OpcoFile $record) => count($record->allowedTransitions()) > 0)
            ->schema([
                Select::make('statut')
                    ->label('Nouveau statut')
                    ->options(fn (OpcoFile $record) => collect($record->allowedTransitions())
                        ->mapWithKeys(fn (OpcoStatut $s) => [$s->value => $s->getLabel()])
                        ->all())
                    ->required(),
                Textarea::make('commentaire')
                    ->label('Commentaire (optionnel)'),
            ])
            ->action(fn (OpcoFile $record, array $data) => self::executer(
                $record,
                OpcoStatut::from($data['statut']),
                $data['commentaire'] ?? null,
            ));
    }

    private static function executer(OpcoFile $record, OpcoStatut $cible, ?string $comment = null): void
    {
        try {
            $record->transitionTo($cible, $comment);

            Notification::make()
                ->title('Dossier OPCO : '.$cible->getLabel())
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
