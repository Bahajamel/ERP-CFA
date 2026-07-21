<?php

namespace App\Filament\Widgets;

use App\Enums\PresenceStatut;
use App\Filament\Resources\Seances\SeanceResource;
use App\Filament\Widgets\Concerns\HasClickableChart;
use App\Models\Formation;
use App\Models\Presence;
use App\Models\Promotion;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

/**
 * Assiduité d'UNE classe à la fois — jamais un agrégat de toutes les formations,
 * qui ne veut rien dire pour un responsable pédagogique.
 *
 * Trois réglages :
 *  - Formation (liste déroulante) ;
 *  - Classe de cette formation (dans ce CFA, une « classe » est une promotion :
 *    formation + groupe + année scolaire, ex. « Bachelor RH — Groupe A (2025-2026) ») ;
 *  - Type de graphe : barres, camembert ou anneau.
 *
 * Une classe est présélectionnée à l'ouverture (la plus récente qui a des séances
 * émargées) : l'écran affiche donc tout de suite quelque chose de lisible.
 *
 * La donnée est la RÉPARTITION des séances émargées (présents, retards, absences
 * justifiées ou non). Ce sont des parts d'un tout, qui somment à 100 % : c'est ce
 * qui rend les trois formes également valables.
 *
 * Palette validée pour les daltonismes ET les deux thèmes (clair/sombre) via
 * scripts/validate_palette.js — ne pas modifier ces teintes sans revalider.
 */
class AssiduiteRepartitionChart extends ChartWidget
{
    use HasClickableChart;
    use HasFiltersSchema;

    /**
     * Vue dédiée : elle affiche « Formation » et « Classe » EN CLAIR au-dessus du
     * graphique, au lieu de les cacher derrière l'icône entonnoir de Filament.
     */
    protected string $view = 'filament.widgets.assiduite-repartition';

    /** Type de graphe (liste déroulante en en-tête). */
    public ?string $filter = 'bar';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    /* Palette validée (voir en-tête). Teintes de statut : le sens prime. */
    private const C_BON = '#059669';          // présents

    private const C_ALERTE = '#d97706';       // retards / départs anticipés

    private const C_NEUTRE = '#3b82f6';       // absences justifiées

    private const C_CRITIQUE = '#ef4444';     // absences injustifiées

    /**
     * Nom de l'évènement annonçant la classe affichée : la page d'assiduité s'y
     * abonne pour ne lister, en dessous, que les apprentis de cette classe.
     */
    public const EVENEMENT_CLASSE = 'assiduite-classe-changee';

    public static function canView(): bool
    {
        return auth()->user()?->can('access_attendance') ?? false;
    }

    /**
     * À chaque changement de formation ou de classe, on prévient la page pour
     * que le tableau du dessous suive la même classe que le graphique.
     */
    public function updatedFilters(): void
    {
        $this->cachedData = null; // comportement d'origine (HasFiltersSchema)

        $this->dispatch(self::EVENEMENT_CLASSE, classeId: $this->classe()?->id);
    }

    /* ============================================================
     |  Sélection : formation → classe
     * ============================================================ */

    /**
     * Classe présélectionnée : la plus récente qui a effectivement des séances
     * émargées (sinon la plus récente tout court). Évite d'ouvrir sur un
     * graphique vide.
     */
    public static function classeParDefaut(): ?Promotion
    {
        $recentes = Promotion::query()->orderByDesc('annee_scolaire')->orderByDesc('id');

        return (clone $recentes)
            ->whereHas('seances.presences', fn ($q) => $q->where('statut', '!=', PresenceStatut::NonRenseigne->value))
            ->first()
            ?? $recentes->first();
    }

    /**
     * Classes d'UNE formation (id => libellé complet). Sans formation, la liste
     * est vide : on ne veut jamais proposer les classes d'une autre formation.
     *
     * @return array<int, string>
     */
    public static function classesDe(mixed $formationId): array
    {
        if (blank($formationId)) {
            return [];
        }

        return Promotion::query()
            ->where('formation_id', $formationId)
            ->orderByDesc('annee_scolaire')
            ->orderBy('libelle')
            ->get()
            ->pluck('nom_complet', 'id')
            ->all();
    }

    public function filtersSchema(Schema $schema): Schema
    {
        $defaut = self::classeParDefaut();

        return $schema
            // Les deux listes côte à côte : le choix se lit d'un coup d'œil.
            ->columns(['default' => 1, 'sm' => 2])
            ->components([
                Select::make('formation')
                    ->label('Formation')
                    ->options(fn (): array => Formation::query()
                        ->whereHas('promotions')
                        ->orderBy('libelle')
                        ->pluck('libelle', 'id')
                        ->all())
                    ->default($defaut?->formation_id)
                    ->selectablePlaceholder(false)
                    ->searchable()
                    ->native(false)
                    ->live()
                    // Changer de formation sans changer de classe afficherait
                    // l'assiduité d'une classe d'une autre formation : on
                    // repositionne sur la première classe de la formation choisie.
                    ->afterStateUpdated(fn (Set $set, $state) => $set(
                        'promotion',
                        array_key_first(self::classesDe($state)),
                    )),

                Select::make('promotion')
                    ->label('Classe')
                    ->helperText('Une classe = une promotion (groupe + année scolaire).')
                    ->options(fn (Get $get): array => self::classesDe($get('formation')))
                    ->default($defaut?->id)
                    ->selectablePlaceholder(false)
                    // Volontairement en select NATIF (ni searchable, ni native(false)) :
                    // le variant JS de Filament est rendu sous wire:ignore et garde
                    // ses options du premier rendu — la liste ne se mettait donc pas
                    // à jour en changeant de formation. Un select natif réémet ses
                    // <option> à chaque rendu Livewire. La liste est courte de toute
                    // façon (les années d'une formation).
                    ->live(),
            ]);
    }

