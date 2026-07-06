<?php

namespace App\Filament\Resources\Matchings\Schemas;

use App\Enums\MatchingStatut;
use App\Models\Candidate;
use App\Models\Need;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MatchingForm
{
    /** Statuts « Refusé » qui exigent un motif. */
    private const REFUS = [
        MatchingStatut::RefuseEntreprise->value,
        MatchingStatut::RefuseCandidat->value,
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proposition candidat ↔ besoin')
                    ->columns(2)
                    ->schema([
                        Select::make('candidate_id')
                            ->label('Candidat')
                            ->relationship('candidate', 'nom')
                            ->getOptionLabelFromRecordUsing(fn (Candidate $record): string => $record->nom_complet)
                            ->searchable(['nom', 'prenom'])
                            ->preload()
                            ->required(),
                        Select::make('need_id')
                            ->label('Besoin')
                            ->relationship('need', 'intitule_poste')
                            ->getOptionLabelFromRecordUsing(fn (Need $record): string => $record->intitule_poste
                                .($record->company ? ' — '.$record->company->raison_sociale : ''))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(MatchingStatut::class)
                            ->default(MatchingStatut::Propose->value)
                            ->required()
                            ->live(),
                        Toggle::make('cv_envoye')
                            ->label('CV envoyé'),
                        DatePicker::make('date_entretien')
                            ->label("Date d'entretien")
                            ->displayFormat('d/m/Y'),
                        Textarea::make('retour_entreprise')
                            ->label('Retour entreprise')
                            ->placeholder('ex : Entretien positif, en attente de décision')
                            ->columnSpanFull(),
                        Textarea::make('refusal_reason')
                            ->label('Motif de refus')
                            ->placeholder('ex : Profil non retenu, désistement du candidat…')
                            ->helperText('Obligatoire pour passer à un statut « Refusé » (motif ou retour entreprise).')
                            ->visible(fn (Get $get): bool => in_array($get('statut'), self::REFUS, true))
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notes internes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
