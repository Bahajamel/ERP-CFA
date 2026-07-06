<?php

namespace App\Filament\Resources\Ruptures;

use App\Enums\RuptureStatut;
use App\Models\RuptureCase;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Actions de workflow du dossier de rupture, pilotées par la machine à états
 * (colonne statut). Réutilisées table + en-tête d'édition.
 */
class RuptureCaseActions
{
    /** Transition générique vers un état réellement atteignable. */
    public static function changerStatut(): Action
    {
        return Action::make('changerStatut')
            ->label('Faire évoluer')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (RuptureCase $record) => count($record->allowedTransitions()) > 0)
            ->schema([
                Select::make('statut')
                    ->label('Nouveau statut')
                    ->options(fn (RuptureCase $record) => collect($record->allowedTransitions())
                        ->mapWithKeys(fn (RuptureStatut $s) => [$s->value => $s->getLabel()])
                        ->all())
                    ->required(),
                Textarea::make('note')
                    ->label("Note d'accompagnement (optionnelle)")
                    ->helperText('Ajoutée au journal du dossier.'),
            ])
            ->action(fn (RuptureCase $record, array $data) => self::executer(
                $record,
                RuptureStatut::from($data['statut']),
                $data['note'] ?? null,
            ));
    }

    /** Acter le reclassement : renseigne le nouvel employeur et passe le dossier à « Reclassé ». */
    public static function reclasser(): Action
    {
        return Action::make('reclasser')
            ->label('Acter le reclassement')
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('success')
            ->visible(fn (RuptureCase $record) => $record->statut->canTransitionTo(RuptureStatut::Reclasse))
            ->modalHeading('Reclassement de l\'apprenti')
            ->modalDescription('L\'apprenti a retrouvé un employeur : le dossier passe à « Reclassé ».')
            ->schema([
                Select::make('nouvelle_company_id')
                    ->label('Nouvel employeur')
                    ->relationship('nouvelleCompany', 'raison_sociale')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('note')
                    ->label('Note (optionnelle)'),
            ])
            ->action(function (RuptureCase $record, array $data) {
                $record->forceFill(['nouvelle_company_id' => $data['nouvelle_company_id']])->save();
                self::executer($record, RuptureStatut::Reclasse, $data['note'] ?? null);
            });
    }

    /** Clore le dossier (horodatage automatique). */
    public static function clore(): Action
    {
        return Action::make('clore')
            ->label('Clore le dossier')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Clore définitivement ce dossier de rupture ?')
            ->visible(fn (RuptureCase $record) => $record->statut->canTransitionTo(RuptureStatut::Clos))
            ->schema([
                Textarea::make('note')
                    ->label('Conclusion (optionnelle)'),
            ])
            ->action(fn (RuptureCase $record, array $data) => self::executer(
                $record,
                RuptureStatut::Clos,
                $data['note'] ?? null,
            ));
    }

    private static function executer(RuptureCase $record, RuptureStatut $cible, ?string $note = null): void
    {
        try {
            $record->transitionTo($cible, $note);

            Notification::make()
                ->title('Dossier de rupture : '.$cible->getLabel())
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
