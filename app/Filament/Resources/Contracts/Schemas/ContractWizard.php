<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Enums\ModaliteSuivi;
use App\Enums\TypeContrat;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Promotion;
use App\Support\AdresseBan;
use App\Support\EntrepriseAnnuaire;
use App\Support\OpcoDetector;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Assistant de création d'un dossier contrat en 4 étapes
 * (Étudiant → Formation → Entreprise → Confirmation).
 *
 * Le dossier N'EST PAS créé avant l'étape 4 : le Wizard Filament ne déclenche la
 * soumission (→ {@see \App\Filament\Resources\Contracts\Pages\CreateContract::handleRecordCreation()})
 * qu'au bouton final « Créer le dossier ». Chaque étape valide ses champs avant
 * de laisser avancer ; l'état est conservé quand on revient en arrière.
 *
 * Aucune donnée n'est enregistrée ici : ce schéma ne fait que collecter. La
 * création des entités (candidat, entreprise, contacts, contrat) et les gardes
 * (anti-doublon) sont dans la page.
 */
class ContractWizard
{
    /** @return array<int, Step> */
    public static function steps(): array
    {
        return [
            self::etudiant(),
            self::formation(),
            self::entreprise(),
            self::confirmation(),
        ];
    }

    /* ===============================  Étape 1 — Étudiant  =========================== */

    private static function etudiant(): Step
    {
        return Step::make('Étudiant')
            ->icon(Heroicon::OutlinedUser)
            ->description('L\'apprenant concerné')
            ->columns(2)
            ->schema([
                ToggleButtons::make('mode_dossier')
                    ->label('Type de dossier')
                    ->options(['autonome' => 'Autonome', 'manuel' => 'Manuel'])
                    ->default('autonome')
                    ->inline()
                    ->live()
                    ->helperText('Autonome : l\'ERP tente de retrouver l\'étudiant à partir de son email et de préremplir. '
                        .'Manuel : vous saisissez toutes les informations. Dans les deux cas, la saisie reste modifiable.')
                    ->columnSpanFull(),
                TextInput::make('etudiant_email')
                    ->label('Adresse mail de l\'étudiant')
                    ->email()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::retrouverEtudiant($get, $set))
                    ->helperText('Sert à retrouver un étudiant déjà enregistré (mode Autonome).')
                    ->columnSpanFull(),
                TextInput::make('etudiant_prenom')
                    ->label('Prénom de l\'étudiant')
                    ->required(),
                TextInput::make('etudiant_nom')
                    ->label('Nom de l\'étudiant')
                    ->required(),
                Select::make('formation_id')
                    ->label('Nom de la formation')
                    ->placeholder('Rechercher par nom de formation, option, spécialité, RNCP…')
                    ->options(fn (): array => Formation::query()->orderBy('libelle')->pluck('libelle', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    // Changer de formation invalide la promotion (elle en dépend).
                    ->afterStateUpdated(fn (Set $set) => $set('promotion_id', null)),
                Select::make('promotion_id')
                    ->label('Nom de la promotion')
                    ->options(fn (Get $get): array => blank($get('formation_id'))
                        ? []
                        : Promotion::query()
                            ->where('formation_id', $get('formation_id'))
                            ->get()
                            ->pluck('nom_complet', 'id')
                            ->all())
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn ($state, Get $get, Set $set) => self::heriterDeLaPromotion($state, $get, $set))
                    ->disabled(fn (Get $get): bool => blank($get('formation_id')))
                    ->placeholder(fn (Get $get): string => blank($get('formation_id'))
                        ? 'Choisissez d\'abord une formation'
                        : 'Optionnel')
                    ->helperText('Optionnel. En choisir une préremplit la configuration de l\'étape Formation.'),
            ]);
    }

