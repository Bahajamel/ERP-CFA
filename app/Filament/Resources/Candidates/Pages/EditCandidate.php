<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditCandidate extends EditRecord
{
    protected static string $resource = CandidateResource::class;

    /** Page « Modifier » premium : chrome custom (carte résumé + barre sticky) enveloppant le formulaire Filament. */
    protected string $view = 'filament.resources.candidates.pages.edit-candidate';

    /**
     * Sur la page « Modifier », le formulaire occupe la colonne de droite (déjà
     * étroite face à la carte résumé) : on l'affiche en colonne unique pour des
     * sections pleine largeur et des champs confortables. Le schéma partagé
     * (CandidateForm) reste intact — la page « Créer » garde ses 2 colonnes.
     */
    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->columns(1);
    }

    /**
     * Données réelles de la carte résumé (aucune donnée fictive) : identité,
     * statut, formation, entreprise liée, dernière MAJ et progression du dossier
     * — le formulaire lui-même reste 100 % Filament (champs + validation).
     */
    protected function getViewData(): array
    {
        $c = $this->getRecord();

        // Progression du dossier : pièces requises présentes / total requis
        // (même règle que la fiche 360°).
        $requis = collect([
            'cv' => true,
            'piece_identite' => true,
            'carte_vitale' => true,
            'attestation_projet' => $c->plusDe30Ans(),
        ])->filter();

        $presentes = $requis->keys()->filter(fn (string $cle): bool => $c->getFirstMedia($cle) !== null)->count();
        $dossierPct = (int) round($presentes / max(1, $requis->count()) * 100);

        return [
            'resume' => [
                'entreprise' => $c->contracts()->with('company')->latest()->first()?->company?->raison_sociale,
                'formation' => $c->formationVisee?->libelle,
                'majLe' => $c->updated_at,
                'dossierPct' => $dossierPct,
            ],
        ];
    }

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
