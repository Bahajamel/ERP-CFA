<?php

namespace App\Filament\Resources\Ruptures\Schemas;

use App\Enums\RuptureMotif;
use App\Models\Contract;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RuptureForm
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
                    ->helperText('L\'ouverture bascule le contrat en « Rompu » et l\'admission officielle en « Rupture ». '
                        .'Un seul dossier de rupture par contrat.')
                    ->columnSpanFull(),
                DatePicker::make('date_rupture')
                    ->label('Date de rupture')
                    ->default(now())
                    ->displayFormat('d/m/Y')
                    ->required(),
                Select::make('motif')
                    ->label('Motif')
                    ->options(RuptureMotif::class)
                    ->required(),
                Select::make('initiative')
                    ->label('À l\'initiative de')
                    ->options([
                        'Employeur' => 'Employeur',
                        'Apprenti' => 'Apprenti',
                        'Commun accord' => 'Commun accord',
                    ]),
                Textarea::make('commentaire')
                    ->label('Commentaire interne')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function libelleContrat(Contract $contract): string
    {
        $apprenti = $contract->candidate?->nom_complet ?? 'Contrat #'.$contract->id;
        $entreprise = $contract->company?->raison_sociale;

        return $entreprise ? "{$apprenti} — {$entreprise}" : $apprenti;
    }
}
