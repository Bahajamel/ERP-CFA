<?php

namespace App\Filament\Widgets;

use App\Enums\PresenceStatut;
use App\Filament\Resources\Seances\SeanceResource;
use App\Filament\Widgets\Concerns\HasClickableChart;
use App\Models\Presence;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Statistiques d'assiduité, en trois lectures au choix (sélecteur en en-tête) :
 *
 *  - « Par promotion » (barres)   : quelles classes décrochent ? Clic → émargement.
 *  - « Évolution » (courbe)       : le taux se dégrade-t-il dans le temps ?
 *  - « Motifs » (camembert)       : de quoi sont faites les absences ?
 *
 * Chaque forme est posée sur la donnée qu'elle sait raconter : une courbe suppose
 * une chronologie (mois), un camembert suppose des parts d'un tout (les motifs
 * somment à 100 %). Les taux par promotion, eux, sont des valeurs indépendantes :
 * ils restent en barres.
 *
 * Palette validée pour les daltonismes ET les deux thèmes (clair/sombre) via
 * scripts/validate_palette.js — ne pas modifier ces teintes sans revalider.
 */
class AssiduiteParPromotionChart extends ChartWidget
{
    use HasClickableChart;

    /** Vue par défaut : celle qui existait avant le sélecteur. */
    public ?string $filter = 'promotion';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    /** Nombre de mois affichés par la vue « Évolution ». */
    private const MOIS_AFFICHES = 6;

    /* Palette validée (voir en-tête). Teintes de statut : le sens prime. */
    private const C_BON = '#059669';          // présent / taux élevé

    private const C_ALERTE = '#d97706';       // retard / taux moyen

    private const C_NEUTRE = '#3b82f6';       // absence justifiée / tendance

    private const C_CRITIQUE = '#ef4444';     // absence injustifiée / taux bas

