<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatut;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Widgets\Concerns\HasClickableChart;
use App\Models\Contract;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Dynamique commerciale : nombre de contrats signés par mois sur un an glissant.
 * Vue macro de la direction — la tendance de signature progresse-t-elle ?
 * (Basée sur la date d'enregistrement du contrat signé, faute de date de
 * signature dédiée.) Réservée à la Direction et à l'Administrateur.
 */
class ContratsSignesParMoisChart extends ChartWidget
{
    use HasClickableChart;

    protected ?string $heading = 'Contrats signés par mois';

    protected ?string $description = 'Sur les 12 derniers mois — clic → liste des contrats signés';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    /** Comparaison N-1 activée par défaut. */
    public ?string $filter = 'comparer';

    /** Statuts témoignant d'un contrat signé (ou au-delà). */
    private const STATUTS_SIGNES = [
        ContractStatut::Complet->value,
        ContractStatut::ACorriger->value,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Direction', 'Administrateur']) ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'simple' => 'Cette année',
            'comparer' => 'Comparer à N-1',
        ];
    }

    /** Nombre de contrats signés par mois sur les 12 mois à partir de $debut. */
    private function buckets(Carbon $debut): array
    {
        $buckets = [];
        for ($i = 0; $i < 12; $i++) {
            $buckets[$debut->copy()->addMonths($i)->format('Y-m')] = 0;
        }

        Contract::whereIn('statut_contrat', self::STATUTS_SIGNES)
            ->whereBetween('created_at', [$debut, $debut->copy()->addMonths(12)])
            ->pluck('created_at')
            ->each(function (Carbon $date) use (&$buckets): void {
                $cle = $date->format('Y-m');
                if (isset($buckets[$cle])) {
                    $buckets[$cle]++;
                }
            });

        return $buckets;
    }

    protected function getData(): array
    {
        $debut = now()->startOfMonth()->subMonths(11);
        $current = $this->buckets($debut);

        $labels = array_map(
            fn (string $ym): string => Carbon::createFromFormat('Y-m', $ym)
                ->locale('fr')->translatedFormat('M Y'),
            array_keys($current),
        );

        $datasets = [
            [
                'label' => 'Cette année',
                'data' => array_values($current),
                'borderColor' => 'rgb(16, 185, 129)',
                'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                'fill' => true,
                'tension' => 0.3,
            ],
        ];

        // Comparaison N-1 : mêmes mois, un an plus tôt (ligne pointillée grise).
        if ($this->filter === 'comparer') {
            $previous = $this->buckets($debut->copy()->subYear());
            $datasets[] = [
                'label' => 'Année précédente (N-1)',
                'data' => array_values($previous),
                'borderColor' => 'rgb(148, 163, 184)',
                'backgroundColor' => 'rgba(148, 163, 184, 0)',
                'borderDash' => [6, 4],
                'fill' => false,
                'tension' => 0.3,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getSegmentUrls(): array
    {
        // Chaque point renvoie vers la liste des contrats signés (pas de filtre
        // mensuel sur la ressource Contrats — on ne la modifie pas ici).
        $url = ContractResource::getUrl('index', [
            'filters' => ['statut_contrat' => ['value' => ContractStatut::Complet->value]],
        ]);

        return array_fill(0, 12, $url);
    }

    protected function getOptions(): \Filament\Support\RawJs
    {
        return $this->clickableOptions([
            'plugins' => [
                'legend' => [
                    'display' => $this->filter === 'comparer',
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ]);
    }
}
