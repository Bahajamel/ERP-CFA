<?php

namespace App\Filament\Resources\Matchings\Schemas;

use App\Enums\MatchingStatut;
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
                            ->searchable(['nom', 'prenom'])
                            ->required(),
                        Select::make('need_id')
                            ->label('Besoin')
                            ->relationship('need', 'intitule_poste')
                            ->searchable()
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
                        DatePicker::make('next_action_at')
                            ->label('Prochaine action')
                            ->displayFormat('d/m/Y')
                            ->helperText('Date de relance / prochain suivi commercial.'),
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
