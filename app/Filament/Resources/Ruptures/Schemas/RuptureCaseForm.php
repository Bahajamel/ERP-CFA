<?php

namespace App\Filament\Resources\Ruptures\Schemas;

use App\Enums\RuptureInitiateur;
use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RuptureCaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contrat rompu')
                    ->description('Le contrat associé passe automatiquement à « Rompu » et une régularisation OPCO / Finance est créée.')
                    ->columns(2)
                    ->schema([
                        Select::make('contract_id')
                            ->label('Contrat')
                            ->relationship('contract', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => trim(
                                ($record->candidate?->nom_complet ?? 'Apprenti').' — '
                                .($record->company?->raison_sociale ?? ''),
                                ' —'
                            ))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->columnSpanFull(),
                        DatePicker::make('date_rupture')
                            ->label('Date de rupture')
                            ->displayFormat('d/m/Y')
                            ->default(now())
                            ->required(),
                        Select::make('statut')
                            ->label('Statut du dossier')
                            ->options(RuptureStatut::class)
                            ->default(RuptureStatut::Ouvert->value)
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Évolue via les actions (Accompagner, Reclasser, Clore).'),
                    ]),
                Section::make('Motif')
                    ->columns(2)
                    ->schema([
                        Select::make('motif')
                            ->label('Motif de rupture')
                            ->options(RuptureMotif::class)
                            ->default(RuptureMotif::CommunAccord->value)
                            ->required(),
                        Select::make('initiateur')
                            ->label("À l'initiative de")
                            ->options(RuptureInitiateur::class)
                            ->default(RuptureInitiateur::CommunAccord->value)
                            ->required(),
                        Textarea::make('motif_detail')
                            ->label('Précisions sur le motif')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Accompagnement & reclassement')
                    ->description("Suivi de l'apprenti et recherche d'un nouvel employeur (le contrat peut se poursuivre chez un autre employeur, art. L6222-18-2).")
                    ->columns(2)
                    ->schema([
                        Toggle::make('recherche_employeur')
                            ->label("Recherche d'un nouvel employeur en cours"),
                        Select::make('nouvelle_company_id')
                            ->label('Nouvel employeur (si reclassé)')
                            ->relationship('nouvelleCompany', 'raison_sociale')
                            ->searchable()
                            ->preload(),
                        Select::make('responsable_id')
                            ->label('Responsable du suivi')
                            ->relationship('responsable', 'name')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('date_cloture')
                            ->label('Date de clôture')
                            ->displayFormat('d/m/Y')
                            ->disabled()
                            ->dehydrated(),
                        Textarea::make('accompagnement')
                            ->label("Journal d'accompagnement")
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Placeholder::make('preuves')
                    ->label('Preuves')
                    ->content('Ajoutez les pièces (courrier de rupture, accusé de réception, convention de reclassement…) dans l\'onglet « Documents » après enregistrement.')
                    ->visibleOn('edit'),
            ]);
    }
}
