<?php

namespace App\Filament\Resources\Admissions\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Dossier d'admission")
                    ->description('Le statut évolue via les actions de workflow (Valider, Faire évoluer), pas manuellement.')
                    ->columns(1)
                    ->schema([
                        Select::make('candidate_id')
                            ->label('Candidat')
                            ->relationship('candidate', 'nom')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->nom_complet)
                            ->searchable(['nom', 'prenom'])
                            ->required()
                            // Un dossier reste rattaché à son candidat.
                            ->disabledOn('edit'),
                        Textarea::make('commentaire')
                            ->label('Commentaire')
                            ->rows(3),
                    ]),
            ]);
    }
}
