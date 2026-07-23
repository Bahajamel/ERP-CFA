<?php

namespace App\Filament\Resources\Needs\Schemas;

use App\Enums\NeedStatut;
use App\Models\CompanyContact;
use App\Support\AdresseBan;
use App\Support\CustomFields;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

class NeedForm
{
    /**
     * Contacts de l'entreprise choisie (id → nom complet), pour les listes
     * « Contact responsable » / « Tuteur prévu ». Vide tant qu'aucune
     * entreprise n'est sélectionnée : on ne propose jamais les contacts d'un
     * autre employeur.
     *
     * @return array<int, string>
     */
    private static function contactsDeLEntreprise(mixed $companyId): array
    {
        if (blank($companyId)) {
            return [];
        }

        return CompanyContact::query()
            ->where('company_id', $companyId)
            ->get()
            ->mapWithKeys(fn (CompanyContact $c): array => [$c->id => $c->nom_complet])
            ->all();
    }

    /**
     * Champs d'un contact créé à la volée depuis le formulaire d'offre
     * (Filament createOptionForm). Tous obligatoires, avec contrôles : e-mail
     * valide (avec @), téléphone en chiffres uniquement, et choix explicite du
     * rôle (contact responsable OU tuteur). Le rôle est mappé sur les colonnes
     * is_principal / is_tuteur dans {@see self::creerContact()}.
     *
     * @return array<int, Component>
     */
    private static function champsContactRapide(): array
    {
        return [
            TextInput::make('nom')
                ->label('Nom')
                ->required(),
            TextInput::make('prenom')
                ->label('Prénom')
                ->required(),
            TextInput::make('fonction')
                ->label('Fonction')
                ->placeholder('ex : DRH, chef d\'atelier')
                ->required(),
            Radio::make('role')
                ->label('Rôle dans l\'entreprise')
                ->options([
                    'responsable' => 'Contact responsable',
                    'tuteur' => 'Tuteur / maître d\'apprentissage',
                ])
                ->required()
                ->validationMessages(['required' => 'Indiquez s\'il s\'agit d\'un contact responsable ou d\'un tuteur.']),
            TextInput::make('email')
                ->label('Adresse e-mail')
                ->email()
                ->required()
                ->validationMessages([
                    'required' => 'L\'adresse e-mail est obligatoire.',
                    'email' => 'Saisissez une adresse e-mail valide (avec @).',
                ]),
            TextInput::make('telephone')
                ->label('Téléphone')
                ->tel()
                ->required()
                // Chiffres uniquement (ni lettres, ni espaces, ni symboles).
                ->rule('regex:/^[0-9]+$/')
                ->validationMessages([
                    'required' => 'Le téléphone est obligatoire.',
                    'regex' => 'Le téléphone ne doit contenir que des chiffres.',
                ]),
        ];
    }

    /**
     * Crée le contact rattaché à l'entreprise sélectionnée et renvoie sa clé
     * (attendue par createOptionUsing). Le rôle choisi (obligatoire) est traduit
     * en indicateurs is_principal / is_tuteur ; la clé « role » n'étant pas une
     * colonne, elle est retirée avant l'écriture.
     */
    private static function creerContact(array $data, mixed $companyId): int
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        $data['company_id'] = $companyId;
        $data['is_principal'] = $role === 'responsable';
        $data['is_tuteur'] = $role === 'tuteur';

        return CompanyContact::create($data)->getKey();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Deux colonnes équilibrées (pas de trous entre cartes).
            ->columns(2)
            ->components([
                Group::make([
                    Section::make('Poste recherché')
                        ->icon('heroicon-o-briefcase')
                        ->columns(2)
                        ->schema([
                            Select::make('company_id')
                                ->label('Entreprise')
                                ->relationship('company', 'raison_sociale')
                                ->searchable()
                                ->preload()
                                // Réactif : filtre les interlocuteurs sur l'entreprise choisie.
                                ->live()
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
                            // Le statut n'est pas demandé à la création : une nouvelle offre
                            // démarre en « Besoin créé » (défaut SQL de la colonne statut). Il
                            // se gère ensuite en édition (ou via le Kanban des offres).
                            Select::make('statut')
                                ->label('Statut')
                                ->options(NeedStatut::class)
                                ->default(NeedStatut::Cree->value)
                                ->required()
                                ->visibleOn('edit'),
                            Textarea::make('prerequis')
                                ->label('Prérequis')
                                ->placeholder('ex : Niveau CAP, permis B, expérience en vente appréciée')
                                ->columnSpanFull(),
                            Textarea::make('competences_attendues')
                                ->label('Compétences recherchées')
                                ->placeholder("Une compétence par ligne\nex : Relation client\nOrganisation et planification")
                                ->helperText('Reprises sur la fiche besoin. Saisies par l\'entreprise sur le formulaire public, complétables ici.')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                    Section::make('Interlocuteurs entreprise')
                        ->icon('heroicon-o-user-circle')
                        ->description('Facultatif. Les contacts proposés sont ceux de l\'entreprise choisie — vous pouvez en créer un ici sans quitter le formulaire.')
                        ->columns(2)
                        ->schema([
                            Select::make('contact_id')
                                ->label('Contact responsable')
                                ->options(fn (Get $get): array => self::contactsDeLEntreprise($get('company_id')))
                                ->getOptionLabelUsing(fn ($value): ?string => optional(CompanyContact::find($value))->nom_complet)
                                ->searchable()
                                ->preload()
                                ->placeholder(fn (Get $get): string => blank($get('company_id'))
                                    ? 'Choisissez d\'abord une entreprise'
                                    : 'Sélectionnez ou ajoutez un contact')
                                ->disabled(fn (Get $get): bool => blank($get('company_id')))
                                ->createOptionForm(fn (): array => self::champsContactRapide())
                                ->createOptionUsing(fn (array $data, Get $get): int => self::creerContact($data, $get('company_id'))),
                            Select::make('tuteur_id')  // @creerContact mappe le rôle choisi
                                ->label('Tuteur prévu')
                                ->options(fn (Get $get): array => self::contactsDeLEntreprise($get('company_id')))
                                ->getOptionLabelUsing(fn ($value): ?string => optional(CompanyContact::find($value))->nom_complet)
                                ->searchable()
                                ->preload()
                                ->placeholder(fn (Get $get): string => blank($get('company_id'))
                                    ? 'Choisissez d\'abord une entreprise'
                                    : 'Sélectionnez ou ajoutez un tuteur')
                                ->disabled(fn (Get $get): bool => blank($get('company_id')))
                                ->createOptionForm(fn (): array => self::champsContactRapide())
                                ->createOptionUsing(fn (array $data, Get $get): int => self::creerContact($data, $get('company_id'))),
                        ]),
                ])->columnSpan(1),
                Group::make([
                    Section::make('Localisation & rayon de recherche')
                        ->icon('heroicon-o-map-pin')
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
                ])->columnSpan(1),
                // Colonnes personnalisées du CFA (section pleine largeur).
                ...CustomFields::formSchema('need'),
            ]);
    }
}
