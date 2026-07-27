<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Enums\ModaliteSuivi;
use App\Enums\TypeContrat;
use App\Models\Formation;
use App\Models\Organisation;
use App\Support\AdresseBan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Assistant de création d'une promotion (session de formation) en 3 étapes
 * (Promotion → Informations → Confirmation), inspiré du parcours Filiz mais avec
 * une vraie étape de confirmation avant création.
 *
 * La promotion sert de MODÈLE au contrat : la configuration saisie ici
 * (type de contrat, modalité, heures, dates, frais) sera héritée à la création
 * d'un contrat rattaché à cette promotion.
 *
 * On CONSERVE la logique de cohorte : `libelle` = niveau (1ère année…), utilisé
 * par l'émargement et les notes. Les apprenants ne sont PAS composés ici : ils
 * sont rattachés ensuite (fiche de la classe / relation « Apprenants »).
 */
class PromotionWizard
{
    /** @return array<int, Step> */
    public static function steps(): array
    {
        return [
            self::promotion(),
            self::informations(),
            self::confirmation(),
        ];
    }

    /** Niveaux (année du cycle) proposés, valeur = libellé stocké (cohorte). */
    public const NIVEAUX = [
        '1ère année' => '1ère année',
        '2ème année' => '2ème année',
        '3ème année' => '3ème année',
        '4ème année' => '4ème année',
    ];

    /* ==============================  Étape 1 — Promotion  =========================== */

