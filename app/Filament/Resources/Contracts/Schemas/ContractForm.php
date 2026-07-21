<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Enums\ContractStatut;
use App\Enums\ModaliteSuivi;
use App\Enums\TypeContrat;
use App\Models\Candidate;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Filament\Resources\Contracts\ContractActions;
use App\Models\Organisation;
use App\Models\Promotion;
use App\Services\ContractDocumentService;
use App\Services\DossierCompletion;
use App\Services\OpcoFundingService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Support\AdresseBan;
use App\Support\EntrepriseAnnuaire;
use App\Support\OpcoDetector;
use App\Support\RemunerationApprenti;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Fiche « tour de contrôle » du dossier contrat (phase 2) : la page d'édition
 * présente le dossier en 6 onglets (Suivi dossier / Étudiant / Contrat /
 * Entreprise / Gestion / Calendrier) + une colonne de résumé à droite.
 *
 * Chaque onglet collecte les informations nécessaires au CERFA et à la
 * convention, préremplies depuis la création (wizard) et complétables
 * progressivement. Les onglets Étudiant / Entreprise éditent les MODÈLES LIÉS
 * (candidat, entreprise, contacts, tuteur) : le préremplissage et la sauvegarde
 * de ces champs sont gérés par la page {@see \App\Filament\Resources\Contracts\Pages\EditContract}.
 *
 * La création initiale passe par l'assistant (wizard), pas par ce formulaire.
 */
