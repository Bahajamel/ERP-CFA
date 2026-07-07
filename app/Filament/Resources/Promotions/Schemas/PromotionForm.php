<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Models\Candidate;
use App\Models\Formation;
use App\Models\Promotion;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                        TextInput::make('matiere')
                            ->label('Matière')
                            ->placeholder('Ex. Mathématiques, Développement web')
                            ->maxLength(255),
                        Hidden::make('formation_id')
                            ->required(),
                        Hidden::make('libelle')
                            ->required(),
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
