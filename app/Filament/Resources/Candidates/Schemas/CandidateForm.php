<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Enums\AvailabilityType;
use App\Support\AdresseBan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nom')
                            ->label('Nom')
                            ->placeholder('ex : Dupont')
                            ->required(),
                        TextInput::make('prenom')
                            ->label('Prénom')
                            ->placeholder('ex : Marie')
                            ->required(),
                        TextInput::make('email')
                            ->label('Adresse e-mail')
                            ->email()
                            ->placeholder('ex : marie.dupont@email.com')
                            ->requiredWithout('telephone')
                            ->validationMessages(['required_without' => 'Renseignez au moins un email ou un téléphone.']),
                        TextInput::make('telephone')
                            ->label('Téléphone')
                            ->tel()
                            ->placeholder('ex : 06 12 34 56 78')
                            ->requiredWithout('email')
                            ->validationMessages(['required_without' => 'Renseignez au moins un email ou un téléphone.']),
                        DatePicker::make('date_naissance')
                            ->label('Date de naissance')
                            ->placeholder('ex : 15/03/2004')
                            ->displayFormat('d/m/Y'),
                    ]),
                Section::make('Adresse')
                    ->description('Recherchez une adresse pour remplir automatiquement les champs, ou saisissez-la à la main.')
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
                            })
                            ->helperText('Autocomplétion Base Adresse Nationale (France). La saisie manuelle reste possible.')
                            ->columnSpanFull(),
                        TextInput::make('adresse')
                            ->label('Adresse (voie)')
                            ->placeholder('ex : 12 rue des Écoles')
                            ->columnSpanFull(),
                        TextInput::make('code_postal')
                            ->label('Code postal')
                            ->placeholder('ex : 75005'),
                        TextInput::make('ville')
                            ->label('Ville')
                            ->placeholder('ex : Paris'),
                        TextInput::make('pays')
                            ->label('Pays')
                            ->default('France'),
                    ]),
                Section::make('Formation & suivi')
                    ->columns(2)
                    ->schema([
                        Select::make('formation_visee_id')
                            ->label('Formation visée')
                            ->relationship('formationVisee', 'libelle')
                            ->searchable()
                            ->preload(),
                        Select::make('promotion_id')
                            ->label('Classe / Promotion')
                            ->placeholder('Rechercher ou créer une promotion…')
                            ->relationship('promotion', 'libelle')
                            ->getOptionLabelFromRecordUsing(fn ($record) => trim($record->libelle.' — '.($record->annee_scolaire ?? '')))
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Select::make('formation_id')
                                    ->label('Formation')
                                    ->relationship('formation', 'libelle')
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('libelle')
                                    ->label('Libellé de la promotion')
                                    ->placeholder('ex : Promo 2025-2026')
                                    ->required(),
                                TextInput::make('annee_scolaire')
                                    ->label('Année scolaire')
                                    ->placeholder('ex : 2025-2026'),
                                DatePicker::make('date_debut')
                                    ->label('Début')
                                    ->displayFormat('d/m/Y'),
                                DatePicker::make('date_fin')
                                    ->label('Fin')
                                    ->displayFormat('d/m/Y'),
                            ])
                            ->createOptionModalHeading('Nouvelle promotion'),
                        TextInput::make('niveau_actuel')
                            ->label('Niveau actuel')
                            ->placeholder('ex : Terminale, Bac, Bac+2'),
                        Select::make('commercial_id')
                            ->label('Commercial')
                            ->relationship('commercial', 'name')
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make('CV du candidat')
                    ->description('Le CV est le seul document demandé à cette étape. Il sera automatiquement disponible côté pré-admission.')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cv')
                            ->label('CV (PDF, DOC ou DOCX)')
                            ->collection('cv')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ])
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->helperText('Taille maximale : 5 Mo.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Disponibilités')
                    ->description('Périodes de disponibilité ou d\'indisponibilité du candidat. Vous pouvez en ajouter plusieurs.')
                    ->schema([
                        Repeater::make('availabilities')
                            ->label('')
                            ->relationship()
                            ->schema([
                                Select::make('type')
                                    ->label('Type')
                                    ->options(AvailabilityType::class)
                                    ->default(AvailabilityType::Disponible->value)
                                    ->required(),
                                Toggle::make('immediate')
                                    ->label('Immédiate')
                                    ->helperText('Disponible tout de suite, sans date.')
                                    ->live(),
                                DatePicker::make('date_debut')
                                    ->label('À partir du')
                                    ->displayFormat('d/m/Y')
                                    ->hidden(fn ($get) => (bool) $get('immediate')),
                                DatePicker::make('date_fin')
                                    ->label('Jusqu\'au')
                                    ->displayFormat('d/m/Y')
                                    ->hidden(fn ($get) => (bool) $get('immediate')),
                                TextInput::make('commentaire')
                                    ->label('Commentaire')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Ajouter une disponibilité')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => ($state['type'] ?? null)
                                ? AvailabilityType::tryFrom($state['type'])?->getLabel()
                                : 'Disponibilité'),
                    ]),
            ]);
    }
}