class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // Liens vers les entités (conservés en état de formulaire pour le
                // barème de rémunération et le rattachement du tuteur).
                Hidden::make('candidate_id'),
                Hidden::make('company_id'),

                Tabs::make('Dossier')
                    ->persistTabInQueryString()
                    ->columnSpan(['default' => 3, 'xl' => 2])
                    ->tabs([
                        self::ongletSuivi(),
                        self::ongletEtudiant(),
                        self::ongletEntreprise(),
                        self::ongletContrat(),
                        self::ongletGestion(),
                        self::ongletCalendrier(),
                    ]),

                Section::make('Résumé du dossier')
                    ->columnSpan(['default' => 3, 'xl' => 1])
                    ->schema([
                        Placeholder::make('resume')
                            ->hiddenLabel()
                            ->content(fn (Contract $record): HtmlString => new HtmlString(
                                view('filament.contracts.dossier-sidebar', [
                                    'contract' => $record,
                                    'completion' => app(DossierCompletion::class)->pour($record),
                                ])->render(),
                            )),
                    ]),
            ]);
    }

    /* ==============================  Onglet Suivi dossier  ========================== */

    private static function ongletSuivi(): Tab
    {
        return Tab::make('Suivi dossier')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->schema([
                Placeholder::make('suivi')
                    ->hiddenLabel()
                    ->content(fn (Contract $record): HtmlString => new HtmlString(
                        view('filament.contracts.dossier-suivi', [
                            'contract' => $record,
                            'completion' => app(DossierCompletion::class)->pour($record),
                            'etat' => app(ContractDocumentService::class)->completude($record),
                        ])->render(),
                    )),
            ]);
    }

    /* ================================  Onglet Étudiant  ============================= */

    private static function ongletEtudiant(): Tab
    {
        return Tab::make('Étudiant')
            ->icon(Heroicon::OutlinedUser)
            ->badge(fn (Contract $record): ?string => self::badge($record, 'etudiant'))
            ->schema([
                self::blocFormation(),
                self::blocVolumeHeures(),
                self::blocDonneesPersonnelles(),
                self::blocSituationSociale(),
                self::blocEtudes(),
                Section::make('Documents de l\'apprenant')
                    ->description('Pièces déjà fournies. Le dépôt et la gestion se font dans la fiche de l\'apprenant.')
                    ->collapsed()
                    ->schema([
                        Placeholder::make('documents_apprenant')
                            ->hiddenLabel()
                            ->content(fn (Contract $record): HtmlString => new HtmlString(
                                view('filament.contracts.dossier-documents-apprenant', [
                                    'candidate' => $record->candidate,
                                ])->render(),
                            )),
                    ]),
            ]);
    }

    /** Bloc 1 — Formation (formation, promotion, lieu de réalisation). */
    private static function blocFormation(): Section
    {
        return Section::make('Formation')
            ->columns(2)
            ->schema([
                Select::make('formation_id')
                    ->label('Nom de la formation')
                    ->relationship('formation', 'libelle')
                    ->searchable()->preload()->required()
                    ->placeholder('Rechercher par nom, option, spécialité, RNCP…'),
                Select::make('promotion_id')
                    ->label('Nom de la promotion')
                    ->options(fn (Get $get): array => blank($get('formation_id'))
                        ? []
                        : Promotion::query()->where('formation_id', $get('formation_id'))
                            ->get()->pluck('nom_complet', 'id')->all())
                    ->searchable()
                    ->placeholder('Session de formation'),
                Section::make('Adresse de réalisation de la formation')
                    ->icon('heroicon-o-map-pin')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('lieu_formation_recherche')
                            ->label('Adresse')->placeholder('Tapez une adresse…')
                            ->searchable()->live()->dehydrated(false)
                            ->getSearchResultsUsing(fn (string $search): array => app(AdresseBan::class)->options($search))
                            ->getOptionLabelUsing(fn ($value): ?string => AdresseBan::decode($value)['label'] ?? null)
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $data = AdresseBan::decode($state);
                                if ($data === null) {
                                    return;
                                }
                                $set('lieu_formation_numero', $data['numero']);
                                $set('lieu_formation', $data['voie'] ?? $data['adresse'] ?? $data['label']);
                                $set('lieu_formation_code_postal', $data['code_postal']);
                                $set('lieu_formation_ville', $data['ville']);
                                $set('lieu_formation_pays', $data['pays'] ?? 'France');
                            })
                            ->columnSpanFull(),
                        TextInput::make('lieu_formation_numero')->label('Numéro'),
                        TextInput::make('lieu_formation')->label('Rue')->markAsRequired(),
                        TextInput::make('lieu_formation_complement')->label('Complément d\'adresse')->columnSpanFull(),
                        TextInput::make('lieu_formation_code_postal')->label('Code postal')->markAsRequired(),
                        TextInput::make('lieu_formation_ville')->label('Ville')->markAsRequired(),
                        TextInput::make('lieu_formation_pays')->label('Pays')->default('France')->markAsRequired(),
                        TextInput::make('lieu_formation_denomination')
                            ->label('Dénomination du lieu de formation')
                            ->helperText('À renseigner si le lieu principal de formation est distinct du CFA responsable.')
                            ->columnSpanFull(),
                        TextInput::make('lieu_formation_uai')->label('N° UAI du lieu de formation'),
                        TextInput::make('lieu_formation_siret')
                            ->label('N° SIRET du lieu de formation')
                            ->helperText('Sert à la vérification de l\'habilitation à former.'),
                    ]),
            ]);
    }

    /** Bloc 2 — Volume d'heures et dates de formation. */
    private static function blocVolumeHeures(): Section
    {
        return Section::make('Volume d\'heures et dates de formation')
            ->columns(2)
            ->schema([
                TextInput::make('duree_formation_heures')
                    ->label('Durée de la formation')->numeric()->minValue(0)->suffix('heures')->markAsRequired()->placeholder('ex : 455'),
                Select::make('duree_diplome')
                    ->label('Durée nécessaire à l\'obtention du diplôme')
                    ->options(config('cerfa_options.duree_diplome'))->native(false),
                Select::make('annee_cycle')
                    ->label('Année du cycle où l\'étudiant débute')
                    ->options(config('cerfa_options.annee_cycle'))->native(false)->markAsRequired(),
                DatePicker::make('date_debut')
                    ->label('Date de début de la 1ère année de formation')->displayFormat('d/m/Y')->required()
                    ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::preRemplirSalaire($set, $get)),
                DatePicker::make('date_fin')
                    ->label('Date de fin de la 1ère année de formation')->displayFormat('d/m/Y')->required()
                    ->afterOrEqual('date_debut')
                    ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::preRemplirSalaire($set, $get)),
                Select::make('responsable_pedagogique_id')
                    ->label('Responsable pédagogique')
                    ->options(fn (): array => self::utilisateursCfa())->searchable()->preload(),
                TextInput::make('convention_modele')
                    ->label('Convention')->placeholder('ex : Convention de formation par apprentissage'),
                Select::make('responsable_id')
                    ->label('Responsable dossier')
                    ->options(fn (): array => self::utilisateursCfa())->searchable()->preload(),
                TextInput::make('nombre_organismes_formation')
                    ->label('Nombre d\'organismes de formation intervenant')->numeric()->minValue(1)->default(1),
                Select::make('modalite_suivi')
                    ->label('Modalités de suivi')->options(ModaliteSuivi::class)->native(false),
                TextInput::make('heures_elearning')
                    ->label('Nombre d\'heures en e-learning')->numeric()->minValue(0)->suffix('heures')
                    ->live(onBlur: true),
                TextInput::make('heures_classe_virtuelle')
                    ->label('Nombre d\'heures en classe virtuelle')->numeric()->minValue(0)->suffix('heures')
                    ->live(onBlur: true),
                self::ouiNon('etudiant_formation_initiale_precedente', 'L\'étudiant était-il en formation initiale précédemment ?'),
                Placeholder::make('alerte_distance')
                    ->hiddenLabel()
                    ->content(fn (Get $get): HtmlString => self::alerteDistance($get))
                    ->visible(fn (Get $get): bool => self::partDistance($get) !== null)
                    ->columnSpanFull(),
            ]);
    }

    /** Bloc 3 — Données personnelles de l'apprenant. */
    private static function blocDonneesPersonnelles(): Section
    {
        return Section::make('Données personnelles')
            ->columns(2)
            ->schema([
                TextInput::make('etudiant_prenom')->label('Prénom')->required(),
                TextInput::make('etudiant_nom')->label('Nom')->required(),
                DatePicker::make('etudiant_date_naissance')->label('Date de naissance')->displayFormat('d/m/Y')->markAsRequired(),
                Select::make('etudiant_sexe')->label('Sexe')->options(config('cerfa_options.sexe'))->native(false)->markAsRequired(),
                TextInput::make('etudiant_email')->label('Adresse email')->email()->required(),
                TextInput::make('etudiant_telephone')->label('Téléphone')->placeholder('ex : +33 6 12 34 56 78'),
                self::ouiNon('etudiant_emancipe', 'Émancipé(e) ?'),
                Select::make('etudiant_nationalite')->label('Nationalité')->options(config('cerfa_options.nationalite'))->native(false)->markAsRequired(),
                Section::make('Adresse de l\'apprenant')
                    ->icon('heroicon-o-map-pin')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('etudiant_recherche')
                            ->label('Rechercher une adresse')->placeholder('Tapez une adresse…')
                            ->searchable()->live()->dehydrated(false)
                            ->getSearchResultsUsing(fn (string $search): array => app(AdresseBan::class)->options($search))
                            ->getOptionLabelUsing(fn ($value): ?string => AdresseBan::decode($value)['label'] ?? null)
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $data = AdresseBan::decode($state);
                                if ($data === null) {
                                    return;
                                }
                                $set('etudiant_adresse', $data['adresse'] ?? $data['label']);
                                $set('etudiant_code_postal', $data['code_postal']);
                                $set('etudiant_ville', $data['ville']);
                                $set('etudiant_pays', $data['pays'] ?? 'France');
                            })
                            ->columnSpanFull(),
                        TextInput::make('etudiant_adresse')->label('Adresse (voie)')->markAsRequired()->columnSpanFull(),
                        TextInput::make('etudiant_code_postal')->label('Code postal'),
                        TextInput::make('etudiant_ville')->label('Ville'),
                        TextInput::make('etudiant_pays')->label('Pays')->default('France'),
                    ]),
                Section::make('Représentant légal')
                    ->description('À renseigner si l\'apprenti est mineur non émancipé (nom, adresse et courriel du représentant légal).')
                    ->icon('heroicon-o-user-group')
                    ->columnSpanFull()
                    ->columns(2)
                    ->collapsed(fn (Get $get): bool => blank($get('etudiant_repr_legal_nom')))
                    ->schema([
                        TextInput::make('etudiant_repr_legal_nom')->label('Nom de naissance'),
                        TextInput::make('etudiant_repr_legal_prenom')->label('Prénom'),
                        TextInput::make('etudiant_repr_legal_email')->label('Courriel')->email()->columnSpanFull(),
                        TextInput::make('etudiant_repr_legal_adresse')->label('Adresse (voie)')->columnSpanFull(),
                        TextInput::make('etudiant_repr_legal_complement')->label('Complément d\'adresse')->columnSpanFull(),
                        TextInput::make('etudiant_repr_legal_code_postal')->label('Code postal'),
                        TextInput::make('etudiant_repr_legal_ville')->label('Ville'),
                    ]),
                self::ouiNon('etudiant_ne_en_france', 'Né(e) en France (métropolitaine ou d\'outre-mer) ?')->live(),
                TextInput::make('etudiant_departement_naissance')
                    ->label('Département de naissance')
                    ->visible(fn (Get $get): bool => (bool) $get('etudiant_ne_en_france'))
                    ->placeholder('ex : 92'),
                TextInput::make('etudiant_lieu_naissance')->label('Ville de naissance')->placeholder('ex : Chaville'),
                TextInput::make('etudiant_num_secu')
                    ->label('N° de sécurité sociale')
                    ->helperText('15 chiffres. Donnée sensible : chiffrée au repos. Nécessaire au CERFA.')
                    ->placeholder('ex : 1 90 12 92 000 000 00'),
            ]);
    }

    /** Bloc 4 — Situation sociale / administrative. */
    private static function blocSituationSociale(): Section
    {
        return Section::make('Situation sociale / administrative')
            ->columns(2)
            ->schema([
                self::ouiNon('etudiant_rqth', 'Reconnu(e) travailleur handicapé (RQTH) ?'),
                self::ouiNon('etudiant_aeeh_pch_pps', 'Bénéficiaire de l\'AEEH, la PCH ou d\'un PPS ?'),
                self::ouiNon('etudiant_boe', 'Bénéficiaire de l\'obligation d\'emploi (BOE) ?'),
                Select::make('etudiant_regime_social')->label('Régime social')->options(config('cerfa_options.regime_social'))->native(false),
                self::ouiNon('etudiant_sportif_haut_niveau', 'Inscrit comme sportif de haut niveau ?'),
                Select::make('etudiant_situation_avant_contrat')
                    ->label('Situation avant ce contrat')->options(config('cerfa_options.situation_avant_contrat'))->native(false)->markAsRequired(),
                self::ouiNon('etudiant_projet_creation_entreprise', 'Projet de création ou reprise d\'entreprise ?'),
            ]);
    }

    /** Bloc 5 — Études (diplômes obtenus et préparés). */
    private static function blocEtudes(): Section
    {
        return Section::make('Études')
            ->columns(2)
            ->schema([
                Select::make('etudiant_niveau_diplome_max')
                    ->label('Niveau du diplôme ou titre le plus élevé obtenu')->options(config('cerfa_options.niveau_diplome'))->native(false)->markAsRequired(),
                Select::make('etudiant_diplome_max')
                    ->label('Diplôme ou titre le plus élevé obtenu')->options(config('cerfa_options.diplome'))->native(false)->markAsRequired(),
                Select::make('etudiant_niveau_dernier_diplome_prepare')
                    ->label('Niveau du dernier diplôme ou titre préparé')->options(config('cerfa_options.niveau_diplome'))->native(false)->markAsRequired(),
                Select::make('etudiant_dernier_diplome_prepare')
                    ->label('Dernier diplôme ou titre préparé')->options(config('cerfa_options.diplome'))->native(false)->markAsRequired(),
                TextInput::make('etudiant_intitule_dernier_diplome')
                    ->label('Intitulé précis du dernier diplôme ou titre préparé')->markAsRequired()->columnSpanFull(),
                Select::make('etudiant_derniere_classe_suivie')
                    ->label('Dernière classe ou année suivie')->options(config('cerfa_options.derniere_classe'))->native(false)->markAsRequired(),
            ]);
    }

    /* ================================  Onglet Contrat  ============================= */

    private static function ongletContrat(): Tab
    {
        return Tab::make('Contrat')
            ->icon(Heroicon::OutlinedDocumentText)
            ->badge(fn (Contract $record): ?string => self::badge($record, 'contrat'))
            ->schema([
                self::blocTermesContrat(),
                self::blocCalendrierRemuneration(),
                self::blocRemunerationAnnuelle(),
                self::blocDonneesFinancieres(),
                self::blocResteAChargeEntreprise(),
                self::blocCalendrierFinancement(),
                self::blocFraisAnnexes(),
            ]);
    }

    /** Bloc 1 — Termes du contrat (nature, durée du travail, avantages, missions). */
    private static function blocTermesContrat(): Section
    {
        return Section::make('Termes du contrat')
            ->description('Formation, dates et volume horaire de formation se renseignent dans l\'onglet « Étudiant ».')
            ->columns(2)
            ->schema([
                ToggleButtons::make('type_contrat')
                    ->label('Contrat')->options(TypeContrat::class)->inline()->required()->columnSpanFull(),
                Select::make('mode_contractuel')
                    ->label('Mode contractuel de l\'apprentissage')
                    ->options(config('cerfa_options.mode_contractuel'))->native(false)->markAsRequired(),
                Select::make('nature_contrat')
                    ->label('Quelle est la nature du contrat ?')
                    ->options(config('cerfa_options.nature_contrat'))->native(false)->markAsRequired(),
                self::ouiNon('derogation', 'Existe-t-il une dérogation à ce contrat ?'),
                Select::make('type_derogation')
                    ->label('Type de dérogation')
                    ->options(config('cerfa_options.type_derogation'))->native(false)
                    ->visible(fn (Get $get): bool => (bool) $get('derogation'))
                    ->markAsRequired(),
                TextInput::make('code_rncp')->label('Code RNCP')->placeholder('ex : RNCP34556')
                    ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::detecterNpec($set, $get)),
                DatePicker::make('date_signature')->label('Date de signature du contrat')->displayFormat('d/m/Y'),
                TextInput::make('duree_hebdo_heures')->label('Durée hebdomadaire du travail')
                    ->numeric()->minValue(0)->maxValue(60)->suffix('heures')->placeholder('ex : 35'),
                TextInput::make('duree_hebdo_minutes')->label('Durée hebdomadaire du travail')
                    ->numeric()->minValue(0)->maxValue(59)->suffix('minutes')->placeholder('ex : 0'),
                TextInput::make('avantage_repas')->label('Montant par repas')
                    ->numeric()->minValue(0)->step('0.01')->suffix('€ / repas'),
                TextInput::make('avantage_logement')->label('Montant pour le logement')
                    ->numeric()->minValue(0)->step('0.01')->suffix('€ / mois'),
                self::ouiNon('autres_avantages', 'Existe-t-il d\'autres avantages en nature ?')->live(),
                TextInput::make('autres_avantages_detail')->label('Précisez les autres avantages en nature')
                    ->visible(fn (Get $get): bool => (bool) $get('autres_avantages'))->columnSpanFull(),
                TextInput::make('emploi_occupe')->label('Intitulé du poste')
                    ->placeholder('ex : Développeur web')->columnSpanFull(),
                self::ouiNon('travail_dangereux', 'L\'apprenti va-t-il travailler sur des machines dangereuses ou être exposé à des risques ?')
                    ->columnSpanFull(),
                Textarea::make('missions')->label('Décrivez les missions de votre futur apprenti')
                    ->rows(4)->columnSpanFull(),
            ]);
    }

    /** Bloc 2 — Calendrier du contrat et rémunération (dates + estimation légale). */
    private static function blocCalendrierRemuneration(): Section
    {
        return Section::make('Calendrier et rémunération')
            ->columns(2)
            ->schema([
                DatePicker::make('date_debut_contrat')->label('Date de début de contrat')->displayFormat('d/m/Y')->markAsRequired()
                    ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::apresDatesContrat($set, $get)),
                DatePicker::make('date_fin_contrat')->label('Date de fin de contrat')->displayFormat('d/m/Y')
                    ->markAsRequired()->afterOrEqual('date_debut_contrat')
                    ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::apresDatesContrat($set, $get)),
                DatePicker::make('date_fin_periode_essai')->label('Date de fin de période d\'essai prévue')->displayFormat('d/m/Y')
                    ->helperText('45 jours de formation pratique en entreprise (art. L6222-18). Ajustable.'),
                DatePicker::make('date_conclusion')->label('Date de conclusion du contrat')->displayFormat('d/m/Y'),
                DatePicker::make('date_debut_formation_pratique')->label('Date de début de formation pratique chez l\'employeur')->displayFormat('d/m/Y'),
                self::ouiNon('smc', 'Existe-t-il un salaire minimum conventionné (SMC) ?'),
                Placeholder::make('estimation_remuneration')
                    ->label('Estimation de la rémunération minimale')
                    ->content(fn (Get $get): HtmlString => self::estimationRemunerationHtml($get))
                    ->columnSpanFull(),
                TextInput::make('salaire_mensuel_brut')
                    ->label('Salaire brut mensuel de l\'apprenti à l\'embauche')->suffix('€')
                    ->numeric()->minValue(0)->step('0.01')->live(onBlur: true)
                    ->helperText('Pré-rempli avec le minimum légal ; ajustable à la hausse.')
                    ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                        if (blank($value) || ! is_numeric($value)) {
                            return;
                        }
                        $min = self::minimumLegal($get);
                        if ($min !== null && (float) $value + 0.005 < $min['montant']) {
                            $fail(sprintf(
                                'Salaire sous le minimum légal de l\'apprenti : %s € (%d %% du SMIC — %d ans, année %d du contrat).',
                                number_format($min['montant'], 2, ',', ' '),
                                $min['taux'], $min['age'], $min['annee'],
                            ));
                        }
                    }),
                TextInput::make('pourcentage_smic')->label('Pourcentage du SMIC')
                    ->numeric()->minValue(0)->maxValue(100)->step('0.01')->suffix('%'),
            ]);
    }

    /** Bloc 3 — Rémunération par année d'exécution (Année 1 ; évolutif Année 2/3). */
    private static function blocRemunerationAnnuelle(): Section
    {
        return Section::make('Rémunération par année')
            ->description('Répartition du taux applicable par année d\'exécution. Année 1 requise ; ajoutez Année 2 / 3 pour les contrats pluriannuels.')
            ->schema([
                Repeater::make('remuneration_annuelle')
                    ->hiddenLabel()
                    ->addActionLabel('Ajouter une année')
                    ->defaultItems(1)
                    ->columns(2)
                    ->itemLabel(fn (array $state): ?string => filled($state['annee'] ?? null) ? 'Année '.$state['annee'] : 'Nouvelle année')
                    ->schema([
                        TextInput::make('annee')->label('Année')->numeric()->minValue(1)->default(1),
                        Select::make('base')->label('SMIC ou SMC')
                            ->options(config('cerfa_options.base_remuneration'))->default('smic')->native(false),
                        DatePicker::make('date_debut')->label('Date de début')->displayFormat('d/m/Y'),
                        DatePicker::make('date_fin')->label('Date de fin')->displayFormat('d/m/Y'),
                        TextInput::make('pourcentage')->label('Pourcentage')->numeric()->minValue(0)->maxValue(100)->step('0.01')->suffix('%'),
                    ]),
            ]);
    }

    /** Bloc 4 — Données financières et financement OPCO (NPEC, engagement). */
    private static function blocDonneesFinancieres(): Section
    {
        return Section::make('Données financières')
            ->columns(2)
            ->schema([
                TextInput::make('cout_formation')->label('Montant total de la formation')
                    ->numeric()->minValue(0)->step('0.01')->suffix('€ net de taxe')->columnSpanFull(),
                Section::make('Financement OPCO')
                    ->columns(2)->columnSpanFull()
                    ->schema([
                        TextInput::make('npec_annuel')->label('NPEC annuel pour ce RNCP')
                            ->numeric()->minValue(0)->step('0.01')->suffix('€')
                            ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::recalculerOpco($set, $get))
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Niveau de prise en charge annuel fixé par France compétences pour ce diplôme (RNCP).'),
                        TextInput::make('npec_journalier')->label('NPEC journalier pour ce RNCP')
                            ->numeric()->minValue(0)->step('0.01')->suffix('€')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'NPEC annuel ÷ 365. Recalculé automatiquement, ajustable.'),
                        TextInput::make('nombre_jours_contrat')->label('Nombre de jours pour ce contrat')
                            ->numeric()->minValue(0)->suffix('jours')
                            ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::recalculerOpco($set, $get)),
                        TextInput::make('engagement_opco_total')->label('Montant de l\'engagement OPCO total')
                            ->numeric()->minValue(0)->step('0.01')->suffix('€')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'NPEC journalier × nombre de jours du contrat.'),
                        Actions::make([
                            Action::make('detecter_npec')
                                ->label('Détecter le NPEC depuis le RNCP / l\'IDCC')
                                ->icon('heroicon-o-sparkles')
                                ->color('gray')
                                ->action(fn (Set $set, Get $get) => self::detecterNpec($set, $get, forcer: true)),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    /** Bloc 5 — Montant du reste à charge pour l'entreprise (formule + / − = net). */
    private static function blocResteAChargeEntreprise(): Section
    {
        return Section::make('Montant du reste à charge pour l\'entreprise')
            ->columns(2)
            ->schema([
                ToggleButtons::make('reste_a_charge_zero')
                    ->label('Souhaitez-vous activer le reste à charge à 0 € automatiquement, hors participation obligatoire ?')
                    ->options([1 => 'Oui', 0 => 'Non'])->colors([1 => 'success', 0 => 'gray'])->inline()
                    ->formatStateUsing(fn ($state): int => (int) (bool) $state)
                    ->dehydrateStateUsing(fn ($state): bool => (bool) $state)
                    ->columnSpanFull(),
                TextInput::make('reste_a_charge_montant')->label('Montant du reste à charge pour l\'entreprise')
                    ->numeric()->minValue(0)->step('0.01')->suffix('€')
                    ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::recalculerNet($set, $get)),
                TextInput::make('participation_obligatoire')->label('Montant de la participation obligatoire (+)')
                    ->numeric()->minValue(0)->step('0.01')->suffix('€')
                    ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::recalculerNet($set, $get)),
                TextInput::make('participation_cfa')->label('Participation du CFA (−)')
                    ->numeric()->minValue(0)->step('0.01')->suffix('€')
                    ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::recalculerNet($set, $get)),
                TextInput::make('net_a_payer')->label('Montant net total à payer par l\'entreprise (=)')
                    ->numeric()->step('0.01')->suffix('€')->readOnly()
                    ->helperText('Reste à charge + participation obligatoire − participation du CFA.'),
            ]);
    }

    /** Bloc 6 — Calendrier de financement pluriannuel (montants par année). */
    private static function blocCalendrierFinancement(): Section
    {
        return Section::make('Calendrier de financement')
            ->description('Répartition des montants par année. Années 2 et 3 pour les contrats pluriannuels — laissez vide sinon (ne pas supprimer les lignes).')
            ->schema([
                Repeater::make('calendrier_financement')
                    ->hiddenLabel()
                    ->addActionLabel('Ajouter une année')
                    ->defaultItems(3)
                    ->reorderable(false)
                    ->columns(2)
                    ->itemLabel(fn (array $state): ?string => filled($state['annee'] ?? null) ? 'Année '.$state['annee'] : 'Nouvelle année')
                    ->schema([
                        TextInput::make('annee')->label('Année')->numeric()->minValue(1),
                        TextInput::make('formation')->label('Montant de formation')->numeric()->minValue(0)->step('0.01')->suffix('€'),
                        TextInput::make('financement')->label('Financement')->numeric()->minValue(0)->step('0.01')->suffix('€'),
                        TextInput::make('geste_commercial')->label('Geste commercial')->numeric()->minValue(0)->step('0.01')->suffix('€'),
                        TextInput::make('reste_a_charge')->label('Reste à charge')->numeric()->minValue(0)->step('0.01')->suffix('€'),
                    ]),
            ]);
    }

    /** Bloc 7 — Frais annexes finançables par l'OPCO. */
    private static function blocFraisAnnexes(): Section
    {
        $repere = config('apprentissage.frais_annexes');

        return Section::make('Frais annexes')
            ->columns(2)
            ->schema([
                self::ouiNon('frais_hebergement', 'Frais d\'hébergement ? ('.$repere['hebergement_par_nuit'].' €/nuit)'),
                self::ouiNon('frais_restauration', 'Frais de restauration ? ('.$repere['restauration_par_repas'].' €/repas)'),
                self::ouiNon('frais_equipement', 'Frais de premier équipement pédagogique ? ('.$repere['premier_equipement'].' €)')->live(),
                self::ouiNon('frais_mobilite', 'Frais de mobilité internationale ?'),
                Select::make('type_equipement')->label('Type de premier équipement pédagogique')
                    ->options(config('cerfa_options.type_equipement'))->native(false)
                    ->visible(fn (Get $get): bool => (bool) $get('frais_equipement'))
                    ->required(fn (Get $get): bool => (bool) $get('frais_equipement'))
                    ->columnSpanFull(),
            ]);
    }

    /* ==============================  Onglet Entreprise  ============================ */

    private static function ongletEntreprise(): Tab
    {
        return Tab::make('Entreprise')
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->badge(fn (Contract $record): ?string => self::badge($record, 'entreprise'))
            ->schema([
                self::blocContactEntreprise(),
                self::blocInfosEntreprise(),
                Section::make('Représentant légal')
                    ->description('Signataire du contrat et de la convention côté employeur.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('representant_prenom')->label('Prénom'),
                        TextInput::make('representant_nom')->label('Nom'),
                        TextInput::make('representant_email')->label('Adresse email')->email(),
                        TextInput::make('representant_poste')->label('Poste occupé'),
                    ]),
                self::blocMaitreApprentissage(),
                self::blocSecondMaitre(),
                self::blocResponsableFinancier(),
                self::blocResteACharge(),
                Section::make('Informations annexes obligatoires')
                    ->schema([
                        Select::make('informations_annexes')
                            ->hiddenLabel()
                            ->options(config('cerfa_options.informations_annexes'))
                            ->default('aucune')
                            ->native(false)
                            ->live(),
                        TextInput::make('informations_annexes_detail')
                            ->label(fn (Get $get): string => $get('informations_annexes') === 'bon_commande'
                                ? 'N° de bon de commande'
                                : 'Précisez')
                            ->visible(fn (Get $get): bool => in_array($get('informations_annexes'), ['bon_commande', 'autres'], true)),
                    ]),
            ]);
    }

    /** Bloc 1 — Contact de l'entreprise. */
    private static function blocContactEntreprise(): Section
    {
        return Section::make('Contact de l\'entreprise')
            ->description('Personne opérationnelle en lien avec le CFA (peut différer du représentant légal, du tuteur et du contact de facturation).')
            ->columns(3)
            ->schema([
                TextInput::make('contact_prenom')->label('Prénom contact entreprise'),
                TextInput::make('contact_nom')->label('Nom contact entreprise'),
                TextInput::make('contact_email')->label('Adresse email contact entreprise')->email(),
            ]);
    }

    /** Bloc 2 — Informations de l'entreprise (identité, administratif, employeur, entité à facturer). */
    private static function blocInfosEntreprise(): Section
    {
        return Section::make('Informations de l\'entreprise')
            ->columns(2)
            ->schema([
                Select::make('entreprise_recherche')
                    ->label('Rechercher / mettre à jour depuis l\'Annuaire des Entreprises')
                    ->placeholder('Raison sociale ou SIRET…')
                    ->searchable()->live()->dehydrated(false)
                    ->getSearchResultsUsing(fn (string $search): array => app(EntrepriseAnnuaire::class)->options($search))
                    ->getOptionLabelUsing(fn ($value): ?string => EntrepriseAnnuaire::decode($value)['label'] ?? null)
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $fiche = EntrepriseAnnuaire::decode($state);
                        if ($fiche === null) {
                            return;
                        }
                        $set('entreprise_raison_sociale', $fiche['raison_sociale'] ?? null);
                        $set('entreprise_nom_commercial', $fiche['nom_commercial'] ?? null);
                        $set('entreprise_siret', $fiche['siret'] ?? null);
                        $set('entreprise_siren', $fiche['siren'] ?? ContractWizard::sirenDepuisSiret($fiche['siret'] ?? null));
                        $set('entreprise_forme_juridique', $fiche['forme_juridique'] ?? null);
                        $set('entreprise_code_ape_naf', $fiche['code_ape_naf'] ?? null);
                        $set('entreprise_code_idcc', $fiche['code_idcc'] ?? null);
                        $set('entreprise_numero_siege', $fiche['numero'] ?? null);
                        $set('entreprise_adresse', $fiche['adresse'] ?? null);
                        $set('entreprise_complement_adresse', $fiche['complement_adresse'] ?? null);
                        $set('entreprise_code_postal', $fiche['code_postal'] ?? null);
                        $set('entreprise_ville', $fiche['ville'] ?? null);

                        // Représentant légal proposé depuis le dirigeant personne
                        // physique le plus à même d'engager la société (gérant,
                        // président…), sa qualité servant de poste — modifiable
                        // ensuite. Les autres dirigeants sont signalés pour que
                        // l'utilisateur sache qu'un autre signataire est possible.
                        if (filled($fiche['dirigeant_nom'] ?? null)) {
                            $set('representant_nom', $fiche['dirigeant_nom']);
                            $set('representant_prenom', $fiche['dirigeant_prenom'] ?? null);
                            $set('representant_poste', $fiche['dirigeant_qualite'] ?? null);
                        }

                        self::signalerAutresDirigeants($fiche['dirigeants'] ?? []);
                    })
                    ->helperText('Facultatif : remplit les champs depuis l\'Annuaire officiel. Saisie manuelle possible.')
                    ->columnSpanFull(),

                // Adresse de réalisation (exécution) du contrat.
                Section::make('Adresse de réalisation du contrat')
                    ->icon('heroicon-o-map-pin')
                    ->columnSpanFull()->columns(2)
                    ->schema([
                        Select::make('lieu_execution_recherche')
                            ->label('Adresse de réalisation du contrat')->markAsRequired()
                            ->placeholder('Tapez une adresse…')->searchable()->live()->dehydrated(false)
                            ->getSearchResultsUsing(fn (string $search): array => app(AdresseBan::class)->options($search))
                            ->getOptionLabelUsing(fn ($value): ?string => AdresseBan::decode($value)['label'] ?? null)
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $data = AdresseBan::decode($state);
                                if ($data === null) {
                                    return;
                                }
                                $set('lieu_execution_numero', $data['numero']);
                                $set('lieu_execution', $data['voie'] ?? $data['adresse'] ?? $data['label']);
                                $set('lieu_execution_code_postal', $data['code_postal']);
                                $set('lieu_execution_ville', $data['ville']);
                                $set('lieu_execution_pays', $data['pays'] ?? 'France');
                            })
                            ->columnSpanFull(),
                        TextInput::make('lieu_execution_numero')->label('Numéro'),
                        TextInput::make('lieu_execution')->label('Rue'),
                        TextInput::make('lieu_execution_complement')->label('Complément d\'adresse')->columnSpanFull(),
                        TextInput::make('lieu_execution_code_postal')->label('Code postal'),
                        TextInput::make('lieu_execution_ville')->label('Ville'),
                        TextInput::make('lieu_execution_pays')->label('Pays')->default('France'),
                    ]),

                // Identité légale (conservée — préremplie depuis la création).
                Section::make('Identité de l\'entreprise')
                    ->collapsed()->columnSpanFull()->columns(2)
                    ->schema([
                        TextInput::make('entreprise_raison_sociale')->label('Nom légal')->markAsRequired(),
                        TextInput::make('entreprise_nom_commercial')->label('Nom commercial'),
                        TextInput::make('entreprise_forme_juridique')->label('Forme juridique'),
                        TextInput::make('entreprise_siret')->label('SIRET')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Set $set) => $set('entreprise_siren', ContractWizard::sirenDepuisSiret($state))),
                        TextInput::make('entreprise_siren')->label('SIREN'),
                        TextInput::make('entreprise_siret_etablissement')->label('SIRET établissement'),
                        TextInput::make('entreprise_ville_rcs')->label('Ville RCS'),
                        TextInput::make('entreprise_numero_siege')->label('Numéro du siège'),
                        TextInput::make('entreprise_adresse')->label('Adresse du siège')->columnSpanFull(),
                        TextInput::make('entreprise_complement_adresse')->label('Complément d\'adresse')->columnSpanFull(),
                        TextInput::make('entreprise_code_postal')->label('Code postal'),
                        TextInput::make('entreprise_ville')->label('Ville'),
                        TextInput::make('entreprise_pays')->label('Pays')->default('France'),
                    ]),

                // Informations administratives (CERFA / OPCO).
                TextInput::make('entreprise_code_ape_naf')->label('Code APE / NAF')
                    ->hintIcon('heroicon-o-information-circle', tooltip: 'Code composé de 4 chiffres et 1 lettre. Vous pouvez retrouver l\'information sur le site société.com et sur votre Kbis.'),
                TextInput::make('entreprise_code_idcc')->label('Code IDCC')
                    ->live(onBlur: true)->afterStateUpdated(fn (Set $set, Get $get) => self::detecterNpec($set, $get))
                    ->hintIcon('heroicon-o-information-circle', tooltip: "Il se compose de 4 chiffres maximum. Pour le trouver cliquez ici.\nExemples :\n9998 - Convention non encore en vigueur\n9999 - Absence de convention collective\n5501 - Convention d'entreprise indépendante ou texte assimilé non précisé\n5100 - Statut divers ou inconnus"),
                TextInput::make('entreprise_convention_collective')->label('Convention collective')->columnSpanFull(),
                Select::make('entreprise_caisse_retraite')->label('Caisse de retraite complémentaire')
                    ->options(config('cerfa_options.caisse_retraite'))->native(false)->searchable()->markAsRequired(),
                TextInput::make('entreprise_nombre_salaries')->label('Nombre de salariés total')->numeric()->minValue(0)->markAsRequired(),
                Select::make('entreprise_secteur')->label('Secteur d\'activité')
                    ->options(['prive' => 'Privé', 'public' => 'Public'])->native(false)->live()->markAsRequired(),
                TextInput::make('numero_accord_prealable')->label('Numéro d\'accord préalable')
                    ->visible(fn (Get $get): bool => $get('entreprise_secteur') === 'public'),
                Select::make('entreprise_type_employeur')
                    ->label('Type d\'employeur')
                    ->options(config('cerfa_options.type_employeur'))
                    ->native(false)->markAsRequired(),
                Select::make('entreprise_type_employeur_specifique')
                    ->label('Type d\'employeur spécifique')
                    ->options(config('cerfa_options.type_employeur_specifique'))
                    ->native(false)->markAsRequired(),

                // Entité à facturer + régimes (secteur public : entité distincte facturée).
                TextInput::make('facturation_entite_nom')->label('Nom de l\'entité à facturer')->markAsRequired()
                    ->visible(fn (Get $get): bool => $get('entreprise_secteur') === 'public'),
                Section::make('Adresse de l\'entité à facturer')
                    ->visible(fn (Get $get): bool => $get('entreprise_secteur') === 'public')
                    ->icon('heroicon-o-map-pin')->columnSpanFull()->columns(2)
                    ->schema([
                        Select::make('facturation_adresse_recherche')
                            ->label('Adresse')->markAsRequired()->placeholder('Tapez une adresse…')
                            ->searchable()->live()->dehydrated(false)
                            ->getSearchResultsUsing(fn (string $search): array => app(AdresseBan::class)->options($search))
                            ->getOptionLabelUsing(fn ($value): ?string => AdresseBan::decode($value)['label'] ?? null)
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $data = AdresseBan::decode($state);
                                if ($data === null) {
                                    return;
                                }
                                $set('facturation_adresse', $data['adresse'] ?? $data['label']);
                                $set('facturation_code_postal', $data['code_postal']);
                                $set('facturation_ville', $data['ville']);
                            })
                            ->columnSpanFull(),
                        TextInput::make('facturation_adresse')->label('Adresse (voie)')->columnSpanFull(),
                        TextInput::make('facturation_code_postal')->label('Code postal'),
                        TextInput::make('facturation_ville')->label('Ville'),
                    ]),
            ]);
    }

    /** Bloc 3 — Maître d'apprentissage (tuteur principal). */
    private static function blocMaitreApprentissage(): Section
    {
        return Section::make('Maître d\'apprentissage')
            ->description('Requis pour le CERFA. Choisissez un contact de l\'entreprise ou renseignez ses coordonnées.')
            ->columns(2)
            ->schema([
                Select::make('tuteur_id')
                    ->label('Contact désigné comme maître d\'apprentissage')
                    ->options(fn (Get $get): array => self::contactsEntreprise($get))
                    ->searchable()->live()
                    ->afterStateUpdated(fn ($state, Set $set) => self::chargerContactTuteur($state, $set, 'tuteur'))
                    ->placeholder('Aucun / nouveau maître d\'apprentissage')
                    ->columnSpanFull(),
                TextInput::make('tuteur_prenom')->label('Prénom'),
                TextInput::make('tuteur_nom')->label('Nom'),
                TextInput::make('tuteur_email')->label('Adresse email')->email(),
                TextInput::make('tuteur_telephone')->label('Téléphone')->placeholder('ex : +33 6 12 34 56 78'),
                TextInput::make('tuteur_fonction')->label('Poste occupé'),
                DatePicker::make('tuteur_date_naissance')->label('Date de naissance')->displayFormat('d/m/Y'),
                Select::make('tuteur_niveau_diplome')->label('Niveau de diplôme ou titre le plus élevé obtenu')
                    ->options(config('cerfa_options.niveau_diplome'))->native(false),
                Select::make('tuteur_diplome')->label('Diplôme ou titre le plus élevé obtenu')
                    ->options(config('cerfa_options.diplome'))->native(false),
            ]);
    }

    /** Bloc 4 — Second maître d'apprentissage (conditionnel). */
    private static function blocSecondMaitre(): Section
    {
        return Section::make('Second maître d\'apprentissage')
            ->columns(2)
            ->schema([
                self::ouiNon('second_maitre', 'Existe-t-il un second maître d\'apprentissage ?')
                    ->live()->columnSpanFull(),
                Select::make('tuteur2_id')
                    ->label('Contact désigné comme second maître')
                    ->options(fn (Get $get): array => self::contactsEntreprise($get))
                    ->searchable()->live()
                    ->afterStateUpdated(fn ($state, Set $set) => self::chargerContactTuteur($state, $set, 'tuteur2'))
                    ->visible(fn (Get $get): bool => (bool) $get('second_maitre'))
                    ->placeholder('Aucun / nouveau second maître')
                    ->columnSpanFull(),
                TextInput::make('tuteur2_prenom')->label('Prénom')->visible(fn (Get $get): bool => (bool) $get('second_maitre')),
                TextInput::make('tuteur2_nom')->label('Nom')->visible(fn (Get $get): bool => (bool) $get('second_maitre')),
                TextInput::make('tuteur2_email')->label('Adresse email')->email()->visible(fn (Get $get): bool => (bool) $get('second_maitre')),
                TextInput::make('tuteur2_telephone')->label('Téléphone')->visible(fn (Get $get): bool => (bool) $get('second_maitre')),
                TextInput::make('tuteur2_fonction')->label('Poste occupé')->visible(fn (Get $get): bool => (bool) $get('second_maitre')),
                DatePicker::make('tuteur2_date_naissance')->label('Date de naissance')->displayFormat('d/m/Y')->visible(fn (Get $get): bool => (bool) $get('second_maitre')),
                Select::make('tuteur2_niveau_diplome')->label('Niveau de diplôme le plus élevé obtenu')
                    ->options(config('cerfa_options.niveau_diplome'))->native(false)->visible(fn (Get $get): bool => (bool) $get('second_maitre')),
                Select::make('tuteur2_diplome')->label('Diplôme ou titre le plus élevé obtenu')
                    ->options(config('cerfa_options.diplome'))->native(false)->visible(fn (Get $get): bool => (bool) $get('second_maitre')),
            ]);
    }

    /** Bloc 5 — Responsable administratif et financier. */
    private static function blocResponsableFinancier(): Section
    {
        return Section::make('Responsable administratif et financier')
            ->columns(2)
            ->schema([
                TextInput::make('raf_prenom')->label('Prénom'),
                TextInput::make('raf_nom')->label('Nom'),
                TextInput::make('raf_email')->label('Adresse email')->email(),
                TextInput::make('raf_telephone')->label('Téléphone')->placeholder('ex : +33 6 12 34 56 78'),
            ]);
    }

    /** Bloc 6 — Informations concernant le règlement du reste à charge. */
    private static function blocResteACharge(): Section
    {
        return Section::make('Informations concernant le règlement du reste à charge')
            ->schema([
                Section::make('Adresse de facturation')
                    ->schema([
                        Select::make('adresse_facturation_type')
                            ->label('Adresse de facturation')
                            ->options(config('cerfa_options.adresse_facturation_type'))
                            ->default('convention')->native(false),
                    ]),
                Section::make('Contact de facturation')
                    ->columns(2)
                    ->schema([
                        TextInput::make('fac_prenom')->label('Prénom du contact de facturation'),
                        TextInput::make('fac_nom')->label('Nom du contact de facturation'),
                        TextInput::make('fac_email')->label('Adresse mail du contact de facturation')->email()->columnSpanFull(),
                    ]),
                Section::make('Informations entreprise')
                    ->schema([
                        TextInput::make('facturation_nom_societe')
                            ->label('Nom de la société (si différent du nom de la société de l\'entreprise pour la facturation)'),
                    ]),
                Section::make('Modalité d\'envoi de la facture du reste à charge')
                    ->schema([
                        Select::make('modalite_envoi_facture')
                            ->label('Modalité d\'envoi de la facture du reste à charge')
                            ->options(config('cerfa_options.modalite_envoi_facture'))
                            ->default('email')->native(false)->live(),
                        Repeater::make('facturation_emails')
                            ->label('Adresses email des destinataires')
                            ->schema([
                                TextInput::make('email')->label('Adresse email du destinataire')->email(),
                            ])
                            ->addActionLabel('Ajouter une adresse email')
                            ->defaultItems(1)
                            ->visible(fn (Get $get): bool => $get('modalite_envoi_facture') === 'email'),
                    ]),
            ]);
    }

    /** Contacts de l'entreprise du contrat (id => nom complet), pour les sélecteurs de tuteur. */
    private static function contactsEntreprise(Get $get): array
    {
        return blank($get('company_id'))
            ? []
            : CompanyContact::query()->where('company_id', $get('company_id'))
                ->get()->pluck('nom_complet', 'id')->all();
    }

    /** Charge les coordonnées d'un contact dans les champs du (second) maître d'apprentissage. */
    private static function chargerContactTuteur(mixed $state, Set $set, string $prefixe): void
    {
        $contact = filled($state) ? CompanyContact::find($state) : null;

        $set("{$prefixe}_prenom", $contact?->prenom);
        $set("{$prefixe}_nom", $contact?->nom);
        $set("{$prefixe}_email", $contact?->email);
        $set("{$prefixe}_telephone", $contact?->telephone);
        $set("{$prefixe}_fonction", $contact?->fonction);
        $set("{$prefixe}_date_naissance", $contact?->date_naissance?->format('Y-m-d'));
        $set("{$prefixe}_niveau_diplome", $contact?->niveau_diplome);
        $set("{$prefixe}_diplome", $contact?->diplome);
    }

    /* ================================  Onglet Gestion  ============================= */

    private static function ongletGestion(): Tab
    {
        return Tab::make('Gestion')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->schema([
                Grid::make(['default' => 1, 'xl' => 3])->schema([
                    // ---- Colonne principale (actions du dossier) ----
                    Group::make([
                        Section::make('Relecture de la convention de formation et du CERFA')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->schema([
                                Placeholder::make('relecture_statut')->hiddenLabel()
                                    ->content(fn (Contract $record): HtmlString => self::relectureBadge($record)),
                            ]),

                        Section::make('Engager le dossier')
                            ->schema([
                                Placeholder::make('engager_info')->hiddenLabel()
                                    ->content('Le dossier n\'est pas encore engagé ? Lancez-le dans le circuit de signature.'),
                                Actions::make([ContractActions::engagerDossier()]),
                            ])
                            ->visible(fn (Contract $record): bool => ! $record->estAnnule()
                                && $record->statut_contrat->canTransitionTo(ContractStatut::ManqueSignature)),

                        Section::make('Statut du dossier')
                            ->description('Modification directe du statut (utilisateurs avertis).')
                            ->collapsed()
                            ->schema([
                                Select::make('statut_contrat')
                                    ->label('Statut du dossier')
                                    ->options(fn (?Contract $record): array => $record ? self::statutOptions($record) : [])
                                    ->required(),
                            ]),

                        Section::make('Modifier les informations du contact entreprise et de l\'étudiant')
                            ->description('Corrections rapides sans passer par les onglets complets.')
                            ->collapsed()
                            ->schema([
                                Actions::make([
                                    ContractActions::modifierEtudiant(),
                                    ContractActions::modifierContactEntreprise(),
                                    ContractActions::modifierSignataire(),
                                ]),
                            ]),

                        Section::make('Déclarer le dossier non conforme')
                            ->collapsed()
                            ->schema([
                                Placeholder::make('non_conforme_info')->hiddenLabel()
                                    ->content(fn (Contract $record): HtmlString => self::nonConformiteInfo($record)),
                                Actions::make([
                                    ContractActions::declarerNonConforme(),
                                    ContractActions::leverNonConformite(),
                                ]),
                            ]),

                        Section::make('Annuler ou supprimer un dossier')
                            ->collapsed()
                            ->schema([
                                Placeholder::make('annuler_info')->hiddenLabel()
                                    ->content(fn (Contract $record): HtmlString => self::annulationInfo($record)),
                                Actions::make([
                                    ContractActions::annulerDossier(),
                                    ContractActions::reactiverDossier(),
                                ]),
                            ]),

                        Section::make('Changer le type de dossier')
                            ->collapsed()
                            ->schema([
                                Placeholder::make('type_info')->hiddenLabel()
                                    ->content(fn (Contract $record): HtmlString => self::typeDossierInfo($record)),
                                Actions::make([ContractActions::changerTypeContrat()]),
                            ]),
                    ])->columnSpan(['default' => 1, 'xl' => 2]),

                    // ---- Colonne secondaire (notes & réglages) ----
                    Group::make([
                        Section::make('Notes du dossier')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Textarea::make('notes_internes')->hiddenLabel()->rows(6)
                                    ->placeholder('Notes ou commentaires pour vos collègues')
                                    ->helperText('Suivi interne — non imprimé sur le CERFA ni la convention.'),
                            ]),

                        Section::make('Réglages du dossier')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Toggle::make('relances_activees')->label('Relances activées')
                                    ->helperText('Active les relances automatiques liées au dossier.')
                                    ->default(true)->onColor('success'),
                                Toggle::make('facturation_opco')->label('Facturation OPCO')
                                    ->helperText('Active la logique de facturation OPCO pour ce dossier.')
                                    ->default(true)->onColor('success'),
                            ]),

                        Section::make('Financement OPCO')
                            ->schema([
                                Placeholder::make('opco_gestion')->label('OPCO')
                                    ->content(fn (Contract $record): string => $record->company?->opco?->nom
                                        ?? $record->opcoFile?->opco?->nom ?? 'Non détecté'),
                                Placeholder::make('reste_a_charge_gestion')->label('Reste à charge à 0 €')
                                    ->content(fn (Contract $record): string => $record->reste_a_charge_zero ? 'Oui' : 'Non'),
                            ]),
                    ])->columnSpan(['default' => 1, 'xl' => 1]),
                ]),
            ]);
    }

    /** Badge de statut de relecture (onglet Gestion). */
    private static function relectureBadge(Contract $record): HtmlString
    {
        $statut = $record->relectureStatut();
        $couleurs = ['success' => '#22c55e', 'warning' => '#f59e0b', 'danger' => '#ef4444', 'info' => '#3b82f6', 'gray' => '#6b7280'];
        $c = $couleurs[$statut['color']] ?? '#3b82f6';

        return new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:.45rem;padding:.35rem .8rem;border-radius:9999px;'
            .'font-size:.8rem;font-weight:600;background:color-mix(in srgb,'.$c.' 16%,transparent);color:'.$c.'">'
            .'<span style="width:.5rem;height:.5rem;border-radius:9999px;background:'.$c.'"></span>'
            .e($statut['label']).'</span>'
        );
    }

    /** Texte informatif du bloc « Déclarer non conforme ». */
    private static function nonConformiteInfo(Contract $record): HtmlString
    {
        if ($record->estNonConforme()) {
            $date = $record->non_conforme_at?->format('d/m/Y');
            $motif = e($record->non_conforme_motif ?? '');

            return new HtmlString('<div style="font-size:.85rem;color:#ef4444">Dossier déclaré non conforme'
                .($date ? ' le '.$date : '').'.'.($motif !== '' ? ' Motif : '.$motif : '').'</div>');
        }

        return new HtmlString('<div style="font-size:.85rem;opacity:.85">Vous souhaitez déclarer le dossier non conforme ?</div>');
    }

    /** Texte informatif du bloc « Annuler ou supprimer ». */
    private static function annulationInfo(Contract $record): HtmlString
    {
        if ($record->estAnnule()) {
            $date = $record->annule_at?->format('d/m/Y');
            $motif = e($record->annulation_motif ?? '');

            return new HtmlString('<div style="font-size:.85rem;color:#f59e0b">Dossier annulé'
                .($date ? ' le '.$date : '').'.'.($motif !== '' ? ' Motif : '.$motif : '')
                .' Il reste consultable.</div>');
        }

        return new HtmlString('<div style="font-size:.85rem;opacity:.85">Vous souhaitez annuler le dossier en cours ?</div>');
    }

    /** Mode et type actuels du dossier (bloc « Changer le type de dossier »). */
    private static function typeDossierInfo(Contract $record): HtmlString
    {
        $type = $record->type_contrat === TypeContrat::Professionnalisation
            ? 'contrat de professionnalisation'
            : 'contrat d\'apprentissage';

        return new HtmlString(
            '<div style="font-size:.85rem;display:grid;gap:.3rem">'
            .'<div>Le dossier est en <b>mode manuel</b>.</div>'
            .'<div>Le dossier est un <b>'.$type.'</b>.</div></div>'
        );
    }

    /* ==============================  Onglet Calendrier  ============================ */

    private static function ongletCalendrier(): Tab
    {
        return Tab::make('Calendrier')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->schema([
                Section::make('Dates clés du dossier')
                    ->description('Vue de synthèse. Les dates se saisissent dans les onglets « Étudiant » (formation) et « Contrat » (signature).')
                    ->schema([
                        Placeholder::make('calendrier_apercu')
                            ->hiddenLabel()
                            ->content(fn (Contract $record): HtmlString => new HtmlString(
                                view('filament.contracts.dossier-calendrier', ['contract' => $record])->render(),
                            )),
                    ]),
            ]);
    }

    /* ================================  Helpers  ==================================== */

    /** Badge de complétude d'un onglet : « 60 % » tant que < 100, rien sinon. */
    private static function badge(Contract $record, string $key): ?string
    {
        $score = app(DossierCompletion::class)->scoreOnglet($record, $key);

        return ($score !== null && $score < 100) ? $score.' %' : null;
    }

    /**
     * Bouton Oui/Non pour un booléen NULLABLE (tri-état) : `null` = non renseigné
     * (aucun bouton actif), sinon Oui/Non. Ponte le cast booléen du modèle et les
     * clés d'option entières (1/0), en préservant `null`.
     */
    private static function ouiNon(string $name, string $label): ToggleButtons
    {
        return ToggleButtons::make($name)
            ->label($label)
            ->options([1 => 'Oui', 0 => 'Non'])
            ->colors([1 => 'success', 0 => 'gray'])
            ->inline()
            ->formatStateUsing(fn ($state) => $state === null ? null : (int) (bool) $state)
            ->dehydrateStateUsing(fn ($state) => ($state === null || $state === '') ? null : (bool) $state);
    }

    /** Utilisateurs du CFA courant (responsables), id => nom. */
    private static function utilisateursCfa(): array
    {
        return Organisation::courante()?->users()->orderBy('name')->pluck('name', 'users.id')->all() ?? [];
    }

    /** Part de la formation à distance (e-learning + classe virtuelle) en %, ou null. */
    private static function partDistance(Get $get): ?float
    {
        $total = (float) $get('duree_formation_heures');

        if ($total <= 0) {
            return null;
        }

        $distance = (float) $get('heures_elearning') + (float) $get('heures_classe_virtuelle');

        return $distance <= 0 ? null : round($distance / $total * 100, 1);
    }

    /** Alerte informative (jamais bloquante) : minoration OPCO possible au-delà de 80 % à distance. */
    private static function alerteDistance(Get $get): HtmlString
    {
        $part = self::partDistance($get);

        if ($part === null) {
            return new HtmlString('');
        }

        $depasse = $part > 80.0;
        $couleur = $depasse ? '#f59e0b' : '#3b82f6';
        $pct = rtrim(rtrim(number_format($part, 1, ',', ' '), '0'), ',');
        $titre = $depasse ? 'Prise en charge OPCO possiblement minorée' : 'Répartition présentiel / distance';
        $corps = $depasse
            ? "La formation est réalisée à <b>{$pct} %</b> à distance. Au-delà de 80 %, la prise en charge de l'OPCO "
                .'peut être réduite de 20 % (décret du 1er juillet 2025).'
            : "La formation est réalisée à <b>{$pct} %</b> à distance.";

        return new HtmlString(
            '<div style="border-left:3px solid '.$couleur.';background:color-mix(in srgb, '.$couleur.' 12%, transparent);'
            .'padding:.7rem .9rem;border-radius:.4rem;font-size:.85rem;line-height:1.35">'
            .'<div style="font-weight:600;margin-bottom:.15rem">'.$titre.'</div>'
            .'<div style="opacity:.85">'.$corps.'</div></div>'
        );
    }

    /**
     * Dates de rémunération : celles du contrat (onglet Contrat) en priorité,
     * sinon celles de la formation (onglet Étudiant) pour les anciens dossiers.
     *
     * @return array{debut: mixed, fin: mixed}
     */
    private static function bornesRemuneration(Get $get): array
    {
        return [
            'debut' => filled($get('date_debut_contrat')) ? $get('date_debut_contrat') : $get('date_debut'),
            'fin' => filled($get('date_fin_contrat')) ? $get('date_fin_contrat') : $get('date_fin'),
        ];
    }

    /** Périodes du barème légal pour l'état courant du formulaire. */
    private static function baremeLegal(Get $get): array
    {
        $candidate = filled($get('candidate_id')) ? Candidate::query()->find($get('candidate_id')) : null;
        $bornes = self::bornesRemuneration($get);

        return RemunerationApprenti::periodes($candidate?->date_naissance, $bornes['debut'], $bornes['fin']);
    }

    /**
     * Signale les autres dirigeants déclarés à l'Annuaire des Entreprises : le
     * représentant pré-rempli est une proposition, pas une certitude, et c'est
     * l'utilisateur qui sait lequel signe réellement le contrat.
     *
     * @param  array<int, array{nom: ?string, prenom: ?string, qualite: ?string}>  $dirigeants
     */
    private static function signalerAutresDirigeants(array $dirigeants): void
    {
        if (count($dirigeants) < 2) {
            return;
        }

        $autres = collect($dirigeants)->skip(1)->take(5)
            ->map(fn (array $d): string => trim(($d['prenom'] ?? '').' '.($d['nom'] ?? ''))
                .($d['qualite'] ? ' — '.$d['qualite'] : ''))
            ->implode(', ');

        Notification::make()
            ->info()
            ->title('Plusieurs dirigeants déclarés')
            ->body('Représentant pré-rempli : le plus à même d\'engager la société. Autres dirigeants : '.$autres.'.')
            ->persistent()
            ->send();
    }

    /** Plancher légal applicable. */
    private static function minimumLegal(Get $get): ?array
    {
        $candidate = filled($get('candidate_id')) ? Candidate::query()->find($get('candidate_id')) : null;
        $bornes = self::bornesRemuneration($get);

        return RemunerationApprenti::minimum($candidate?->date_naissance, $bornes['debut'], $bornes['fin']);
    }

    /** Pré-remplit le salaire avec le minimum légal (sans écraser une saisie). */
    private static function preRemplirSalaire(Set $set, Get $get): void
    {
        if (blank($get('salaire_mensuel_brut')) && ($min = self::minimumLegal($get)) !== null) {
            $set('salaire_mensuel_brut', number_format($min['montant'], 2, '.', ''));
            $set('pourcentage_smic', number_format($min['taux'], 2, '.', ''));
        }
    }

    /** À la saisie des dates du contrat : pré-remplit salaire et nombre de jours. */
    private static function apresDatesContrat(Set $set, Get $get): void
    {
        self::preRemplirSalaire($set, $get);
        self::preRemplirJoursContrat($set, $get);
    }

    /** Nombre de jours calendaires du contrat (bornes incluses), sans écraser une saisie. */
    private static function preRemplirJoursContrat(Set $set, Get $get): void
    {
        if (filled($get('nombre_jours_contrat'))) {
            return;
        }

        $bornes = self::bornesRemuneration($get);

        if (blank($bornes['debut']) || blank($bornes['fin'])) {
            return;
        }

        try {
            $debut = \Carbon\Carbon::parse($bornes['debut'])->startOfDay();
            $fin = \Carbon\Carbon::parse($bornes['fin'])->startOfDay();
        } catch (\Throwable) {
            return;
        }

        if ($fin->gte($debut)) {
            $set('nombre_jours_contrat', (int) $debut->diffInDays($fin) + 1);
        }
    }

    /** Financement OPCO : NPEC journalier (= annuel ÷ 365) puis engagement total. */
    private static function recalculerOpco(Set $set, Get $get): void
    {
        $annuel = (float) $get('npec_annuel');
        $journalier = $annuel > 0 ? round($annuel / 365, 2) : (float) $get('npec_journalier');

        if ($annuel > 0) {
            $set('npec_journalier', number_format($journalier, 2, '.', ''));
        }

        $jours = (int) $get('nombre_jours_contrat');

        if ($journalier > 0 && $jours > 0) {
            $set('engagement_opco_total', number_format(round($journalier * $jours, 2), 2, '.', ''));
        }
    }

    /**
     * Détecte le NPEC dans le référentiel France Compétences à partir du code
     * RNCP (+ IDCC de l'entreprise si disponible) et pré-remplit le financement
     * OPCO. Ne remplace une valeur déjà saisie que sur recalcul explicite
     * ($forcer). Jamais bloquant : message clair si RNCP manquant / NPEC absent.
     */
    private static function detecterNpec(Set $set, Get $get, bool $forcer = false): void
    {
        $rncp = $get('code_rncp');

        if (preg_replace('/\D/', '', (string) $rncp) === '') {
            if ($forcer) {
                Notification::make()->warning()
                    ->title('Impossible de détecter le NPEC')
                    ->body('Le code RNCP de la formation est manquant.')->send();
            }

            return;
        }

        $npec = app(OpcoFundingService::class)->findNpecByIdccAndRncp($get('entreprise_code_idcc'), $rncp);

        if ($npec === null) {
            if ($forcer) {
                Notification::make()->warning()
                    ->title('Aucun NPEC trouvé pour ce RNCP')
                    ->body('Vous pouvez saisir le montant manuellement.')->send();
            }

            return;
        }

        // On préserve une saisie manuelle, sauf demande explicite de recalcul.
        if (! $forcer && filled($get('npec_annuel'))) {
            return;
        }

        $set('npec_annuel', number_format((float) $npec->npec_annuel, 2, '.', ''));
        self::recalculerOpco($set, $get);

        Notification::make()->success()
            ->title('NPEC détecté automatiquement')
            ->body('Niveau de prise en charge : '.number_format((float) $npec->npec_annuel, 2, ',', ' ').' € (source : '.($npec->source ?? 'référentiel').').')
            ->send();
    }

    /** Reste à charge : net = reste à charge + participation obligatoire − participation CFA. */
    private static function recalculerNet(Set $set, Get $get): void
    {
        $net = (float) $get('reste_a_charge_montant')
            + (float) $get('participation_obligatoire')
            - (float) $get('participation_cfa');

        $set('net_a_payer', number_format(round(max(0, $net), 2), 2, '.', ''));
    }

    /** Estimation de la rémunération minimale (carte de synthèse + détail par période). */
    private static function estimationRemunerationHtml(Get $get): HtmlString
    {
        $candidate = filled($get('candidate_id')) ? Candidate::query()->find($get('candidate_id')) : null;
        $min = self::minimumLegal($get);
        $periodes = self::baremeLegal($get);

        if ($min === null || $periodes === []) {
            return new HtmlString(
                '<div style="border-left:3px solid #3b82f6;background:color-mix(in srgb,#3b82f6 10%,transparent);'
                .'padding:.7rem .9rem;border-radius:.4rem;font-size:.85rem">'
                .'Renseignez la <b>date de naissance</b> de l\'apprenti (onglet Étudiant) et les '
                .'<b>dates du contrat</b> : le minimum légal se calcule automatiquement.</div>'
            );
        }

        $premier = $periodes[0];
        $dob = $candidate?->date_naissance;

        $ligne = fn (string $label, string $valeur): string => '<div style="display:flex;justify-content:space-between;gap:1rem">'
            .'<span style="opacity:.7">'.$label.'</span><span style="font-weight:600">'.$valeur.'</span></div>';

        $detail = collect($periodes)->map(fn (array $p): string => sprintf(
            '<li>Du <b>%s</b> au <b>%s</b> · année %d · %d ans → <b>%d %% du SMIC = %s € / mois</b></li>',
            $p['du']->format('d/m/Y'), $p['au']->format('d/m/Y'), $p['annee'], $p['age'], $p['taux'],
            number_format($p['montant'], 2, ',', ' '),
        ))->implode('');

        return new HtmlString(
            '<div style="border:1px solid var(--cfa-card-border,#3f3f46);border-radius:.6rem;overflow:hidden;font-size:.85rem">'
            .'<div style="background:color-mix(in srgb,#22c55e 14%,transparent);padding:.7rem .9rem;display:flex;'
            .'justify-content:space-between;align-items:baseline">'
            .'<span style="font-weight:600">Montant estimé</span>'
            .'<span style="font-size:1.15rem;font-weight:700">'.number_format($min['montant'], 2, ',', ' ').' € / mois</span></div>'
            .'<div style="padding:.7rem .9rem;display:grid;gap:.35rem">'
            .$ligne('Âge au début du contrat', $premier['age'].' ans')
            .$ligne('Date de naissance utilisée', $dob ? $dob->format('d/m/Y') : '—')
            .$ligne('Année de formation', 'Année '.$min['annee'])
            .$ligne('Taux applicable', $min['taux'].' % du SMIC')
            .$ligne('SMIC brut de référence', number_format($min['smic'], 2, ',', ' ').' €')
            .'</div>'
            .'<details style="padding:0 .9rem .7rem"><summary style="cursor:pointer;opacity:.8">Afficher le détail du calcul</summary>'
            .'<ul style="margin:.5rem 0 0;padding-left:1.1rem;display:grid;gap:.3rem">'.$detail.'</ul>'
            .'<p style="margin:.5rem 0 0;font-size:.75rem;opacity:.65">Grille légale (art. D6222-26), plancher SMIC. '
            .'Le SMC de branche peut être plus favorable.</p></details></div>'
        );
    }

    /** @return array<string, string> */
    private static function statutOptions(Contract $record): array
    {
        return collect([$record->statut_contrat, ...$record->allowedTransitions()])
            ->unique()
            ->mapWithKeys(fn (ContractStatut $s): array => [$s->value => $s->getLabel()])
            ->all();
    }
}
