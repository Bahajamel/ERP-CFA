<?php

namespace App\Filament\Resources\Entretiens;

use App\Enums\CandidateStatut;
use App\Enums\EntretienMode;
use App\Enums\EntretienStatut;
use App\Models\Entretien;
use App\Parcours\CycleApprenant;
use App\Parcours\CycleBloqueException;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

/**
 * Actions rapides de la section Entretiens (planifier, reprogrammer,
 * réaliser, absent, annuler, accepter / refuser le candidat). Chaque action
 * passe par la machine à états et le service central du cycle apprenant :
 * les statuts du candidat suivent automatiquement.
 */
class EntretienActions
{
    /** Champs du créneau (réutilisés par « Planifier » et « Reprogrammer »). */
    private static function champsCreneau(): array
    {
        return [
            DatePicker::make('date_entretien')
                ->label('Date')
                ->displayFormat('d/m/Y')
                ->native(false)
                ->required()
                ->default(fn (Entretien $record) => $record->date_entretien),
            TimePicker::make('heure_debut')
                ->label('Heure de début')
                ->seconds(false)
                ->required()
                ->default(fn (Entretien $record) => $record->heure_debut),
            TimePicker::make('heure_fin')
                ->label('Heure de fin')
                ->seconds(false)
                ->required()
                ->after('heure_debut')
                ->default(fn (Entretien $record) => $record->heure_fin),
            Select::make('mode')
                ->label('Mode')
                ->options(EntretienMode::class)
                ->default(fn (Entretien $record) => $record->mode?->value ?? EntretienMode::Presentiel->value)
                ->required()
                ->live(),
            TextInput::make('lien_visio')
                ->label('Lien visio')
                ->url()
                ->visible(fn (Get $get): bool => $get('mode') === EntretienMode::Visio->value)
                ->default(fn (Entretien $record) => $record->lien_visio),
        ];
    }

    /** Pose (ou repose) le créneau puis passe l'entretien à « Planifié ». */
    private static function planifierAvec(Entretien $record, array $data): void
    {
        $record->forceFill([
            'date_entretien' => $data['date_entretien'],
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'],
            'mode' => $data['mode'],
            'lien_visio' => $data['lien_visio'] ?? $record->lien_visio,
        ])->save();

        // Depuis « Absent », le passage repasse par « À reprogrammer ».
        if ($record->statut === EntretienStatut::Absent) {
            $record->transitionTo(EntretienStatut::AReprogrammer);
        }

        if ($record->statut !== EntretienStatut::Planifie) {
            $record->transitionTo(EntretienStatut::Planifie);
        }

        Notification::make()
            ->success()
            ->title('Entretien planifié')
            ->body($record->creneauLisible().' — '.($record->candidate?->nom_complet ?? '')
                .($record->candidate?->statut === CandidateStatut::EntretienPrevu
                    ? ' (candidat passé à « Entretien prévu »)' : ''))
            ->send();
    }

