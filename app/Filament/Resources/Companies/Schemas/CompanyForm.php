<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\CompanyStatut;
use App\Support\AdresseBan;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entreprise')
                    ->columns(2)
                    ->schema([
                        TextInput::make('raison_sociale')
                            ->label('Raison sociale')
                            ->placeholder('ex : Boulangerie Martin SARL')
                            ->required(),
                        TextInput::make('nom_commercial')
                            ->label('Nom commercial')
                            ->placeholder('ex : Chez Martin'),
                        TextInput::make('siret')
                            ->label('SIRET')
                            ->placeholder('ex : 123 456 789 00012')
                            ->helperText('14 chiffres')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('secteur')
                            ->label("Secteur d'activité")
                            ->placeholder('ex : Restauration, BTP, Informatique'),
                        Select::make('opco_id')
                            ->label('OPCO')
                            ->relationship('opco', 'nom')
                            ->searchable()
                            ->preload(),
                        // Le statut n'est pas demandé à la création : une nouvelle
                        // entreprise démarre en « Prospect » (défaut SQL). Le statut
                        // (dont Active / Inactive) se gère ensuite en édition.
                        Select::make('statut')
                            ->label('Statut')
                            ->options(CompanyStatut::class)
                            ->default(CompanyStatut::Prospect->value)
                            ->required()
                            ->visibleOn('edit'),
                    ]),
                Section::make('Adresse')
                    ->description('Recherchez une adresse pour remplir automatiquement les champs et géolocaliser l\'entreprise, ou saisissez-la à la main.')
                    ->columns(2)
                    ->schema([
                        Select::make('adresse_recherche')
                            ->label('Rechercher une adresse')
                            ->placeholder('Tapez une adresse…')
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->getSearchResultsUsing(fn (string $search): array => app(AdresseBan::class)->options($search))
                            ->getOptionLabelUsing(fn ($value): ?string => AdresseBan::decode($value)['label'] ?? null)
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $data = AdresseBan::decode($state);

                                if ($data === null) {
                                    return;
                                }

                                $set('adresse', $data['adresse']);
                                $set('code_postal', $data['code_postal']);
                                $set('ville', $data['ville']);
                                $set('pays', $data['pays'] ?? 'France');
                                $set('latitude', $data['latitude']);
                                $set('longitude', $data['longitude']);
                            })
                            ->helperText('Autocomplétion Base Adresse Nationale (France). La saisie manuelle reste possible.')
                            ->columnSpanFull(),
                        TextInput::make('adresse')
                            ->label('Adresse (voie)')
                            ->placeholder('ex : 5 avenue de la République')
                            ->columnSpanFull(),
                        TextInput::make('code_postal')
                            ->label('Code postal')
                            ->placeholder('ex : 69003'),
                        TextInput::make('ville')
                            ->label('Ville')
                            ->placeholder('ex : Lyon'),
                        TextInput::make('pays')
                            ->label('Pays')
                            ->default('France'),
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->helperText('Renseignée automatiquement par la recherche d\'adresse.'),
                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->helperText('Renseignée automatiquement par la recherche d\'adresse.'),
                    ]),
            ]);
    }
}