    /**
     * Mode Autonome : retrouve un candidat déjà enregistré à partir de son email
     * (cloisonné au CFA courant) et préremplit nom / prénom / formation visée.
     * Ne bloque jamais, n'écrase jamais une saisie déjà faite.
     */
    private static function retrouverEtudiant(Get $get, Set $set): void
    {
        if ($get('mode_dossier') !== 'autonome' || blank($get('etudiant_email'))) {
            return;
        }

        $candidate = Candidate::query()->where('email', $get('etudiant_email'))->first();

        if ($candidate === null) {
            return;
        }

        if (blank($get('etudiant_prenom'))) {
            $set('etudiant_prenom', $candidate->prenom);
        }
        if (blank($get('etudiant_nom'))) {
            $set('etudiant_nom', $candidate->nom);
        }
        if (blank($get('formation_id')) && $candidate->formation_visee_id !== null) {
            $set('formation_id', (string) $candidate->formation_visee_id);
        }

        Notification::make()
            ->info()
            ->title('Étudiant retrouvé')
            ->body("« {$candidate->nom_complet} » est déjà enregistré : ses informations ont été préremplies. "
                .'Le dossier sera rattaché à sa fiche existante.')
            ->send();
    }

    /**
     * Héritage : choisir une promotion préremplit la configuration de l'étape
     * Formation (type de contrat, modalité, heures, durée, dates) depuis la
     * session — le contrat gardera sa propre copie. On ne recopie que les
     * valeurs réellement définies sur la promotion.
     */
    private static function heriterDeLaPromotion(mixed $state, Get $get, Set $set): void
    {
        if (blank($state)) {
            return;
        }

        $promotion = Promotion::query()->find($state);

        if ($promotion === null) {
            return;
        }

        $valeurs = [
            'type_contrat' => $promotion->type_contrat?->value,
            'modalite_suivi' => $promotion->modalite_suivi?->value,
            'duree_formation_heures' => $promotion->duree_formation_heures,
            'heures_elearning' => $promotion->heures_elearning,
            'heures_classe_virtuelle' => $promotion->heures_classe_virtuelle,
            'reste_a_charge_zero' => (int) $promotion->reste_a_charge_zero,
            'date_debut' => $promotion->date_debut?->format('Y-m-d'),
            'date_fin' => $promotion->date_fin?->format('Y-m-d'),
        ];

        foreach ($valeurs as $champ => $valeur) {
            if (filled($valeur)) {
                $set($champ, $valeur);
            }
        }

        Notification::make()
            ->info()
            ->title('Configuration héritée de la promotion')
            ->body('L\'étape « Formation » a été préremplie depuis « '.$promotion->nom_complet.' ». '
                .'Vous pouvez l\'ajuster.')
            ->send();
    }

    /* ===============================  Étape 2 — Formation  ========================== */

