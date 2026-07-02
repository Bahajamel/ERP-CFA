<?php

namespace App\Filament\Widgets;

use App\Enums\PresenceStatut;
use App\Filament\Resources\Seances\SeanceResource;
use App\Filament\Widgets\Concerns\HasClickableChart;
use App\Models\Presence;
use App\Models\Promotion;
use Filament\Widgets\ChartWidget;

/**
 * Taux de présence par promotion : repère en un coup d'œil les classes qui
 * décrochent. Clic sur une barre → liste des séances/émargement de la classe.
 * Réservée aux profils ayant accès à l'assiduité.
 */
class AssiduiteParPromotionChart extends ChartWidget
{
    use HasClickableChart;

    protected ?string $heading = 'Assiduité par promotion';

    protected ?string $description = 'Taux de présence — clic → émargement de la classe';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    /** @var array<int, int>|null Ids de promotion alignés sur les labels. */
    private ?array $promotionIds = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('access_attendance') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** Taux de présence (%) d'une promotion, ou null si aucune présence renseignée. */
    private function tauxPromotion(int $promotionId): ?int
    {
        $renseignees = Presence::whereHas('seance', fn ($q) => $q->where('promotion_id', $promotionId))
            ->where('statut', '!=', PresenceStatut::NonRenseigne->value)
            ->count();

        if ($renseignees === 0) {
            return null;
        }

        $presents = Presence::whereHas('seance', fn ($q) => $q->where('promotion_id', $promotionId))
            ->whereIn('statut', array_map(fn (PresenceStatut $s) => $s->value, PresenceStatut::presents()))
            ->count();

        return (int) round($presents / $renseignees * 100);
    }

    protected function getData(): array
    {
        $labels = [];
        $data = [];
        $ids = [];

        Promotion::query()->orderBy('libelle')->get()->each(function (Promotion $promo) use (&$labels, &$data, &$ids): void {
            $taux = $this->tauxPromotion($promo->id);

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
                    'backgroundColor' => array_map(fn (int $t): string => match (true) {
                        $t >= 90 => 'rgba(16, 185, 129, 0.7)',  // émeraude
                        $t >= 70 => 'rgba(234, 179, 8, 0.7)',   // ambre
                        default => 'rgba(239, 68, 68, 0.7)',    // rouge
                    }, $data),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getSegmentUrls(): array
    {
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

    protected function getOptions(): \Filament\Support\RawJs
    {
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
