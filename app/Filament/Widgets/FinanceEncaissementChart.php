<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatut;
use App\Models\FinanceLine;
use App\Models\FinancePayment;
use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

/**
 * Suivi financier global : ce qui est attendu, effectivement facturé et encaissé.
 * Donne à la direction l'écart entre le prévisionnel et le réel en un coup d'œil.
 * Réservée à la Finance, la Direction et l'Administrateur.
 */
class FinanceEncaissementChart extends ChartWidget
{
    protected ?string $heading = 'Attendu / Facturé / Encaissé';

    protected ?string $description = 'Vue globale du chiffre attendu au réel encaissé';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Finance', 'Direction', 'Administrateur']) ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $attendu = (float) FinanceLine::sum('montant_attendu');
        $facture = (float) Invoice::whereIn('statut', [
            InvoiceStatut::Emise->value,
            InvoiceStatut::Payee->value,
        ])->sum('montant');
        $encaisse = (float) FinancePayment::sum('montant');

        return [
            'datasets' => [
                [
                    'label' => 'Montant (€)',
                    'data' => [$attendu, $facture, $encaisse],
                    'backgroundColor' => [
                        'rgba(148, 163, 184, 0.7)', // gris — attendu
                        'rgba(59, 130, 246, 0.7)',  // bleu — facturé
                        'rgba(16, 185, 129, 0.7)',  // émeraude — encaissé
                    ],
                    'borderColor' => [
                        'rgb(148, 163, 184)',
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => ['Attendu', 'Facturé', 'Encaissé'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
