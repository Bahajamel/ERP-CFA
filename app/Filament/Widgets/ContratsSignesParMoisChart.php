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

    /** Statuts témoignant d'un contrat signé (ou au-delà). */
    private const STATUTS_SIGNES = [
        ContractStatut::Signe->value,
        ContractStatut::TransmisOpco->value,
        ContractStatut::Actif->value,
    ];

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Direction', 'Administrateur']) ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    /** Buckets [Y-m => nombre] des 12 derniers mois (mois courant inclus). */
    private function buckets(): array
    {
        $debut = now()->startOfMonth()->subMonths(11);

        $buckets = [];
        for ($i = 0; $i < 12; $i++) {
            $buckets[$debut->copy()->addMonths($i)->format('Y-m')] = 0;
        }

        Contract::whereIn('statut_contrat', self::STATUTS_SIGNES)
            ->where('created_at', '>=', $debut)
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
        $buckets = $this->buckets();

        $labels = array_map(
            fn (string $ym): string => Carbon::createFromFormat('Y-m', $ym)
                ->locale('fr')->translatedFormat('M Y'),
            array_keys($buckets),
        );

        return [
            'datasets' => [
                [
                    'label' => 'Contrats signés',
                    'data' => array_values($buckets),
                    'borderColor' => 'rgb(16, 185, 129)',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getSegmentUrls(): array
    {
        // Chaque point renvoie vers la liste des contrats signés (pas de filtre
        // mensuel sur la ressource Contrats — on ne la modifie pas ici).
        $url = ContractResource::getUrl('index', [
            'tableFilters' => ['statut_contrat' => ['value' => ContractStatut::Signe->value]],
        ]);

        return array_fill(0, 12, $url);
    }

    protected function getOptions(): \Filament\Support\RawJs
    {
        return $this->clickableOptions([
            'plugins' => [
                'legend' => ['display' => false],
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
