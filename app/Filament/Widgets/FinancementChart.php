<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatut;
use App\Models\OpcoPayment;
use Filament\Widgets\ChartWidget;

/**
 * Santé financière OPCO : répartition des échéances de versement.
 * Vue trésorerie pour la direction — ce qui est encaissé, en retard, ou à venir.
 * Réservée à la Direction et à l'Administrateur.
 */
class FinancementChart extends ChartWidget
{
    protected ?string $heading = 'Trésorerie OPCO (échéances de versement)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Direction', 'Administrateur']) ?? false;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $verse = (float) OpcoPayment::where('statut', PaymentStatut::Verse->value)->sum('montant_prevu');
        $enRetard = (float) OpcoPayment::where('statut', PaymentStatut::EnRetard->value)->sum('montant_prevu');
        $attendu = (float) OpcoPayment::where('statut', PaymentStatut::Attendu->value)->sum('montant_prevu');

        return [
            'datasets' => [
                [
                    'data' => [$verse, $enRetard, $attendu],
                    'backgroundColor' => [
                        'rgb(16, 185, 129)',  // émeraude — encaissé
                        'rgb(239, 68, 68)',   // rouge — en retard
                        'rgb(148, 163, 184)', // gris — à venir
                    ],
                ],
            ],
            'labels' => ['Encaissé', 'En retard', 'À venir'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}
