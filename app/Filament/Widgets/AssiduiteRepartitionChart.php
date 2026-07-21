<?php

namespace App\Filament\Widgets;

use App\Enums\PresenceStatut;
use App\Filament\Resources\Seances\SeanceResource;
use App\Filament\Widgets\Concerns\HasClickableChart;
use App\Models\Presence;
use App\Models\Promotion;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

/**
 * Répartition de l'assiduité, avec deux réglages indépendants :
 *
 *  - le PÉRIMÈTRE (panneau « Filtrer ») : toutes les classes, ou une classe précise ;
 *  - la FORME (liste déroulante en en-tête) : barres, camembert ou anneau.
 *
 * Complète {@see AssiduiteParPromotionChart}, qui compare les classes entre elles :
 * ici on regarde DE QUOI est faite l'assiduité d'un périmètre (présents, retards,
 * absences justifiées ou non).
 *
 * C'est justement cette donnée — des parts d'un tout, qui somment à 100 % — qui rend
 * les trois formes légitimes. Un taux par classe, lui, ne se met pas en camembert.
 *
 * Palette validée pour les daltonismes ET les deux thèmes (clair/sombre) via
 * scripts/validate_palette.js — ne pas modifier ces teintes sans revalider.
 */
class AssiduiteRepartitionChart extends ChartWidget
{
    use HasClickableChart;
    use HasFiltersSchema;

    /** Forme du graphique (liste déroulante en en-tête). */
    public ?string $filter = 'bar';

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    /* Palette validée (voir en-tête). Teintes de statut : le sens prime. */
    private const C_BON = '#059669';          // présents

    private const C_ALERTE = '#d97706';       // retards / départs anticipés

    private const C_NEUTRE = '#3b82f6';       // absences justifiées

    private const C_CRITIQUE = '#ef4444';     // absences injustifiées

    public static function canView(): bool
    {
        return auth()->user()?->can('access_attendance') ?? false;
    }

    /* ============================================================
     |  Réglages : périmètre (classe) et forme
     * ============================================================ */

    /** Panneau de filtres : la classe (promotion) sur laquelle porter la lecture. */
    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('classe')
                ->label('Classe')
                ->placeholder('Toutes les classes')
                ->options(fn (): array => Promotion::query()
                    ->orderBy('libelle')
                    ->get()
                    ->pluck('nom_complet', 'id')
                    ->all())
                ->searchable()
                ->native(false),
        ]);
    }

    /**
     * Formes proposées. Les trois sont valables ici : la donnée est une
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

    /** Classe sélectionnée (id de promotion), ou null pour « toutes ». */
    private function classeId(): ?int
    {
        $id = $this->filters['classe'] ?? null;

        return filled($id) ? (int) $id : null;
    }

    public function getHeading(): ?string
    {
        $classe = $this->classeId();

        return $classe === null
            ? 'Répartition — toutes les classes'
            : 'Répartition — '.(Promotion::find($classe)?->nom_complet ?? 'classe');
    }

    /** Le taux de présence du périmètre est donné en clair sous le titre. */
    public function getDescription(): ?string
    {
        $taux = $this->tauxDuPerimetre();

        return $taux === null
            ? 'Aucune séance émargée sur ce périmètre'
            : "Taux de présence : {$taux} % — répartition des séances émargées";
    }

    /* ============================================================
     |  Données
     * ============================================================ */

    /** Présences émargées du périmètre courant (hors « non renseigné »). */
    private function presencesEmargees()
    {
        $classe = $this->classeId();

        return Presence::query()
            ->where('statut', '!=', PresenceStatut::NonRenseigne->value)
            ->when($classe !== null, fn ($q) => $q->whereHas(
                'seance',
                fn ($s) => $s->where('promotion_id', $classe),
            ));
    }

    /** Taux de présence (%) du périmètre, null si rien n'est émargé. */
    private function tauxDuPerimetre(): ?int
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
     * Répartition des séances émargées du périmètre. Les motifs à zéro sont
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

    /**
     * Drill-down : quand une classe est choisie, chaque segment mène à ses
     * séances (émargement). Sur « toutes les classes », il n'y a pas de cible
     * unique — les segments ne sont pas cliquables.
     */
    protected function getSegmentUrls(): array
    {
        $classe = $this->classeId();

        if ($classe === null) {
            return [];
        }

        $url = SeanceResource::getUrl('index', [
            'filters' => ['promotion_id' => ['value' => $classe]],
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