    /** @var array<int, int>|null Ids de promotion alignés sur les labels. */
    private ?array $promotionIds = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('access_attendance') ?? false;
    }

    /**
     * Les trois lectures proposées au lecteur.
     *
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            'promotion' => 'Par promotion (barres)',
            'evolution' => 'Évolution mensuelle (courbe)',
            'motifs' => 'Répartition des motifs (camembert)',
        ];
    }

    public function getHeading(): ?string
    {
        return match ($this->filter) {
            'evolution' => 'Évolution de l\'assiduité',
            'motifs' => 'Répartition des présences et absences',
            default => 'Assiduité par promotion',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this->filter) {
            'evolution' => 'Taux de présence des '.self::MOIS_AFFICHES.' derniers mois',
            'motifs' => 'Part de chaque motif sur les séances émargées',
            default => 'Taux de présence — clic sur une barre → émargement de la classe',
        };
    }

    protected function getType(): string
    {
        return match ($this->filter) {
            'evolution' => 'line',
            'motifs' => 'doughnut',
            default => 'bar',
        };
    }

    protected function getData(): array
    {
        return match ($this->filter) {
            'evolution' => $this->donneesEvolution(),
            'motifs' => $this->donneesMotifs(),
            default => $this->donneesParPromotion(),
        };
    }

    /* ============================================================
     |  Jeux de données
     * ============================================================ */

    /** Taux de présence (%) par promotion — barres colorées par seuil. */
    private function donneesParPromotion(): array
    {
        $labels = [];
        $data = [];
        $ids = [];

        Promotion::query()->orderBy('libelle')->get()->each(function (Promotion $promo) use (&$labels, &$data, &$ids): void {
            $taux = $this->taux(fn ($q) => $q->where('promotion_id', $promo->id));

            if ($taux === null) {
                return; // classe sans émargement → non affichée
            }

            $labels[] = $promo->libelle;
            $data[] = $taux;
            $ids[] = $promo->id;
        });

        $this->promotionIds = $ids;

        return [
            'datasets' => [
                [
                    'label' => 'Taux de présence (%)',
                    'data' => $data,
                    'backgroundColor' => array_map(fn (int $t): string => $this->couleurSeuil($t), $data),
                    'borderWidth' => 0,
                    'borderRadius' => 4, // extrémité arrondie, ancrée à la ligne de base
                ],
            ],
            'labels' => $labels,
        ];
    }

    /** Taux de présence (%) mois par mois — une seule série, donc pas de légende. */
    private function donneesEvolution(): array
    {
        $labels = [];
        $data = [];
        $mois = CarbonImmutable::now()->startOfMonth()->subMonths(self::MOIS_AFFICHES - 1);

        for ($i = 0; $i < self::MOIS_AFFICHES; $i++) {
            $debut = $mois->addMonths($i);
            $fin = $debut->endOfMonth();

            $labels[] = $debut->translatedFormat('M Y');
            // null = mois sans émargement : la courbe laisse un trou plutôt que
            // de descendre à zéro (ce qui se lirait comme « personne n'est venu »).
            $data[] = $this->taux(fn ($q) => $q->whereBetween('date', [$debut->toDateString(), $fin->toDateString()]));
        }

        return [
            'datasets' => [
                [
                    'label' => 'Taux de présence (%)',
                    'data' => $data,
                    'borderColor' => self::C_NEUTRE,
                    'backgroundColor' => self::C_NEUTRE,
                    'borderWidth' => 2,
                    'pointRadius' => 4,
                    'tension' => 0.3,
                    'fill' => false,
                    'spanGaps' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * Répartition des motifs sur les séances émargées : les parts somment bien à
     * 100 %, ce qui est la condition pour qu'un camembert soit honnête.
     */
    private function donneesMotifs(): array
    {
        $compte = fn (array $statuts): int => Presence::query()
            ->whereIn('statut', array_map(fn (PresenceStatut $s): string => $s->value, $statuts))
            ->count();

        $segments = [
            'Présents' => [self::C_BON, $compte([PresenceStatut::Present])],
            'Retards / départs anticipés' => [self::C_ALERTE, $compte([PresenceStatut::Retard, PresenceStatut::DepartAnticipe])],
            'Absences justifiées' => [self::C_NEUTRE, $compte([PresenceStatut::AbsentJustifie])],
            'Absences injustifiées' => [self::C_CRITIQUE, $compte([PresenceStatut::AbsentInjustifie])],
        ];

        // Un motif à zéro n'apporte rien : on l'écarte plutôt que d'afficher une
        // part invisible dans la légende.
        $segments = array_filter($segments, fn (array $s): bool => $s[1] > 0);

        return [
            'datasets' => [
                [
                    'label' => 'Séances émargées',
                    'data' => array_map(fn (array $s): int => $s[1], array_values($segments)),
                    'backgroundColor' => array_map(fn (array $s): string => $s[0], array_values($segments)),
                    'borderWidth' => 0,
                    'spacing' => 2, // écart entre parts (encodage secondaire, cf. validation CVD)
                ],
            ],
            'labels' => array_keys($segments),
        ];
    }

    /* ============================================================
     |  Calculs
     * ============================================================ */

    /**
     * Taux de présence (%) sur les séances émargées, restreint par $filtreSeance.
     * null si aucune séance n'a été émargée sur le périmètre.
     */
    private function taux(callable $filtreSeance): ?int
    {
        $base = fn () => Presence::query()
            ->whereHas('seance', $filtreSeance)
            ->where('statut', '!=', PresenceStatut::NonRenseigne->value);

        $renseignees = $base()->count();

        if ($renseignees === 0) {
            return null;
        }

        $presents = $base()
            ->whereIn('statut', array_map(fn (PresenceStatut $s): string => $s->value, PresenceStatut::presents()))
            ->count();

        return (int) round($presents / $renseignees * 100);
    }

    /** Couleur de statut d'un taux (vert ≥ 90, ambre ≥ 70, rouge en dessous). */
    private function couleurSeuil(int $taux): string
    {
        return match (true) {
            $taux >= 90 => self::C_BON,
            $taux >= 70 => self::C_ALERTE,
            default => self::C_CRITIQUE,
        };
    }

    /* ============================================================
     |  Rendu
     * ============================================================ */

    /** Seule la vue « par promotion » mène quelque part (l'émargement de la classe). */
    protected function getSegmentUrls(): array
    {
        if ($this->filter !== 'promotion') {
            return [];
        }

        if ($this->promotionIds === null) {
            $this->getData();
        }

        return array_map(
            fn (int $id): string => SeanceResource::getUrl('index', [
                'filters' => ['promotion_id' => ['value' => $id]],
            ]),
            $this->promotionIds ?? [],
        );
    }

    protected function getOptions(): RawJs
    {
        // Camembert : pas d'axes, et une légende obligatoire (plusieurs catégories
        // → l'identité ne doit jamais reposer sur la seule couleur).
        if ($this->filter === 'motifs') {
            return $this->clickableOptions([
                'plugins' => [
                    'legend' => ['display' => true, 'position' => 'right'],
                ],
            ]);
        }

        // Barres et courbe : série unique → le titre suffit, pas de légende.
        return $this->clickableOptions([
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                ],
            ],
        ]);
    }
}
