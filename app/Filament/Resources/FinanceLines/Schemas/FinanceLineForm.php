<?php

namespace App\Filament\Resources\FinanceLines\Schemas;

use App\Models\Contract;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FinanceLineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('contract_id')
                    ->label('Contrat')
                    ->relationship('contract')
                    ->getOptionLabelFromRecordUsing(fn (Contract $record): string => self::libelleContrat($record))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('libelle')
                    ->label('Libellé')
                    ->placeholder('Coût de formation — année 1')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('montant_attendu')
                    ->label('Montant attendu')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('€'),
                TextInput::make('montant_accepte')
                    ->label('Montant accepté (financeur)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('€')
                    ->helperText('S\'il est renseigné, il sert de base facturable.'),
                TextInput::make('montant_bloque')
                    ->label('Montant bloqué')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('€')
                    ->live(onBlur: true),
                Textarea::make('motif_blocage')
                    ->label('Motif du blocage')
                    ->rows(2)
                    ->required(fn (Get $get): bool => (float) $get('montant_bloque') > 0)
                    ->visible(fn (Get $get): bool => (float) $get('montant_bloque') > 0)
                    ->helperText('Obligatoire dès qu\'un montant est bloqué.'),
                Textarea::make('commentaire')
                    ->label('Commentaire')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    /** Libellé lisible d'un contrat : « Apprenti — Entreprise ». */
    public static function libelleContrat(Contract $contract): string
    {
        $apprenti = $contract->candidate?->nom_complet ?? 'Contrat #'.$contract->id;
        $entreprise = $contract->company?->raison_sociale;

        return $entreprise ? "{$apprenti} — {$entreprise}" : $apprenti;
    }
}
