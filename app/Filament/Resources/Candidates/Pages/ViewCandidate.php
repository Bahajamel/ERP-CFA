<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Vue 360° « Parcours de l'apprenant » : rassemble en un écran les 4 phases du
 * cycle de vie (admission → entreprise/contrat → OPCO → scolarité).
 */
class ViewCandidate extends ViewRecord
{
    protected static string $resource = CandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité & scolarité')
                ->columns(3)
                ->schema([
                    TextEntry::make('nom_complet')
                        ->label('Apprenti')
                        ->weight('bold'),
                    TextEntry::make('statut')
                        ->label('Statut')
                        ->badge(),
                    TextEntry::make('formationVisee.libelle')
                        ->label('Formation visée')
                        ->placeholder('—'),
                    TextEntry::make('promotion')
                        ->label('Classe / Promotion')
                        ->state(fn (Candidate $record) => $record->promotion
                            ? trim($record->promotion->libelle.' — '.$record->promotion->annee_scolaire)
                            : null)
                        ->badge()
                        ->color('info')
                        ->placeholder('Non affecté'),
                    TextEntry::make('commercial.name')
                        ->label('Commercial')
                        ->placeholder('—'),
                    TextEntry::make('contact')
                        ->label('Contact')
                        ->state(fn (Candidate $record) => $record->email ?: $record->telephone)
                        ->placeholder('—'),
                ]),

            Section::make('1 · Admission')
                ->columns(3)
                ->schema([
                    TextEntry::make('admission.statut')
                        ->label('Dossier d\'admission')
                        ->badge()
                        ->placeholder('Aucun dossier'),
                    TextEntry::make('pieces_obligatoires')
                        ->label('Pièces obligatoires')
                        ->state(function (Candidate $record) {
                            if (! $record->admission) {
                                return 'Aucun dossier';
                            }
                            $manquantes = $record->admission->piecesObligatoiresManquantes()->count();

                            return $manquantes === 0 ? 'Complet' : $manquantes.' manquante(s)';
                        })
                        ->badge()
                        ->color(fn (string $state) => $state === 'Complet' ? 'success' : 'warning'),
                    TextEntry::make('admission.validated_at')
                        ->label('Validé le')
                        ->dateTime('d/m/Y')
                        ->placeholder('—'),
                ]),

            Section::make('2 · Entreprise & contrat')
                ->columns(3)
                ->schema([
                    TextEntry::make('contrat_entreprise')
                        ->label('Entreprise')
                        ->state(fn (Candidate $record) => optional($record->contracts()->latest()->first())->company?->raison_sociale)
                        ->placeholder('Aucun contrat'),
                    TextEntry::make('contrat_statut')
                        ->label('Statut du contrat')
                        ->state(fn (Candidate $record) => optional($record->contracts()->latest()->first())->statut_contrat)
                        ->badge()
                        ->placeholder('—'),
                    TextEntry::make('contrat_periode')
                        ->label('Période')
                        ->state(function (Candidate $record) {
                            $contract = $record->contracts()->latest()->first();
                            if (! $contract || ! $contract->date_debut) {
                                return null;
                            }

                            return $contract->date_debut->format('d/m/Y')
                                .' → '.optional($contract->date_fin)->format('d/m/Y');
                        })
                        ->placeholder('—'),
                ]),

            Section::make('3 · Financement OPCO')
                ->columns(3)
                ->schema([
                    TextEntry::make('opco_statut')
                        ->label('Dossier OPCO')
                        ->state(fn (Candidate $record) => optional(optional($record->contracts()->latest()->first())->opcoFile)->statut)
                        ->badge()
                        ->placeholder('Aucun dossier'),
                    TextEntry::make('opco_montant')
                        ->label('Montant accepté')
                        ->state(fn (Candidate $record) => optional(optional($record->contracts()->latest()->first())->opcoFile)->montant_accepte)
                        ->money('EUR')
                        ->placeholder('—'),
                ]),

            Section::make('Documents & notes')
                ->columns(2)
                ->schema([
                    TextEntry::make('documents_count')
                        ->label('Documents')
                        ->state(fn (Candidate $record) => $record->documents()->count())
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('notes_count')
                        ->label('Notes internes')
                        ->state(fn (Candidate $record) => $record->notes()->count())
                        ->badge()
                        ->color('gray'),
                ]),
        ]);
    }
}
