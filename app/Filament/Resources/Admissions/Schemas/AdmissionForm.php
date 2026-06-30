<?php

namespace App\Filament\Resources\Admissions\Schemas;

use App\Enums\AdmissionStatut;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Dossier d'admission")
                    ->columns(2)
                    ->schema([
                        Select::make('candidate_id')
                            ->label('Candidat')
                            ->relationship('candidate', 'nom')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(AdmissionStatut::class)
                            ->default(AdmissionStatut::AVerifier->value)
                            ->required(),
                        Select::make('validated_by')
                            ->label('Validé par')
                            ->relationship('validatedBy', 'name')
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('validated_at')
                            ->label('Validé le')
                            ->displayFormat('d/m/Y H:i'),
                        Textarea::make('commentaire')
                            ->label('Commentaire')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
