<?php

namespace App\Filament\Resources\OpcoFiles\Schemas;

use App\Enums\OpcoStatut;
use App\Models\OpcoFile;
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
                Section::make('Statut du dossier')
                    ->description('Faire évoluer le statut applique les règles métier (garde de dépôt, motif de rejet, etc.).')
                    ->visibleOn('edit')
                    ->schema([
                        Select::make('statut')
                            ->label('Statut')
                            ->options(fn (?OpcoFile $record): array => $record ? self::statutOptions($record) : [])
                            ->required(),
                    ]),
                Section::make('Dossier OPCO')
                    ->columns(2)
                    ->schema([
                        Select::make('contract_id')
                            ->label('Contrat')
                            ->relationship('contract', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => 'Contrat #'.$record->id.' — '.($record->candidate?->nom_complet ?? ''))
                            ->searchable()
                            ->required(),
                        Select::make('opco_id')
                            ->label('OPCO')
                            ->relationship('opco', 'nom')
                            ->searchable()
                            ->preload(),
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
                            ->placeholder('ex : 8000')
                            ->prefix('€'),
                        TextInput::make('montant_accepte')
                            ->label('Montant accepté (€)')
                            ->numeric()
                            ->placeholder('ex : 8000')
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
                            ->placeholder('ex : CERFA incomplet, pièce manquante')
                            ->columnSpanFull(),
                        Textarea::make('commentaire_interne')
                            ->label('Commentaire interne')
                            ->placeholder("ex : Note pour l'équipe, relance prévue le…")
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Statuts sélectionnables en édition : le statut actuel plus les transitions
     * réellement atteignables selon la machine à états.
     *
     * @return array<string, string>
     */
    private static function statutOptions(OpcoFile $record): array
    {
        return collect([$record->statut, ...$record->allowedTransitions()])
            ->unique()
            ->mapWithKeys(fn (OpcoStatut $s): array => [$s->value => $s->getLabel()])
            ->all();
    }
}
