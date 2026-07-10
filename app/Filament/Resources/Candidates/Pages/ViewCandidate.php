<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use App\Parcours\CycleApprenant;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Vue 360° « Parcours de l'apprenant » : timeline du cycle (Candidat →
 * Matching → Contrat → OPCO → Admission → Rupture) puis le détail de
 * chaque phase en un écran.
 */
class ViewCandidate extends ViewRecord
{
    protected static string $resource = CandidateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Point de création UNIQUE d'un entretien (masqué si un entretien
            // actif existe déjà → « Gérer l'entretien »).
            \Filament\Actions\Action::make('planifierEntretien')
                ->label('Planifier un entretien')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->visible(fn (): bool => ! $this->getRecord()->statut->estFinal()
                    && $this->getRecord()->entretienActif() === null)
                ->modalHeading(fn (): string => 'Planifier un entretien — '.$this->getRecord()->nom_complet)
                ->modalDescription('Le candidat passera automatiquement à « Entretien prévu ».')
                ->schema([
                    \Filament\Forms\Components\DatePicker::make('date_entretien')
                        ->label('Date')->displayFormat('d/m/Y')->native(false)->required(),
                    \Filament\Forms\Components\TimePicker::make('heure_debut')
                        ->label('Heure de début')->seconds(false)->required(),
                    \Filament\Forms\Components\TimePicker::make('heure_fin')
                        ->label('Heure de fin')->seconds(false)->required()->after('heure_debut'),
                    \Filament\Forms\Components\Select::make('mode')
                        ->label('Mode')
                        ->options(\App\Enums\EntretienMode::class)
                        ->default(\App\Enums\EntretienMode::Presentiel->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $entretien = $this->getRecord()->entretiens()->create($data + [
                            'statut' => \App\Enums\EntretienStatut::Planifie->value,
                            'responsable_id' => auth()->id(),
                        ]);
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        \Filament\Notifications\Notification::make()->danger()->title('Planification impossible')
                            ->body(collect($e->errors())->flatten()->first())->send();

                        return;
                    }

                    \Filament\Notifications\Notification::make()->success()
                        ->title('Entretien planifié')
                        ->body($entretien->creneauLisible().' — le candidat passe à « Entretien prévu ».')
                        ->send();
                }),
            // Un entretien est déjà en cours : lien direct pour le gérer.
            \Filament\Actions\Action::make('gererEntretien')
                ->label('Gérer l\'entretien')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->visible(fn (): bool => ! $this->getRecord()->statut->estFinal()
                    && $this->getRecord()->entretienActif() !== null)
                ->url(fn (): string => \App\Filament\Resources\Entretiens\EntretienResource::getUrl(
                    'edit',
                    ['record' => $this->getRecord()->entretienActif()],
                )),
            \Filament\Actions\Action::make('voirEntretiens')
                ->label('Entretiens')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->entretiens()->exists())
                ->url(fn (): string => \App\Filament\Resources\Entretiens\EntretienResource::getUrl('index')),
            \Filament\Actions\Action::make('voirMatching')
                ->label('Voir le matching')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->matchings()->exists())
                ->url(fn (): string => \App\Filament\Resources\Matchings\MatchingResource::getUrl('index')),
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // Où en est l'apprenant dans le cycle — visible d'un coup d'œil.
            Section::make('Parcours de l\'apprenant')
                ->schema([
                    TextEntry::make('parcours')
                        ->hiddenLabel()
                        ->state(fn (Candidate $record): HtmlString => new HtmlString(
                            view('filament.parcours.timeline', [
                                'etapes' => app(CycleApprenant::class)->etapes($record),
                            ])->render(),
                        )),
                ]),

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
                    TextEntry::make('promotions')
                        ->label('Classes (matières)')
                        ->state(fn (Candidate $record) => $record->promotions->map->nom_complet->all() ?: null)
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

            Section::make('Entretien de recrutement')
                ->columns(3)
                ->schema([
                    TextEntry::make('dernierEntretien.statut')
                        ->label('Dernier entretien')
                        ->badge()
                        ->placeholder('Aucun entretien'),
                    TextEntry::make('entretien_creneau')
                        ->label('Créneau')
                        ->state(fn (Candidate $record) => $record->dernierEntretien?->creneauLisible())
                        ->placeholder('—'),
                    TextEntry::make('dernierEntretien.responsable.name')
                        ->label('Responsable')
                        ->placeholder('—'),
                ]),

            Section::make('3 · Admission officielle')
                ->columns(3)
                ->schema([
                    TextEntry::make('admission.statut')
                        ->label('Admission')
                        ->badge()
                        ->placeholder('Pas encore admis'),
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

            Section::make('1 · Entreprise & contrat')
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

            Section::make('2 · Financement OPCO')
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
                        ->label('Attestation de projet (30 ans et plus)')
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
