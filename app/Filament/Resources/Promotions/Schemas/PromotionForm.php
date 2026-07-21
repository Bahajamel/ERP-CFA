<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Enums\ModaliteSuivi;
use App\Enums\TypeContrat;
use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Organisation;
use App\Models\Promotion;
use App\Support\AdresseBan;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Classe / promotion')
                    ->columns(2)
                    ->schema([
                        // Une seule liste : chaque formation déclinée par année (selon sa durée).
                        // Choisir « CDA — 2ème année » remplit la formation ET le libellé.
                        Select::make('formation_annee')
                            ->label('Formation & année')
                            ->options(fn (?Promotion $record): array => static::optionsFormationAnnee($record))
                            ->searchable()
                            ->live()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Select $component, ?Promotion $record): void {
                                if ($record?->formation_id && preg_match('/^(\d+)/u', (string) $record->libelle, $m)) {
                                    $component->state($record->formation_id.'|'.$m[1]);
                                }
                            })
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (blank($state)) {
                                    return;
                                }
                                [$formationId, $annee] = explode('|', $state);
                                $set('formation_id', (int) $formationId);
                                $set('libelle', static::libelleAnnee((int) $annee));
                            }),
                        Hidden::make('formation_id')
                            ->required(),
                        Hidden::make('libelle')
                            ->required(),
                        TextInput::make('nom')
                            ->label('Nom de la promotion (session)')
                            ->placeholder('ex : TP EPR E-LEARNING 12 MOIS JUIN 2026')
                            ->helperText('Nom libre de la session mensuelle. Laissez vide pour une cohorte simple.')
                            ->columnSpanFull(),
                        Select::make('responsable_id')
                            ->label('Responsable pédagogique')
                            ->options(fn (): array => Organisation::courante()
                                ?->users()->orderBy('name')->pluck('name', 'users.id')->all() ?? [])
                            ->searchable()
                            ->preload(),
                        TextInput::make('annee_scolaire')
                            ->label('Année scolaire')
                            ->placeholder('Ex. 2025-2026')
                            ->maxLength(20),
                        DatePicker::make('date_debut')
                            ->label('Début')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('date_fin')
                            ->label('Fin')
                            ->displayFormat('d/m/Y'),
                    ]),

                Section::make('Lieu de formation')
                    ->description('Adresse intelligente (Base Adresse Nationale) ou saisie manuelle.')
                    ->icon('heroicon-o-map-pin')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        Select::make('lieu_recherche')
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
                        TextInput::make('lieu_formation_complement')->label('Complément d\'adresse')->columnSpanFull(),
                        TextInput::make('lieu_formation_code_postal')->label('Code postal'),
                        TextInput::make('lieu_formation_ville')->label('Ville'),
                        TextInput::make('lieu_formation_pays')->label('Pays')->default('France'),
                        Hidden::make('lieu_formation_latitude'),
                        Hidden::make('lieu_formation_longitude'),
                    ]),

                Section::make('Configuration (modèle pour les contrats)')
                    ->description('Valeurs héritées par défaut à la création d\'un contrat rattaché à cette promotion.')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('type_contrat')
                            ->label('Type de contrat')
                            ->options(TypeContrat::class)
                            ->inline()
                            ->columnSpanFull(),
                        Select::make('modalite_suivi')
                            ->label('Modalités de suivi')
                            ->options(ModaliteSuivi::class)
                            ->native(false),
                        TextInput::make('duree_formation_heures')
                            ->label('Durée de la formation')->numeric()->minValue(0)->suffix('heures'),
                        TextInput::make('heures_elearning')
                            ->label('Heures e-learning')->numeric()->minValue(0)->suffix('heures'),
                        TextInput::make('heures_classe_virtuelle')
                            ->label('Heures classe virtuelle')->numeric()->minValue(0)->suffix('heures'),
                        static::ouiNon('reste_a_charge_zero', 'Reste à charge à 0 € automatique ?'),
                    ]),

                Section::make('Frais annexes')
                    ->description('Prestations annexes finançables par l\'OPCO.')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        static::ouiNon('frais_hebergement', 'Hébergement (6 €/nuit)'),
                        static::ouiNon('frais_restauration', 'Restauration (3 €/repas)'),
                        static::ouiNon('frais_equipement', 'Premier équipement pédagogique (500 €)')->live(),
                        static::ouiNon('frais_mobilite', 'Mobilité internationale'),
                        Select::make('type_equipement')
                            ->label('Type de premier équipement pédagogique')
                            ->options([
                                'informatique' => 'Équipement informatique mis à disposition de l\'apprenti',
                                'outillage' => 'Outillage / matériel professionnel',
                                'autre' => 'Autre équipement',
                            ])
                            ->native(false)
                            ->visible(fn (Get $get): bool => (bool) $get('frais_equipement'))
                            ->columnSpanFull(),
                    ]),

                Section::make('Apprenants de la classe')
                    ->description('Seuls les apprenants de la formation ET de ce niveau (cohorte de l\'année, ou nouveaux sans classe) sont proposés. Un apprenant décoché sera détaché de la classe.')
                    ->schema([
                        CheckboxList::make('apprentis_ids')
                            ->label('')
                            ->options(fn (Get $get, ?Promotion $record): array => static::apprenants($get('formation_id'), static::anneeCourante($get, $record), $record)
                                ->mapWithKeys(fn (Candidate $c): array => [$c->id => $c->nom_complet])
                                ->all())
                            ->descriptions(fn (Get $get, ?Promotion $record): array => static::apprenants($get('formation_id'), static::anneeCourante($get, $record), $record)
                                ->mapWithKeys(fn (Candidate $c): array => [$c->id => static::sousTexte($c, $record)])
                                ->all())
                            ->searchable()
                            ->columns(2)
                            ->bulkToggleable()
                            ->noSearchResultsMessage('Aucun apprenant trouvé.')
                            ->hint(fn (Get $get): ?string => $get('formation_id') ? null : 'Choisissez d\'abord la formation de la classe.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Bouton Oui/Non pour une colonne booléenne : ponte le cast booléen du modèle
     * (true/false) et les clés d'option entières (1/0) — sans ce pont, l'état
     * hydraté depuis la base ne correspond à aucune option et se dé-hydrate en null
     * (violation NOT NULL à l'enregistrement).
     */
    protected static function ouiNon(string $name, string $label): ToggleButtons
    {
        return ToggleButtons::make($name)
            ->label($label)
            ->options([1 => 'Oui', 0 => 'Non'])
            ->colors([1 => 'success', 0 => 'gray'])
            ->inline()
            ->formatStateUsing(fn ($state): int => (int) (bool) $state)
            ->dehydrateStateUsing(fn ($state): bool => (bool) $state);
    }

    /** « Formation — 1ère année », « Formation — 2ème année »… selon la durée (mois). */
    protected static function optionsFormationAnnee(?Promotion $record = null): array
    {
        $options = Formation::query()
            ->orderBy('libelle')
            ->get()
            ->flatMap(fn (Formation $f): array => collect(range(1, max(1, (int) ceil(($f->duree_mois ?? 12) / 12))))
                ->mapWithKeys(fn (int $annee): array => [
                    $f->id.'|'.$annee => $f->libelle.' — '.static::libelleAnnee($annee),
                ])
                ->all())
            ->all();

        // À l'édition, la combinaison de la classe reste proposée même si la
        // durée de la formation a changé depuis (sinon la valeur serait rejetée).
        if ($record?->formation_id && preg_match('/^(\d+)/u', (string) $record->libelle, $m)) {
            $cle = $record->formation_id.'|'.$m[1];
            $options[$cle] ??= ($record->formation?->libelle ?? 'Formation').' — '.static::libelleAnnee((int) $m[1]);
        }

        return $options;
    }

    /** 1 → « 1ère année », n → « nème année ». */
    protected static function libelleAnnee(int $annee): string
    {
        return $annee === 1 ? '1ère année' : $annee.'ème année';
    }

    /** Niveau (année) de la classe en cours d'édition : « 1ère année », « 2ème année »… */
    protected static function anneeCourante(Get $get, ?Promotion $record): ?string
    {
        if (filled($state = $get('formation_annee'))) {
            return static::libelleAnnee((int) explode('|', (string) $state)[1]);
        }

        return $record?->libelle;
    }

    /**
     * Apprenants proposés : filtrés par formation ET niveau — la cohorte de
     * l'année (apprenants des autres matières de ce niveau) et les nouveaux
     * sans classe. Les membres actuels restent listés, en premier.
     */
    protected static function apprenants(mixed $formationId, ?string $annee, ?Promotion $record): Collection
    {
        if (blank($formationId)) {
            return new Collection;
        }

        return Candidate::query()
            ->with(['formationVisee', 'promotions'])
            ->where(fn ($q) => $q
                ->where('formation_visee_id', $formationId)
                ->orWhereNull('formation_visee_id')
                ->when($record, fn ($q) => $q->orWhereHas('promotions', fn ($p) => $p->whereKey($record->id))))
            // Cohorte : personne d'un AUTRE niveau (ex. un 1ère année dans une classe de 2ème).
            ->when($annee, fn ($q) => $q->where(fn ($q) => $q
                ->whereDoesntHave('promotions', fn ($p) => $p->where('libelle', '!=', $annee))
                ->when($record, fn ($q) => $q->orWhereHas('promotions', fn ($p) => $p->whereKey($record->id)))))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get()
            ->when($record, fn (Collection $c) => $c
                ->sortBy(fn (Candidate $a) => $a->promotions->contains($record) ? 0 : 1)
                ->values());
    }

    /** Sous-texte d'une option : formation visée + autres classes (matières) suivies. */
    protected static function sousTexte(Candidate $c, ?Promotion $record): string
    {
        $autresClasses = $c->promotions
            ->reject(fn (Promotion $p) => $p->id === $record?->id)
            ->map(fn (Promotion $p) => trim($p->libelle.($p->matiere ? ' · '.$p->matiere : '')));

        $infos = array_filter([
            $c->formationVisee?->libelle,
            $autresClasses->isNotEmpty() ? 'Suit aussi : '.$autresClasses->implode(', ') : null,
        ]);

        return implode(' · ', $infos) ?: '—';
    }
}
