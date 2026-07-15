<?php

namespace App\Filament\Resources\Candidates\Schemas;

use App\Models\Candidate;
use App\Support\AdresseBan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CandidateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Deux colonnes équilibrées : chaque colonne empile ses sections,
            // ce qui évite les « trous » d'alignement entre cartes de hauteurs
            // différentes.
            ->columns(2)
            ->components([
                Group::make([
                    Section::make('Identité')
                        ->icon('heroicon-o-identification')
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
                                ->displayFormat('d/m/Y')
                                // Pilote l'affichage immédiat de l'attestation de
                                // création de projet (candidats de 30 ans ou plus).
                                ->live(),
                        ]),
                    Section::make('Formation & suivi')
                        ->icon('heroicon-o-academic-cap')
                        ->columns(2)
                        ->schema([
                            Select::make('formation_visee_id')
                                ->label('Formation visée')
                                ->relationship('formationVisee', 'libelle')
                                ->searchable()
                                ->preload()
                                ->live(),
                            // Un apprenant suit plusieurs classes (matières), toutes de SA formation.
                            Select::make('promotions')
                                ->label('Classes (matières)')
                                ->multiple()
                                ->relationship(
                                    name: 'promotions',
                                    titleAttribute: 'libelle',
                                    modifyQueryUsing: fn ($query, Get $get) => $query
                                        ->with('formation')
                                        ->when($get('formation_visee_id'), fn ($q, $id) => $q->where('formation_id', $id)),
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nom_complet)
                                ->searchable()
                                ->preload()
                                ->helperText(fn (Get $get): string => $get('formation_visee_id')
                                    ? 'Seules les classes de la formation visée sont proposées.'
                                    : 'Choisissez d\'abord la formation visée.'),
                            TextInput::make('niveau_actuel')
                                ->label('Niveau actuel')
                                ->placeholder('ex : Terminale, Bac, Bac+2'),
                            Select::make('commercial_id')
                                ->label('Commercial')
                                ->relationship('commercial', 'name')
                                ->searchable()
                                ->preload(),
                        ]),
                    Section::make('Disponibilité')
                        ->icon('heroicon-o-calendar-days')
                        ->description('Date à partir de laquelle le candidat est disponible. Laissez vide si inconnue.')
                        ->schema([
                            DatePicker::make('date_disponibilite')
                                ->label('Disponible à partir du')
                                ->displayFormat('d/m/Y'),
                        ]),

                    Section::make('Consentement RGPD')
                        ->icon('heroicon-o-shield-check')
                        ->description('Sans cet accord, le candidat ne peut pas être proposé aux entreprises.')
                        ->schema([
                            Toggle::make('cv_consentement')
                                ->label('CV autorisé pour candidatures')
                                ->helperText(fn (?Candidate $record): string => $record?->cv_consentement_at
                                    ? 'Consentement donné le '.$record->cv_consentement_at->format('d/m/Y')
                                    : 'L\'apprenant accepte que son CV soit transmis aux entreprises partenaires.'),
                        ]),
                ])->columnSpan(1),

                Group::make([
                    Section::make('Adresse')
                        ->icon('heroicon-o-map-pin')
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
                    Section::make('Pièces du candidat')
                        ->icon('heroicon-o-document-arrow-up')
                        ->description('Le CV reste la pièce requise pour valider la pré-admission. Les justificatifs complètent le dossier dès la création.')
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
                                ->helperText('Taille maximale : 5 Mo. Automatiquement disponible côté pré-admission.')
                                ->columnSpanFull(),
                            SpatieMediaLibraryFileUpload::make('piece_identite')
                                ->label('Pièce d\'identité')
                                ->collection('piece_identite')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(5120)
                                ->downloadable()
                                ->openable()
                                ->helperText('Carte d\'identité, titre de séjour, passeport ou document équivalent (PDF/JPG/PNG, 5 Mo max).')
                                ->columnSpanFull(),
                            SpatieMediaLibraryFileUpload::make('carte_vitale')
                                ->label('Carte Vitale ou attestation de sécurité sociale')
                                ->collection('carte_vitale')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(5120)
                                ->downloadable()
                                ->openable()
                                ->helperText('PDF, JPG ou PNG — 5 Mo max.')
                                ->columnSpanFull(),
                            SpatieMediaLibraryFileUpload::make('attestation_projet')
                                ->label('Attestation de création de projet')
                                ->collection('attestation_projet')
                                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                                ->maxSize(5120)
                                ->downloadable()
                                ->openable()
                                // Visible et exigée uniquement pour les candidats
                                // de 30 ans ou plus (dérogation d'âge apprentissage).
                                ->visible(fn (Get $get): bool => Candidate::dateNaissancePlusDe30Ans($get('date_naissance')))
                                ->required(fn (Get $get): bool => Candidate::dateNaissancePlusDe30Ans($get('date_naissance')))
                                ->validationMessages([
                                    'required' => 'L\'attestation de création de projet est obligatoire pour les candidats de 30 ans ou plus.',
                                ])
                                ->helperText('Obligatoire uniquement pour les candidats de 30 ans ou plus (PDF/JPG/PNG, 5 Mo max).')
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(1),
            ]);
    }
}
