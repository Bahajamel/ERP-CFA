<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatut;
use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

/**
 * Santé de la facturation : ventilation des montants émis entre encaissé,
 * en retard et reste à encaisser (non échu). Vue trésorerie « facturation »
 * complémentaire du suivi OPCO. Réservée à la Finance, la Direction et l'Administrateur.
 */
class FinanceFacturesChart extends ChartWidget
{
    protected ?string $heading = 'Factures : encaissé / en retard / à encaisser';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Finance', 'Direction', 'Administrateur']) ?? false;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $encaisse = 0.0;
        $enRetard = 0.0;
        $aEncaisser = 0.0;

        // Factures émises (non annulées) : on ventile le reste à payer.
        Invoice::whereIn('statut', [InvoiceStatut::Emise->value, InvoiceStatut::Payee->value])
            ->with('payments')
            ->get()
            ->each(function (Invoice $invoice) use (&$encaisse, &$enRetard, &$aEncaisser): void {
                $encaisse += $invoice->montantPaye();
                $reste = $invoice->resteAPayer();

                if ($reste <= 0) {
                    return;
                }

                if ($invoice->estEnRetard()) {
                    $enRetard += $reste;
                } else {
                    $aEncaisser += $reste;
                }
            });

        return [
            'datasets' => [
                [
                    'data' => [
                        round($encaisse, 2),
                        round($enRetard, 2),
                        round($aEncaisser, 2),
                    ],
                    'backgroundColor' => [
                        'rgb(16, 185, 129)',  // émeraude — encaissé
                        'rgb(239, 68, 68)',   // rouge — en retard
                        'rgb(148, 163, 184)', // gris — à encaisser (non échu)
                    ],
                ],
            ],
            'labels' => ['Encaissé', 'En retard', 'À encaisser'],
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
