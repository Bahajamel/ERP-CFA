<?php

namespace App\Filament\Resources\Admissions;

use App\Enums\AdmissionStatut;
use App\Models\Admission;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Actions de workflow du dossier d'admission, pilotées par la machine à états.
 * Réutilisées dans la table et l'en-tête de la page d'édition.
 */
class AdmissionActions
{
    /**
     * Valider (« présenter ») le dossier de pré-admission. Visible seulement si
     * la règle métier l'autorise, c.-à-d. si le CV du candidat est présent.
     */
    public static function valider(): Action
    {
        return Action::make('valider')
            ->label('Valider le dossier')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Le CV du candidat est présent. Confirmer la validation de ce dossier de pré-admission ?')
            ->visible(fn (Admission $record) => $record->canTransitionTo(AdmissionStatut::Valide))
            ->action(function (Admission $record) {
                self::executer($record, AdmissionStatut::Valide);
            });
    }

    /** Transition générique vers un état atteignable (structure + gardes métier). */
    public static function changerStatut(): Action
    {
        return Action::make('changerStatut')
            ->label('Faire évoluer')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Admission $record) => count($record->allowedTransitions()) > 0)
            ->schema([
                Select::make('statut')
                    ->label('Nouveau statut')
                    ->options(fn (Admission $record) => collect($record->allowedTransitions())
                        ->mapWithKeys(fn (AdmissionStatut $s) => [$s->value => $s->getLabel()])
                        ->all())
                    ->required(),
                Textarea::make('commentaire')
                    ->label('Commentaire (optionnel)'),
            ])
            ->action(function (Admission $record, array $data) {
                self::executer($record, AdmissionStatut::from($data['statut']), $data['commentaire'] ?? null);
            });
    }

    private static function executer(Admission $record, AdmissionStatut $cible, ?string $comment = null): void
    {
        try {
            $record->transitionTo($cible, $comment);

            Notification::make()
                ->title('Statut mis à jour : '.$cible->getLabel())
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