    /**
     * Types de graphe proposés. Les trois sont valables ici : la donnée est une
     * répartition (parts d'un tout).
     *
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            'bar' => 'Barres',
            'pie' => 'Camembert',
            'doughnut' => 'Anneau',
        ];
    }

    protected function getType(): string
    {
        return in_array($this->filter, ['pie', 'doughnut'], true) ? $this->filter : 'bar';
    }

    /**
     * Classe affichée. La cohérence est garantie ICI plutôt que de dépendre du
     * seul hook de la liste déroulante : si la classe retenue n'appartient pas
     * à la formation choisie (cas typique : on vient de changer de formation),
     * on bascule sur la première classe de cette formation. Sans ce garde-fou,
     * on afficherait les chiffres d'une classe d'une autre formation.
     */
    private function classe(): ?Promotion
    {
        $formationId = $this->filters['formation'] ?? null;
        $classe = filled($this->filters['promotion'] ?? null)
            ? Promotion::find($this->filters['promotion'])
            : null;

        if (blank($formationId)) {
            return $classe ?? self::classeParDefaut();
        }

        // La classe doit relever de la formation choisie.
        if ($classe !== null && (int) $classe->formation_id === (int) $formationId) {
            return $classe;
        }

        $premiere = array_key_first(self::classesDe($formationId));

        return $premiere !== null ? Promotion::find($premiere) : null;
    }

    public function getHeading(): ?string
    {
        return 'Assiduité — '.($this->classe()?->nom_complet ?? 'aucune classe');
    }

    /** Le taux de présence de la classe est donné en clair sous le titre. */
    public function getDescription(): ?string
    {
        $taux = $this->tauxDeLaClasse();

        return $taux === null
            ? 'Aucune séance émargée pour cette classe'
            : "Taux de présence : {$taux} % — répartition des séances émargées";
    }

    /* ============================================================
     |  Données
     * ============================================================ */

    /** Présences émargées de la classe affichée (hors « non renseigné »). */
    private function presencesEmargees()
    {
        $classe = $this->classe();

        return Presence::query()
            ->where('statut', '!=', PresenceStatut::NonRenseigne->value)
            // Aucune classe (base vide) : on ne remonte rien plutôt que tout.
            ->when($classe === null, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($classe !== null, fn ($q) => $q->whereHas(
                'seance',
                fn ($s) => $s->where('promotion_id', $classe->id),
            ));
    }

    /** Taux de présence (%) de la classe, null si rien n'est émargé. */
    private function tauxDeLaClasse(): ?int
    {
        $renseignees = $this->presencesEmargees()->count();

        if ($renseignees === 0) {
            return null;
        }

        $presents = $this->presencesEmargees()
            ->whereIn('statut', array_map(fn (PresenceStatut $s): string => $s->value, PresenceStatut::presents()))
            ->count();

        return (int) round($presents / $renseignees * 100);
    }

    /**
     * Répartition des séances émargées de la classe. Les motifs à zéro sont
     * écartés : une part invisible n'apporte rien à la lecture.
     */
    protected function getData(): array
    {
        $compte = fn (array $statuts): int => $this->presencesEmargees()
            ->whereIn('statut', array_map(fn (PresenceStatut $s): string => $s->value, $statuts))
            ->count();

        $segments = array_filter([
            'Présents' => [self::C_BON, $compte([PresenceStatut::Present])],
            'Retards / départs anticipés' => [self::C_ALERTE, $compte([PresenceStatut::Retard, PresenceStatut::DepartAnticipe])],
            'Absences justifiées' => [self::C_NEUTRE, $compte([PresenceStatut::AbsentJustifie])],
            'Absences injustifiées' => [self::C_CRITIQUE, $compte([PresenceStatut::AbsentInjustifie])],
        ], fn (array $s): bool => $s[1] > 0);

        $estBarre = $this->getType() === 'bar';

        return [
            'datasets' => [
                [
                    'label' => 'Séances émargées',
                    'data' => array_map(fn (array $s): int => $s[1], array_values($segments)),
                    'backgroundColor' => array_map(fn (array $s): string => $s[0], array_values($segments)),
                    'borderWidth' => 0,
                    'borderRadius' => $estBarre ? 4 : 0,
                    // Écart entre parts : encodage secondaire exigé par la
                    // validation daltonisme (cf. en-tête de classe).
                    'spacing' => $estBarre ? 0 : 2,
                ],
            ],
            'labels' => array_keys($segments),
        ];
    }

    /* ============================================================
     |  Rendu
     * ============================================================ */

    /** Chaque segment mène à l'émargement de la classe affichée. */
    protected function getSegmentUrls(): array
    {
        $classe = $this->classe();

        if ($classe === null) {
            return [];
        }

        $url = SeanceResource::getUrl('index', [
            'filters' => ['promotion_id' => ['value' => $classe->id]],
        ]);

        return array_fill(0, count($this->getData()['labels']), $url);
    }

    protected function getOptions(): RawJs
    {
        // Camembert / anneau : pas d'axes, et une légende obligatoire — sans elle
        // l'identité des parts reposerait sur la seule couleur.
        if ($this->getType() !== 'bar') {
            return $this->clickableOptions([
                'plugins' => [
                    'legend' => ['display' => true, 'position' => 'right'],
                ],
            ]);
        }

        // Barres : les libellés de l'axe portent déjà l'identité → pas de légende.
        return $this->clickableOptions([
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ]);
    }
}
