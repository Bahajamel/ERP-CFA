<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\CompanyStatut;
use App\Models\Company;
use App\Support\AdresseBan;
use App\Support\EntrepriseAnnuaire;
use App\Support\OpcoDetector;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CompanyForm
{
    /**
     * Cherche une entreprise DÉJÀ enregistrée au même SIRET dans le CFA courant,
     * corbeille comprise (withTrashed). Le OrganisationScope reste actif : la
     * recherche est cloisonnée au CFA. `$ignorerId` exclut l'enregistrement en
     * cours d'édition pour ne pas se détecter soi-même.
     */
    private static function entrepriseExistante(?string $siret, ?int $ignorerId = null): ?Company
    {
        $siret = OpcoDetector::normaliserSiret($siret);

        if (! OpcoDetector::siretValide($siret)) {
            return null;
        }

        return Company::withTrashed()
            ->where('siret', $siret)
            ->when($ignorerId !== null, fn ($query) => $query->whereKeyNot($ignorerId))
            ->first();
    }

    /**
     * Avertit (sans bloquer la saisie, mais avec un message ferme et persistant)
     * qu'une entreprise au même SIRET existe déjà — active ou en corbeille. La
     * création reste interdite au moment de l'enregistrement ({@see configure}) :
     * ce message ne fait qu'anticiper le blocage dès la sélection/saisie.
     */
    private static function avertirSiEntrepriseExiste(?string $siret, ?Model $record): void
    {
        $existante = self::entrepriseExistante($siret, $record?->getKey());

        if ($existante === null) {
            return;
        }

        Notification::make()
            ->danger()
            ->persistent()
            ->title('Cette entreprise existe déjà')
            ->body(
                "« {$existante->raison_sociale} » (SIRET {$existante->siret}) est déjà enregistrée"
                .($existante->trashed()
                    ? " dans la corbeille. Restaurez-la depuis la corbeille au lieu d'en créer une nouvelle."
                    : ". Ouvrez sa fiche au lieu d'en créer une nouvelle.")
            )
            ->send();
    }

    /**
     * Détecte l'OPCO depuis un SIRET (France Compétences), pré-remplit le
     * champ et notifie l'utilisateur. Partagé entre la recherche d'entreprise
     * et la saisie directe du SIRET ; n'écrase jamais un choix manuel en cas
     * d'échec et ne bloque jamais la création.
     */
    private static function detecterEtNotifierOpco(?string $siret, Set $set, Get $get): void
    {
        if (! OpcoDetector::siretValide($siret)) {
            return;
        }

        $resultat = app(OpcoDetector::class)->detecter($siret);

        if ($resultat['statut'] === 'ok') {
            $set('opco_id', $resultat['opco']->id);
            Notification::make()
                ->success()
                ->title('OPCO détecté automatiquement')
                ->body("« {$resultat['nom']} » a été identifié à partir du SIRET (France Compétences).")
                ->send();

            return;
        }

        if ($resultat['statut'] === 'indisponible') {
            Notification::make()
                ->warning()
                ->title('Détection OPCO indisponible')
                ->body('La détection automatique de l\'OPCO est temporairement indisponible. Vous pouvez le sélectionner manuellement.')
                ->send();

            return;
        }

        if (blank($get('opco_id'))) {
            Notification::make()
                ->info()
                ->title('Aucun OPCO détecté')
                ->body('Aucun OPCO trouvé pour ce SIRET. Vous pouvez le sélectionner manuellement.')
                ->send();
        }
    }

    /**
     * F-09 — Alerte « établissement fermé » : prévient (sans jamais bloquer
     * la saisie) qu'un établissement administrativement fermé ou une
     * entreprise cessée ne peut pas accueillir d'apprenti.
     *
     * @param  array{ferme: bool, date_fermeture: ?string}|null  $etat
     */
    private static function notifierSiEtablissementFerme(?array $etat): void
    {
        if ($etat === null || ! $etat['ferme']) {
            return;
        }

        $depuis = filled($etat['date_fermeture'] ?? null)
            ? ' depuis le '.\Carbon\Carbon::parse($etat['date_fermeture'])->format('d/m/Y')
            : '';

        Notification::make()
            ->danger()
            ->persistent()
            ->title('⚠️ Établissement administrativement fermé')
            ->body("Cet établissement est signalé fermé{$depuis} par l'Annuaire des Entreprises (INSEE). "
                .'Vérifiez la situation de l\'entreprise avant toute convention ou contrat d\'apprentissage.')
            ->send();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Deux colonnes équilibrées (pas de trous entre cartes).
            ->columns(2)
            ->components([
                Group::make([
                    Section::make('Entreprise')
                        ->icon('heroicon-o-building-office-2')
                        ->columns(2)
                        ->schema([
                            Select::make('entreprise_recherche')
                                ->label('Rechercher une entreprise')
                                ->placeholder('Tapez la raison sociale ou le SIRET…')
                                ->searchable()
                                ->live()
                                ->dehydrated(false)
                                ->getSearchResultsUsing(fn (string $search): array => app(EntrepriseAnnuaire::class)->options($search))
                                ->getOptionLabelUsing(fn ($value): ?string => EntrepriseAnnuaire::decode($value)['label'] ?? null)
                                ->afterStateUpdated(function ($state, Set $set, Get $get, ?Model $record): void {
                                    $fiche = EntrepriseAnnuaire::decode($state);

                                    if ($fiche === null) {
                                        return;
                                    }

                                    $set('raison_sociale', $fiche['raison_sociale']);
                                    $set('nom_commercial', $fiche['nom_commercial'] ?? null);
                                    $set('siret', $fiche['siret']);
                                    $set('siren', $fiche['siren'] ?? null);
                                    $set('forme_juridique', $fiche['forme_juridique'] ?? null);
                                    $set('code_ape_naf', $fiche['code_ape_naf'] ?? null);
                                    $set('code_idcc', $fiche['code_idcc'] ?? null);
                                    $set('secteur', $fiche['secteur'] ?? null);
                                    $set('numero_siege', $fiche['numero'] ?? null);
                                    $set('adresse', $fiche['adresse']);
                                    $set('complement_adresse', $fiche['complement_adresse'] ?? null);
                                    $set('code_postal', $fiche['code_postal']);
                                    $set('ville', $fiche['ville']);
                                    $set('pays', $fiche['pays'] ?? 'France');
                                    $set('latitude', $fiche['latitude']);
                                    $set('longitude', $fiche['longitude']);

                                    // Doublon d'abord : prévient dès la sélection qu'une fiche
                                    // existe déjà pour ce SIRET (la création sera refusée).
                                    self::avertirSiEntrepriseExiste($fiche['siret'], $record);

                                    // Puis détection de l'OPCO depuis le SIRET, et alerte si
                                    // l'établissement est fermé (F-09).
                                    self::detecterEtNotifierOpco($fiche['siret'], $set, $get);
                                    self::notifierSiEtablissementFerme([
                                        'ferme' => EntrepriseAnnuaire::ferme($fiche),
                                        'date_fermeture' => $fiche['date_fermeture'] ?? null,
                                    ]);
                                })
                                ->helperText('Annuaire officiel des Entreprises (État) : SIRET, adresse et OPCO remplis automatiquement. La saisie manuelle reste possible.')
                                ->columnSpanFull(),
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
                                ->helperText('14 chiffres — l\'OPCO est détecté automatiquement (France Compétences).')
                                ->required()
                                ->live(onBlur: true)
                                // Normalisation : le SIRET est stocké sans espaces.
                                ->dehydrateStateUsing(fn (?string $state): string => OpcoDetector::normaliserSiret($state))
                                ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                    if (! OpcoDetector::siretValide($value)) {
                                        $fail('Le SIRET doit comporter exactement 14 chiffres.');
                                    }
                                })
                                // Interdiction du doublon *au sein du CFA*. Deux volets :
                                //  1. scopedUnique() couvre les entreprises ACTIVES (requête via
                                //     le modèle → cloisonnement CFA appliqué).
                                //  2. la règle onlyTrashed() ci-dessous couvre la CORBEILLE, que
                                //     scopedUnique ignore (le scope SoftDeletes l'exclut) — sans
                                //     elle, recréer une entreprise archivée passe la validation
                                //     puis heurte l'index SQL (organisation_id, siret) → erreur 500.
                                //     onlyTrashed exclut naturellement l'enregistrement courant
                                //     (non archivé), donc pas besoin d'ignorer le record en édition.
                                // Un même employeur peut exister chez plusieurs CFA : le
                                // cloisonnement garantit qu'on ne bloque que dans CE centre.
                                ->scopedUnique(ignoreRecord: true)
                                ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                    $siret = OpcoDetector::normaliserSiret($value);

                                    if (OpcoDetector::siretValide($siret)
                                        && Company::onlyTrashed()->where('siret', $siret)->exists()) {
                                        $fail("Cette entreprise est déjà enregistrée dans la corbeille de ce CFA. Restaurez-la depuis la corbeille au lieu d'en créer une nouvelle.");
                                    }
                                })
                                ->validationMessages([
                                    'unique' => "Cette entreprise (SIRET) est déjà enregistrée pour ce CFA. Ouvrez la fiche existante au lieu d'en créer une nouvelle.",
                                ])
                                // Doublon signalé dès le blur, puis détection OPCO et contrôle
                                // de l'état administratif (F-09).
                                ->afterStateUpdated(function (?string $state, Set $set, Get $get, ?Model $record): void {
                                    self::avertirSiEntrepriseExiste($state, $record);
                                    self::detecterEtNotifierOpco($state, $set, $get);

                                    if (OpcoDetector::siretValide($state)) {
                                        self::notifierSiEtablissementFerme(app(EntrepriseAnnuaire::class)->etatSiret($state));
                                    }
                                }),
                            TextInput::make('secteur')
                                ->label("Secteur d'activité")
                                ->placeholder('ex : Restauration, BTP, Informatique'),
                            // Identité légale récupérée depuis l'Annuaire des Entreprises :
                            // sans ces champs, les valeurs remontées par la recherche SIRET
                            // ne seraient ni affichées ni enregistrées.
                            TextInput::make('siren')
                                ->label('SIREN')
                                ->placeholder('ex : 123 456 789')
                                ->helperText('9 premiers chiffres du SIRET.'),
                            TextInput::make('forme_juridique')
                                ->label('Forme juridique')
                                ->placeholder('ex : SAS, société par actions simplifiée'),
                            TextInput::make('code_ape_naf')
                                ->label('Code APE / NAF')
                                ->placeholder('ex : 62.01Z')
                                ->live(onBlur: true)
                                ->helperText(fn (Get $get): ?string => EntrepriseAnnuaire::libelleNaf($get('code_ape_naf'))),
                            TextInput::make('code_idcc')
                                ->label('IDCC (convention collective)')
                                ->placeholder('ex : 1486')
                                ->helperText('Identifiant de la branche : sert au calcul du NPEC.'),
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
                ])->columnSpan(1),
                Group::make([
                    Section::make('Adresse')
                        ->icon('heroicon-o-map-pin')
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
                            TextInput::make('numero_siege')
                                ->label('Numéro du siège')
                                ->placeholder('ex : 228')
                                ->helperText('Case « N° » du CERFA, séparée de la voie.'),
                            TextInput::make('complement_adresse')
                                ->label('Complément d\'adresse')
                                ->placeholder('ex : Bâtiment B, 3e étage'),
                            TextInput::make('code_postal')
                                ->label('Code postal')
                                ->placeholder('ex : 69003'),
                            TextInput::make('ville')
                                ->label('Ville')
                                ->placeholder('ex : Lyon'),
                            TextInput::make('pays')
                                ->label('Pays')
                                ->default('France'),
                            // Coordonnées GPS : renseignées silencieusement par la
                            // recherche d'adresse (pas de saisie manuelle demandée).
                            Hidden::make('latitude'),
                            Hidden::make('longitude'),
                        ]),
                ])->columnSpan(1),
            ]);
    }
}