    private static function formation(): Step
    {
        return Step::make('Formation')
            ->icon(Heroicon::OutlinedAcademicCap)
            ->description('Contenu et dates de la formation')
            ->schema([
                Section::make('Informations sur la formation')
                    ->columns(2)
                    ->schema([
                        TextInput::make('duree_formation_heures')
                            ->label('Durée de la formation')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('heures')
                            ->required()
                            ->live(onBlur: true)
                            ->placeholder('ex : 455'),
                        TextInput::make('nombre_organismes_formation')
                            ->label('Nombre d\'organismes de formation intervenant pour ce contrat')
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                        Select::make('modalite_suivi')
                            ->label('Modalités de suivi')
                            ->options(ModaliteSuivi::class)
                            ->native(false)
                            ->placeholder('Sélectionner…'),
                        ToggleButtons::make('type_contrat')
                            ->label('Type de contrat')
                            ->options(TypeContrat::class)
                            ->default(TypeContrat::Apprentissage->value)
                            ->inline()
                            ->required(),
                        TextInput::make('heures_elearning')
                            ->label('Nombre d\'heures en e-learning')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('heures')
                            ->live(onBlur: true)
                            ->placeholder('ex : 400'),
                        TextInput::make('heures_classe_virtuelle')
                            ->label('Nombre d\'heures en classe virtuelle')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('heures')
                            ->live(onBlur: true)
                            ->placeholder('ex : 55'),
                        TextInput::make('cout_formation')
                            ->label('Prix total de la formation')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->suffix('€')
                            ->required()
                            ->placeholder('ex : 8445'),
                        ToggleButtons::make('reste_a_charge_zero')
                            ->label('Activer le reste à charge à 0 € automatiquement (hors participation obligatoire) ?')
                            ->options([1 => 'Oui', 0 => 'Non'])
                            ->colors([1 => 'success', 0 => 'gray'])
                            ->inline()
                            ->default(0),
                        Placeholder::make('alerte_distance')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => self::alerteDistance($get))
                            ->visible(fn (Get $get): bool => self::partDistance($get) !== null)
                            ->columnSpanFull(),
                    ]),
                Section::make('Dates de formation')
                    ->columns(2)
                    ->schema([
                        Select::make('duree_diplome')
                            ->label('Durée nécessaire à l\'obtention du diplôme')
                            ->options([
                                'jusqu_1_an' => 'Jusqu\'à 1 an',
                                'jusqu_2_ans' => 'Jusqu\'à 2 ans',
                                'jusqu_3_ans' => 'Jusqu\'à 3 ans',
                                'jusqu_4_ans' => 'Jusqu\'à 4 ans',
                            ])
                            ->native(false)
                            ->placeholder('Sélectionner…'),
                        Select::make('annee_cycle')
                            ->label('Année du cycle où l\'étudiant débute')
                            ->options([
                                '1' => '1ère année',
                                '2' => '2ème année',
                                '3' => '3ème année',
                                '4' => '4ème année',
                            ])
                            ->native(false)
                            ->default('1'),
                        DatePicker::make('date_debut')
                            ->label('Date de début de formation')
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('date_fin')
                            ->label('Date de fin de formation')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->afterOrEqual('date_debut'),
                    ]),
            ]);
    }

    /**
     * Part de la formation réalisée à distance (e-learning + classe virtuelle) en
     * pourcentage des heures totales, ou null si le calcul n'est pas possible.
     */
    private static function partDistance(Get $get): ?float
    {
        $total = (float) $get('duree_formation_heures');

        if ($total <= 0) {
            return null;
        }

        $distance = (float) $get('heures_elearning') + (float) $get('heures_classe_virtuelle');

        if ($distance <= 0) {
            return null;
        }

        return round($distance / $total * 100, 1);
    }

    /**
     * Alerte informative (jamais bloquante) : au-delà de 80 % à distance, la
     * prise en charge OPCO peut être minorée (décret du 1er juillet 2025). Le
     * seuil réglementaire exact est à vérifier auprès de l'OPCO ; on se contente
     * de signaler le franchissement de manière factuelle.
     */
    private static function alerteDistance(Get $get): HtmlString
    {
        $part = self::partDistance($get);

        if ($part === null) {
            return new HtmlString('');
        }

        $depasse = $part > 80.0;
        $couleur = $depasse ? '#f59e0b' : '#3b82f6';
        $titre = $depasse
            ? 'Prise en charge OPCO possiblement minorée'
            : 'Répartition présentiel / distance';
        $corps = $depasse
            ? sprintf(
                'La formation est réalisée à <b>%s %%</b> à distance (e-learning + classe virtuelle). '
                .'Au-delà de 80 %%, le montant de prise en charge de l\'OPCO peut être réduit de 20 %% '
                .'(décret du 1er juillet 2025). Vérifiez les règles applicables auprès de l\'OPCO.',
                self::pourcent($part),
            )
            : sprintf('La formation est réalisée à <b>%s %%</b> à distance.', self::pourcent($part));

        return new HtmlString(
            '<div style="border-left:3px solid '.$couleur.';background:color-mix(in srgb, '.$couleur.' 12%, transparent);'
            .'padding:.7rem .9rem;border-radius:.4rem;font-size:.85rem;line-height:1.35">'
            .'<div style="font-weight:600;margin-bottom:.15rem">'.$titre.'</div>'
            .'<div style="opacity:.85">'.$corps.'</div></div>'
        );
    }

    private static function pourcent(float $part): string
    {
        return rtrim(rtrim(number_format($part, 1, ',', ' '), '0'), ',');
    }

    /* ===============================  Étape 3 — Entreprise  ========================= */

    private static function entreprise(): Step
    {
        return Step::make('Entreprise')
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->description('L\'employeur et ses représentants')
            ->schema([
                Section::make('Contact de l\'entreprise')
                    ->description('La personne opérationnelle qui échange avec le CFA (peut différer du représentant légal).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_email')
                            ->label('Adresse email')
                            ->email()
                            ->columnSpanFull(),
                        TextInput::make('contact_prenom')->label('Prénom'),
                        TextInput::make('contact_nom')->label('Nom'),
                    ]),
                Section::make('Informations de l\'entreprise')
                    ->columns(2)
                    ->schema([
                        Hidden::make('entreprise_existante_id'),
                        Select::make('entreprise_recherche')
                            ->label('Rechercher une entreprise')
                            ->placeholder('Tapez la raison sociale ou le SIRET…')
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->getSearchResultsUsing(fn (string $search): array => app(EntrepriseAnnuaire::class)->options($search))
                            ->getOptionLabelUsing(fn ($value): ?string => EntrepriseAnnuaire::decode($value)['label'] ?? null)
                            ->afterStateUpdated(fn ($state, Get $get, Set $set) => self::appliquerFicheEntreprise($state, $get, $set))
                            ->helperText('Annuaire officiel des Entreprises (État) : SIRET et adresse remplis automatiquement. La saisie manuelle reste possible.')
                            ->columnSpanFull(),
                        TextInput::make('entreprise_siret')
                            ->label('Numéro de SIRET de l\'entreprise')
                            ->placeholder('ex : 123 456 789 00012')
                            ->required()
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(fn (?string $state): string => OpcoDetector::normaliserSiret($state))
                            ->afterStateUpdated(fn ($state, Get $get, Set $set) => self::detecterEntrepriseExistante($state, $get, $set))
                            ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                if (! OpcoDetector::siretValide($value)) {
                                    $fail('Le SIRET doit comporter exactement 14 chiffres.');
                                }
                            })
                            ->helperText('14 chiffres. Le SIREN s\'en déduit automatiquement.'),
                        TextInput::make('entreprise_raison_sociale')
                            ->label('Nom légal de l\'entreprise')
                            ->required(),
                        TextInput::make('entreprise_nom_commercial')
                            ->label('Nom commercial de l\'entreprise'),
                        TextInput::make('entreprise_forme_juridique')
                            ->label('Forme juridique de l\'entreprise')
                            ->placeholder('ex : SARL, SAS, EI…'),
                        TextInput::make('entreprise_siren')
                            ->label('SIREN de l\'entreprise')
                            ->placeholder('9 chiffres')
                            ->helperText('Déduit du SIRET.'),
                        TextInput::make('entreprise_siret_etablissement')
                            ->label('SIRET de l\'établissement')
                            ->placeholder('Si l\'établissement d\'exécution diffère du siège')
                            ->helperText('Laisser vide si identique au SIRET du siège.'),
                        TextInput::make('entreprise_ville_rcs')
                            ->label('Ville RCS de l\'entreprise')
                            ->placeholder('ex : Lyon'),
                        TextInput::make('entreprise_numero_siege')
                            ->label('Numéro du siège')
                            ->placeholder('ex : 15'),
                        TextInput::make('entreprise_adresse')
                            ->label('Adresse du siège social')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('entreprise_complement_adresse')
                            ->label('Complément d\'adresse')
                            ->columnSpanFull(),
                        TextInput::make('entreprise_code_postal')
                            ->label('Code postal')
                            ->required(),
                        TextInput::make('entreprise_ville')
                            ->label('Ville')
                            ->required(),
                        // Récupérés depuis l'Annuaire des Entreprises et conservés
                        // jusqu'à la création : ils se complètent dans l'onglet
                        // Entreprise du dossier, inutile de les ressaisir ici.
                        Hidden::make('entreprise_code_ape_naf'),
                        Hidden::make('entreprise_code_idcc'),
                    ]),
                Section::make('Représentant légal de l\'entreprise')
                    ->description('Signataire du contrat et de la convention côté employeur.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('representant_prenom')->label('Prénom')->required(),
                        TextInput::make('representant_nom')->label('Nom')->required(),
                        TextInput::make('representant_email')->label('Adresse email')->email()->required(),
                        TextInput::make('representant_poste')->label('Poste occupé')->required(),
                    ]),
            ]);
    }

    /**
     * Applique une fiche de l'Annuaire des Entreprises au formulaire : raison
     * sociale, SIRET, adresse structurée, puis SIREN déduit et détection d'une
     * entreprise déjà enregistrée pour ce SIRET dans le CFA.
     */
    private static function appliquerFicheEntreprise(?string $state, Get $get, Set $set): void
    {
        $fiche = EntrepriseAnnuaire::decode($state);

        if ($fiche === null) {
            return;
        }

        $set('entreprise_raison_sociale', $fiche['raison_sociale'] ?? null);
        $set('entreprise_nom_commercial', $fiche['nom_commercial'] ?? null);
        $set('entreprise_forme_juridique', $fiche['forme_juridique'] ?? null);
        $set('entreprise_code_ape_naf', $fiche['code_ape_naf'] ?? null);
        $set('entreprise_code_idcc', $fiche['code_idcc'] ?? null);
        $set('entreprise_siret', $fiche['siret'] ?? null);
        $set('entreprise_numero_siege', $fiche['numero'] ?? null);
        $set('entreprise_adresse', $fiche['adresse'] ?? null);
        $set('entreprise_complement_adresse', $fiche['complement_adresse'] ?? null);
        $set('entreprise_code_postal', $fiche['code_postal'] ?? null);
        $set('entreprise_ville', $fiche['ville'] ?? null);
        $set('entreprise_siren', self::sirenDepuisSiret($fiche['siret'] ?? null));

        self::detecterEntrepriseExistante($fiche['siret'] ?? null, $get, $set);
    }

    /**
     * Détecte une entreprise déjà enregistrée dans le CFA pour ce SIRET : mémorise
     * son id (pour réutiliser la fiche au lieu d'en créer une seconde) et
     * préremplit les champs encore vides depuis la fiche existante. Déduit aussi
     * le SIREN du SIRET saisi. Ne bloque jamais.
     */
    private static function detecterEntrepriseExistante(?string $siret, Get $get, Set $set): void
    {
        $siret = OpcoDetector::normaliserSiret($siret);

        if (blank($get('entreprise_siren')) && OpcoDetector::siretValide($siret)) {
            $set('entreprise_siren', self::sirenDepuisSiret($siret));
        }

        if (! OpcoDetector::siretValide($siret)) {
            return;
        }

        $existante = Company::withTrashed()->where('siret', $siret)->first();

        if ($existante === null) {
            $set('entreprise_existante_id', null);

            return;
        }

        $set('entreprise_existante_id', (string) $existante->id);

        // Préremplissage doux : on ne touche qu'aux champs restés vides.
        $prefill = [
            'entreprise_raison_sociale' => $existante->raison_sociale,
            'entreprise_nom_commercial' => $existante->nom_commercial,
            'entreprise_forme_juridique' => $existante->forme_juridique,
            'entreprise_siren' => $existante->siren ?: self::sirenDepuisSiret($siret),
            'entreprise_siret_etablissement' => $existante->siret_etablissement,
            'entreprise_ville_rcs' => $existante->ville_rcs,
            'entreprise_numero_siege' => $existante->numero_siege,
            'entreprise_adresse' => $existante->adresse,
            'entreprise_complement_adresse' => $existante->complement_adresse,
            'entreprise_code_postal' => $existante->code_postal,
            'entreprise_ville' => $existante->ville,
        ];

        foreach ($prefill as $champ => $valeur) {
            if (blank($get($champ)) && filled($valeur)) {
                $set($champ, $valeur);
            }
        }

        Notification::make()
            ->info()
            ->title('Entreprise déjà connue')
            ->body("« {$existante->raison_sociale} » est déjà enregistrée dans ce CFA"
                .($existante->trashed() ? ' (en corbeille — elle sera restaurée)' : '')
                .' : le dossier sera rattaché à sa fiche existante.')
            ->send();
    }

    /** SIREN (9 chiffres) déduit d'un SIRET normalisé, ou null. */
    public static function sirenDepuisSiret(?string $siret): ?string
    {
        $siret = OpcoDetector::normaliserSiret($siret);

        return OpcoDetector::siretValide($siret) ? substr($siret, 0, 9) : null;
    }

    /* ===============================  Étape 4 — Confirmation  ======================= */

    private static function confirmation(): Step
    {
        return Step::make('Confirmation')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->description('Vérifiez avant de créer le dossier')
            ->schema([
                Placeholder::make('recapitulatif')
                    ->hiddenLabel()
                    // On lit l'état complet du formulaire ($livewire->data) plutôt
                    // que via Get() : le récap agrège des champs répartis sur les
                    // trois étapes précédentes (containers différents).
                    ->content(fn ($livewire): HtmlString => new HtmlString(
                        view('filament.contracts.wizard-recap', [
                            'd' => $livewire->data ?? [],
                            'checklist' => self::checklist($livewire->data ?? []),
                        ])->render(),
                    )),
            ]);
    }

    /**
     * Checklist de complétude affichée à l'étape 4 : distingue ce qui est prêt de
     * ce qui manque encore (informatif, non bloquant — les champs obligatoires
     * sont déjà garantis par la validation de chaque étape).
     *
     * @param  array<string, mixed>  $d
     * @return array<int, array{label: string, ok: bool, hint: ?string}>
     */
    public static function checklist(array $d): array
    {
        $rempli = fn (string $cle): bool => filled($d[$cle] ?? null);

        $datesCoherentes = $rempli('date_debut') && $rempli('date_fin')
            && strtotime((string) $d['date_fin']) >= strtotime((string) $d['date_debut']);

        return [
            ['label' => 'Étudiant identifié (nom, prénom, email)', 'ok' => $rempli('etudiant_nom') && $rempli('etudiant_prenom') && $rempli('etudiant_email'), 'hint' => null],
            ['label' => 'Formation sélectionnée', 'ok' => $rempli('formation_id'), 'hint' => null],
            ['label' => 'Durée et prix de la formation renseignés', 'ok' => $rempli('duree_formation_heures') && $rempli('cout_formation'), 'hint' => null],
            ['label' => 'Dates de formation cohérentes', 'ok' => $datesCoherentes, 'hint' => $datesCoherentes ? null : 'La date de fin doit suivre la date de début.'],
            ['label' => 'Entreprise identifiée (SIRET, raison sociale, adresse)', 'ok' => $rempli('entreprise_siret') && $rempli('entreprise_raison_sociale') && $rempli('entreprise_adresse'), 'hint' => null],
            ['label' => 'Représentant légal renseigné', 'ok' => $rempli('representant_nom') && $rempli('representant_email'), 'hint' => null],
            ['label' => 'Forme juridique et ville RCS (utiles au CERFA/convention)', 'ok' => $rempli('entreprise_forme_juridique') && $rempli('entreprise_ville_rcs'), 'hint' => 'Optionnel maintenant, complétable ensuite sur la fiche du dossier.'],
        ];
    }
}
