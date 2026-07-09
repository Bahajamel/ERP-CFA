<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCandidate extends EditRecord
{
    protected static string $resource = CandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Suppression = archivage en corbeille avec motif obligatoire, comme
            // dans la liste. La restauration et la purge se pilotent en Corbeille.
            Action::make('supprimer')
                ->label('Supprimer')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(fn (Candidate $record): string => "Supprimer {$record->nom_complet} ?")
                ->modalDescription('Le candidat et tous ses dossiers seront retirés de toutes les listes et '
                    .'placés dans la Corbeille pendant '.Candidate::DELAI_PURGE_JOURS.' jours (restauration '
                    .'possible) avant suppression définitive automatique.')
                ->modalSubmitActionLabel('Placer dans la corbeille')
                ->schema([
                    Textarea::make('motif_suppression')
                        ->label('Motif de suppression')
                        ->required()
                        ->rows(3)
                        ->placeholder('ex : doublon, candidature annulée, erreur de saisie…'),
                ])
                ->action(function (Candidate $record, array $data) {
                    $record->archiver($data['motif_suppression']);

                    Notification::make()->success()
                        ->title('Candidat placé dans la corbeille')
                        ->body('Restaurable pendant '.Candidate::DELAI_PURGE_JOURS.' jours (section Corbeille).')
                        ->send();

                    return redirect(CandidateResource::getUrl('index'));
                }),
        ];
    }
}
