<?php

namespace App\Filament\Resources\Needs\RelationManagers;

use App\Enums\MatchingStatut;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MatchingsRelationManager extends RelationManager
{
    protected static string $relationship = 'matchings';

    protected static ?string $title = 'Candidats proposés';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('candidate_id')
                    ->label('Candidat')
                    ->relationship('candidate', 'nom')
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
                DatePicker::make('next_action_at')
                    ->label('Prochaine action')
                    ->displayFormat('d/m/Y'),
                Textarea::make('retour_entreprise')
                    ->label('Retour entreprise')
                    ->columnSpanFull(),
                Textarea::make('refusal_reason')
                    ->label('Motif de refus')
                    ->helperText('Obligatoire pour un statut « Refusé » (motif ou retour entreprise).')
                    ->visible(fn (Get $get): bool => in_array($get('statut'), [
                        MatchingStatut::RefuseEntreprise->value,
                        MatchingStatut::RefuseCandidat->value,
                    ], true))
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Notes internes')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('candidate.nom')
            ->columns([
                TextColumn::make('candidate.nom_complet')
                    ->label('Candidat')
                    ->getStateUsing(fn ($record) => $record->candidate?->nom_complet)
                    ->searchable(['nom', 'prenom']),
                IconColumn::make('cv_envoye')
                    ->label('CV envoyé')
                    ->boolean(),
                TextColumn::make('date_entretien')
                    ->label('Entretien')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()->label('Proposer un candidat'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
