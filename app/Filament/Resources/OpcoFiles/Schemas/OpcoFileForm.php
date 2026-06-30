<?php

namespace App\Filament\Resources\OpcoFiles\Schemas;

use App\Enums\OpcoStatut;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OpcoFileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dossier OPCO')
                    ->columns(2)
                    ->schema([
                        Select::make('contract_id')
                            ->label('Contrat')
                            ->relationship('contract', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => 'Contrat #'.$record->id.' — '.($record->candidate?->nom_complet ?? ''))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('opco_id')
                            ->label('OPCO')
                            ->relationship('opco', 'nom')
                            ->searchable()
                            ->preload(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(OpcoStatut::class)
                            ->default(OpcoStatut::NonCree->value)
                            ->required(),
                        DatePicker::make('date_depot')
                            ->label('Date de dépôt')
                            ->displayFormat('d/m/Y'),
                    ]),
                Section::make('Financement')
                    ->columns(2)
                    ->schema([
                        TextInput::make('montant_prevu')
                            ->label('Montant prévu (€)')
                            ->numeric()
                            ->prefix('€'),
                        TextInput::make('montant_accepte')
                            ->label('Montant accepté (€)')
                            ->numeric()
                            ->prefix('€'),
                    ]),
                Section::make('Suivi & blocage')
                    ->columns(2)
                    ->schema([
                        Select::make('responsable_correction_id')
                            ->label('Responsable correction')
                            ->relationship('responsableCorrection', 'name')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('date_relance')
                            ->label('Date de relance')
                            ->displayFormat('d/m/Y'),
                        Textarea::make('motif_rejet')
                            ->label('Motif de rejet')
                            ->columnSpanFull(),
                        Textarea::make('commentaire_interne')
                            ->label('Commentaire interne')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
