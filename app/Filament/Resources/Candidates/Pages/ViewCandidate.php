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
                    TextEntry::make('date_disponibilite')
                        ->label('Disponible à partir du')
                        ->date('d/m/Y')
                        ->placeholder('—'),
                ]),

            Section::make('1 · Admission')
                ->columns(3)
                ->schema([
                    TextEntry::make('admission.statut')
                        ->label('Dossier d\'admission')
                        ->badge()
                        ->placeholder('Aucun dossier'),
                    TextEntry::make('cv')
                        ->label('CV')
                        ->state(fn (Candidate $record) => $record->hasCv() ? 'CV fourni' : 'CV manquant')
                        ->badge()
                        ->color(fn (string $state) => $state === 'CV fourni' ? 'success' : 'danger'),
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

            Section::make('Pièces justificatives')
                ->columns(3)
                ->schema([
                    TextEntry::make('piece_identite')
                        ->label('Pièce d\'identité')
                        ->state(fn (Candidate $record) => $record->getFirstMedia('piece_identite') ? 'Fournie' : 'Manquante')
                        ->badge()
                        ->color(fn (string $state) => $state === 'Fournie' ? 'success' : 'gray')
                        ->url(fn (Candidate $record) => $record->getFirstMediaUrl('piece_identite') ?: null, shouldOpenInNewTab: true),
                    TextEntry::make('carte_vitale')
                        ->label('Carte Vitale / sécu')
                        ->state(fn (Candidate $record) => $record->getFirstMedia('carte_vitale') ? 'Fournie' : 'Manquante')
                        ->badge()
                        ->color(fn (string $state) => $state === 'Fournie' ? 'success' : 'gray')
                        ->url(fn (Candidate $record) => $record->getFirstMediaUrl('carte_vitale') ?: null, shouldOpenInNewTab: true),
                    TextEntry::make('attestation_projet')
                        ->label('Attestation de projet (+30 ans)')
                        ->state(fn (Candidate $record) => $record->getFirstMedia('attestation_projet')
                            ? 'Fournie'
                            : ($record->plusDe30Ans() ? 'Manquante — requise' : 'Non requise'))
                        ->badge()
                        ->color(fn (string $state) => match (true) {
                            $state === 'Fournie' => 'success',
                            str_starts_with($state, 'Manquante') => 'danger',
                            default => 'gray',
                        })
                        ->url(fn (Candidate $record) => $record->getFirstMediaUrl('attestation_projet') ?: null, shouldOpenInNewTab: true)
                        ->visible(fn (Candidate $record) => $record->plusDe30Ans() || $record->getFirstMedia('attestation_projet')),
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