    private static function promotion(): Step
    {
        return Step::make('Promotion')
            ->icon(Heroicon::OutlinedUserGroup)
            ->description('Identité de la session')
            ->schema([
                Section::make('La promotion')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nom')
                            ->label('Nom de la promotion')
                            ->placeholder('ex : TP EPR E-LEARNING 12 MOIS JUIN 2026')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('formation_id')
                            ->label('Nom de la formation')
                            ->placeholder('Rechercher par nom de formation, option, spécialité, RNCP…')
                            ->options(fn (): array => Formation::query()->orderBy('libelle')->pluck('libelle', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('libelle')
                            ->label('Niveau (année du cycle)')
                            ->options(self::NIVEAUX)
                            ->default('1ère année')
                            ->required()
                            ->helperText('Détermine la cohorte (émargement et notes par niveau).'),
                        Select::make('responsable_id')
                            ->label('Responsable pédagogique')
                            ->options(fn (): array => Organisation::courante()
                                ?->users()->orderBy('name')->pluck('name', 'users.id')->all() ?? [])
                            ->searchable()
                            ->preload()
                            ->placeholder('Sélectionner…'),
                        TextInput::make('annee_scolaire')
                            ->label('Année scolaire')
                            ->placeholder('ex : 2025-2026')
                            ->maxLength(20),
                    ]),
                Section::make('Lieu de formation')
                    ->description('Adresse intelligente (Base Adresse Nationale) : recherchez, les champs se complètent. Saisie manuelle possible.')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->schema([
                        Select::make('lieu_recherche')
                            ->label('Adresse')
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

                                $set('lieu_formation_numero', $data['numero']);
                                $set('lieu_formation', $data['voie'] ?? $data['adresse'] ?? $data['label']);
                                $set('lieu_formation_code_postal', $data['code_postal']);
                                $set('lieu_formation_ville', $data['ville']);
                                $set('lieu_formation_pays', $data['pays'] ?? 'France');
                                $set('lieu_formation_latitude', $data['latitude']);
                                $set('lieu_formation_longitude', $data['longitude']);
                            })
                            ->columnSpanFull(),
                        TextInput::make('lieu_formation_numero')->label('Numéro'),
                        TextInput::make('lieu_formation')->label('Rue'),
                        TextInput::make('lieu_formation_complement')
                            ->label('Complément d\'adresse')
                            ->columnSpanFull(),
                        TextInput::make('lieu_formation_code_postal')->label('Code postal'),
                        TextInput::make('lieu_formation_ville')->label('Ville'),
                        TextInput::make('lieu_formation_pays')->label('Pays')->default('France'),
                        Hidden::make('lieu_formation_latitude'),
                        Hidden::make('lieu_formation_longitude'),
                    ]),
            ]);
    }

    /* ============================  Étape 2 — Informations  ========================== */

    private static function informations(): Step
    {
        return Step::make('Informations')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->description('Configuration de la session')
            ->schema([
                Section::make('Configuration pédagogique')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('type_contrat')
                            ->label('Type de contrat')
                            ->options(TypeContrat::class)
                            ->default(TypeContrat::Apprentissage->value)
                            ->inline()
                            ->required()
                            ->columnSpanFull(),
                        Select::make('modalite_suivi')
                            ->label('Modalités de suivi')
                            ->options(ModaliteSuivi::class)
                            ->native(false)
                            ->placeholder('Sélectionner…')
                            ->columnSpanFull(),
                        Placeholder::make('alerte_distance')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => self::alerteDistance($get))
                            ->visible(fn (Get $get): bool => self::partDistance($get) !== null)
                            ->columnSpanFull(),
                        TextInput::make('duree_formation_heures')
                            ->label('Durée de la formation')
                            ->numeric()->minValue(0)->suffix('heures')
                            ->live(onBlur: true)
                            ->placeholder('ex : 455'),
                        TextInput::make('heures_elearning')
                            ->label('Nombre d\'heures en e-learning')
                            ->numeric()->minValue(0)->suffix('heures')
                            ->live(onBlur: true)
                            ->placeholder('ex : 400'),
                        TextInput::make('heures_classe_virtuelle')
                            ->label('Nombre d\'heures en classe virtuelle')
                            ->numeric()->minValue(0)->suffix('heures')
                            ->live(onBlur: true)
                            ->placeholder('ex : 55'),
                        ToggleButtons::make('reste_a_charge_zero')
                            ->label('Activer le reste à charge à 0 € automatiquement (hors participation obligatoire) ?')
                            ->options([1 => 'Oui', 0 => 'Non'])
                            ->colors([1 => 'success', 0 => 'gray'])
                            ->inline()
                            ->default(0),
                        DatePicker::make('date_debut')
                            ->label('Date de début de l\'année')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('date_fin')
                            ->label('Date de fin de l\'année')
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('date_debut'),
                    ]),
                Section::make('Frais annexes')
                    ->description('Prestations annexes finançables par l\'OPCO, applicables aux contrats de cette promotion.')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('frais_hebergement')
                            ->label('Frais d\'hébergement ? (6 €/nuit)')
                            ->options([1 => 'Oui', 0 => 'Non'])->colors([1 => 'success', 0 => 'gray'])
                            ->inline()->default(0),
                        ToggleButtons::make('frais_restauration')
                            ->label('Frais de restauration ? (3 €/repas)')
                            ->options([1 => 'Oui', 0 => 'Non'])->colors([1 => 'success', 0 => 'gray'])
                            ->inline()->default(0),
                        ToggleButtons::make('frais_equipement')
                            ->label('Frais de premier équipement pédagogique ? (500 €)')
                            ->options([1 => 'Oui', 0 => 'Non'])->colors([1 => 'success', 0 => 'gray'])
                            ->inline()->default(0)->live(),
                        ToggleButtons::make('frais_mobilite')
                            ->label('Frais de mobilité internationale ?')
                            ->options([1 => 'Oui', 0 => 'Non'])->colors([1 => 'success', 0 => 'gray'])
                            ->inline()->default(0),
                        Select::make('type_equipement')
                            ->label('Type de premier équipement pédagogique')
                            ->options([
                                'informatique' => 'Équipement informatique mis à disposition de l\'apprenti',
                                'outillage' => 'Outillage / matériel professionnel',
                                'autre' => 'Autre équipement',
                            ])
                            ->native(false)
                            ->visible(fn (Get $get): bool => (bool) $get('frais_equipement'))
                            ->required(fn (Get $get): bool => (bool) $get('frais_equipement'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Part à distance (e-learning + classe virtuelle) en %, ou null. */
    private static function partDistance(Get $get): ?float
    {
        $total = (float) $get('duree_formation_heures');

        if ($total <= 0) {
            return null;
        }

        $distance = (float) $get('heures_elearning') + (float) $get('heures_classe_virtuelle');

        return $distance <= 0 ? null : round($distance / $total * 100, 1);
    }

    private static function alerteDistance(Get $get): HtmlString
    {
        $part = self::partDistance($get);

        if ($part === null) {
            return new HtmlString('');
        }

        $depasse = $part > 80.0;
        $couleur = $depasse ? '#f59e0b' : '#3b82f6';
        $titre = $depasse ? 'Prise en charge OPCO possiblement minorée' : 'Répartition présentiel / distance';
        $pct = rtrim(rtrim(number_format($part, 1, ',', ' '), '0'), ',');
        $corps = $depasse
            ? "La formation est réalisée à <b>{$pct} %</b> à distance. Au-delà de 80 %, le montant de prise en "
                .'charge de l\'OPCO peut être réduit de 20 % (décret du 1er juillet 2025). Vérifiez les règles applicables.'
            : "La formation est réalisée à <b>{$pct} %</b> à distance.";

        return new HtmlString(
            '<div style="border-left:3px solid '.$couleur.';background:color-mix(in srgb, '.$couleur.' 12%, transparent);'
            .'padding:.7rem .9rem;border-radius:.4rem;font-size:.85rem;line-height:1.35">'
            .'<div style="font-weight:600;margin-bottom:.15rem">'.$titre.'</div>'
            .'<div style="opacity:.85">'.$corps.'</div></div>'
        );
    }

    /* ============================  Étape 3 — Confirmation  ========================== */

    private static function confirmation(): Step
    {
        return Step::make('Confirmation')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->description('Vérifiez avant de créer la promotion')
            ->schema([
                Placeholder::make('recapitulatif')
                    ->hiddenLabel()
                    ->content(fn ($livewire): HtmlString => new HtmlString(
                        view('filament.promotions.wizard-recap', ['d' => $livewire->data ?? []])->render(),
                    )),
            ]);
    }
}