    public static function planifier(): Action
    {
        return Action::make('planifier')
            ->label('Planifier')
            ->icon('heroicon-o-calendar-days')
            ->color('info')
            ->visible(fn (Entretien $record): bool => in_array($record->statut, [
                EntretienStatut::APlanifier,
                EntretienStatut::AReprogrammer,
            ], true))
            ->modalHeading(fn (Entretien $record): string => 'Planifier l\'entretien — '.($record->candidate?->nom_complet ?? ''))
            ->schema(self::champsCreneau())
            ->action(function (Entretien $record, array $data): void {
                try {
                    self::planifierAvec($record, $data);
                } catch (InvalidTransitionException|ValidationException $e) {
                    Notification::make()->danger()->title('Planification impossible')
                        ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())
                        ->send();
                }
            });
    }

    public static function reprogrammer(): Action
    {
        return Action::make('reprogrammer')
            ->label('Reprogrammer')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (Entretien $record): bool => in_array($record->statut, [
                EntretienStatut::Planifie,
                EntretienStatut::Absent,
            ], true))
            ->modalHeading(fn (Entretien $record): string => 'Reprogrammer l\'entretien — '.($record->candidate?->nom_complet ?? ''))
            ->schema(self::champsCreneau())
            ->action(function (Entretien $record, array $data): void {
                try {
                    self::planifierAvec($record, $data);
                } catch (InvalidTransitionException|ValidationException $e) {
                    Notification::make()->danger()->title('Reprogrammation impossible')
                        ->body($e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage())
                        ->send();
                }
            });
    }

    public static function marquerRealise(): Action
    {
        return Action::make('marquerRealise')
            ->label('Marquer réalisé')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Entretien $record): bool => $record->statut === EntretienStatut::Planifie)
            ->schema([
                Textarea::make('compte_rendu')
                    ->label('Compte-rendu (optionnel)')
                    ->rows(4)
                    ->default(fn (Entretien $record) => $record->compte_rendu),
            ])
            ->action(function (Entretien $record, array $data): void {
                if (filled($data['compte_rendu'] ?? null)) {
                    $record->forceFill(['compte_rendu' => $data['compte_rendu']])->save();
                }

                $record->transitionTo(EntretienStatut::Realise);

                Notification::make()->success()
                    ->title('Entretien réalisé')
                    ->body('Le candidat passe à « Entretien réalisé ». Prenez maintenant la décision : '
                        .'accepter ou refuser le candidat.')
                    ->send();
            });
    }

    public static function marquerAbsent(): Action
    {
        return Action::make('marquerAbsent')
            ->label('Candidat absent')
            ->icon('heroicon-o-user-minus')
            ->color('warning')
            ->visible(fn (Entretien $record): bool => $record->statut === EntretienStatut::Planifie)
            ->requiresConfirmation()
            ->modalDescription('Le candidat repassera à « Entretien à planifier » : reprogrammez ensuite un créneau.')
            ->action(function (Entretien $record): void {
                $record->transitionTo(EntretienStatut::Absent);

                Notification::make()->warning()->title('Candidat marqué absent')->send();
            });
    }

    public static function annuler(): Action
    {
        return Action::make('annuler')
            ->label('Annuler')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Entretien $record): bool => $record->statut->estActif())
            ->requiresConfirmation()
            ->modalDescription('L\'annulation est définitive. Le candidat repassera à « Entretien à planifier » '
                .'si aucun autre entretien n\'est planifié.')
            ->action(function (Entretien $record): void {
                $record->transitionTo(EntretienStatut::Annule);

                Notification::make()->success()->title('Entretien annulé')->send();
            });
    }

    public static function accepterCandidat(): Action
    {
        return Action::make('accepterCandidat')
            ->label('Accepter le candidat')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Entretien $record): bool => $record->statut === EntretienStatut::Realise
                && ! ($record->candidate?->statut->estFinal() ?? true))
            ->requiresConfirmation()
            ->modalHeading(fn (Entretien $record): string => 'Accepter '.($record->candidate?->nom_complet ?? 'le candidat'))
            ->modalDescription('Le candidat passera à « Accepté » et un dossier Matching « En recherche » '
                .'sera créé automatiquement.')
            ->schema([
                Textarea::make('compte_rendu')
                    ->label('Compte-rendu (optionnel)')
                    ->rows(3),
            ])
            ->action(function (Entretien $record, array $data): void {
                try {
                    $candidate = app(CycleApprenant::class)
                        ->deciderApresEntretien($record, accepte: true, compteRendu: $data['compte_rendu'] ?? null);
                } catch (CycleBloqueException|InvalidTransitionException $e) {
                    Notification::make()->danger()->title('Décision impossible')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()
                    ->title('Candidat accepté')
                    ->body("{$candidate->nom_complet} : dossier Matching créé automatiquement (« En recherche »).")
                    ->send();
            });
    }

    public static function refuserCandidat(): Action
    {
        return Action::make('refuserCandidat')
            ->label('Refuser le candidat')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->visible(fn (Entretien $record): bool => $record->statut === EntretienStatut::Realise
                && ! ($record->candidate?->statut->estFinal() ?? true))
            ->requiresConfirmation()
            ->modalHeading(fn (Entretien $record): string => 'Refuser '.($record->candidate?->nom_complet ?? 'le candidat'))
            ->modalDescription('Le candidat passera à « Refusé » : il ne poursuivra pas le cycle (pas de Matching).')
            ->schema([
                Textarea::make('compte_rendu')
                    ->label('Motif / compte-rendu (optionnel)')
                    ->rows(3),
            ])
            ->action(function (Entretien $record, array $data): void {
                try {
                    $candidate = app(CycleApprenant::class)
                        ->deciderApresEntretien($record, accepte: false, compteRendu: $data['compte_rendu'] ?? null);
                } catch (CycleBloqueException|InvalidTransitionException $e) {
                    Notification::make()->danger()->title('Décision impossible')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()
                    ->title('Candidat refusé')
                    ->body("{$candidate->nom_complet} ne poursuit pas le cycle.")
                    ->send();
            });
    }
}
