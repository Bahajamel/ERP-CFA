<?php

namespace App\Filament\Resources\Needs\Schemas;

use App\Enums\NeedStatut;
use App\Support\AdresseBan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

class NeedForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Poste recherché')
                    ->columns(2)
                    ->schema([
                        Select::make('company_id')
                            ->label('Entreprise')
                            ->relationship('company', 'raison_sociale')
                            ->searchable()
                            ->required(),
                        TextInput::make('intitule_poste')
                            ->label('Intitulé du poste')
                            ->placeholder('ex : Apprenti boulanger, Développeur web')
                            ->required(),
                        Select::make('formation_id')
                            ->label('Formation visée')
                            ->relationship('formation', 'libelle')
                            ->searchable()
                            ->preload(),
                        DatePicker::make('date_demarrage')
                            ->label('Date de démarrage souhaitée')
                            ->displayFormat('d/m/Y'),
                        TextInput::make('nb_postes')
                            ->label('Nombre de postes')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        TextInput::make('rythme')
                            ->label("Rythme d'alternance")
                            ->placeholder('ex : 2 j CFA / 3 j entreprise'),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(NeedStatut::class)
                            ->default(NeedStatut::Cree->value)
                            ->required(),
                        Textarea::make('prerequis')
                            ->label('Prérequis')
                            ->placeholder('ex : Niveau CAP, permis B, expérience en vente appréciée')
                            ->columnSpanFull(),
                    ]),
                Section::make('Localisation & rayon de recherche')
                    ->description('Géolocalisez le lieu du poste et définissez le rayon de recherche (km) : un cercle s\'affiche sur la carte.')
                    ->columns(2)
                    ->schema([
                        Select::make('adresse_recherche')
                            ->label('Rechercher une adresse')
                            ->placeholder('Tapez une adresse ou une ville…')
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

                                $set('localisation', $data['label'] ?? $data['adresse']);
                                $set('latitude', $data['latitude']);
                                $set('longitude', $data['longitude']);
                            })
                            ->helperText('Autocomplétion Base Adresse Nationale (France). Saisie manuelle possible ci-dessous.')
                            ->columnSpanFull(),
                        TextInput::make('localisation')
                            ->label('Localisation')
                            ->placeholder('ex : Lyon 3e, télétravail partiel')
                            ->columnSpanFull(),
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->live(onBlur: true)
                            ->helperText('Renseignée par la recherche d\'adresse.'),
                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->live(onBlur: true)
                            ->helperText('Renseignée par la recherche d\'adresse.'),
                        TextInput::make('rayon_km')
                            ->label('Rayon de recherche')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(300)
                            ->suffix('km')
                            ->live(onBlur: true)
                            ->helperText('Distance autour du point de référence.')
                            ->columnSpanFull(),
                        Placeholder::make('carte_rayon')
                            ->label('Carte')
                            ->columnSpanFull()
                            ->content(fn (Get $get): View => view('filament.needs.rayon-map', [
                                'lat' => $get('latitude'),
                                'lon' => $get('longitude'),
                                'rayonKm' => $get('rayon_km'),
                            ])),
                    ]),
                Section::make('Interlocuteurs entreprise')
                    ->columns(2)
                    ->schema([
                        Select::make('contact_id')
                            ->label('Contact responsable')
                            ->relationship('contact', 'nom')
                            ->searchable(),
                        Select::make('tuteur_id')
                            ->label('Tuteur prévu')
                            ->relationship('tuteur', 'nom')
                            ->searchable(),
                    ]),
            ]);
    }
}
