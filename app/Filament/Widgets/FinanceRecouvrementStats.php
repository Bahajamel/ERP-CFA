<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatut;
use App\Models\FinancePayment;
use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI de recouvrement (Finance) : ce qui est facturé, encaissé, en retard, et le
 * délai moyen d'encaissement (DSO). Réservé à la Finance, la Direction et l'Administrateur.
 */
class FinanceRecouvrementStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Recouvrement';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['Finance', 'Direction', 'Administrateur']) ?? false;
    }

    private static function euros(float $montant): string
    {
        return number_format($montant, 0, ',', ' ').' €';
    }

    protected function getStats(): array
    {
        $facture = (float) Invoice::whereIn('statut', [InvoiceStatut::Emise->value, InvoiceStatut::Payee->value])
            ->sum('montant');
        $encaisse = (float) FinancePayment::sum('montant');
        $taux = $facture > 0 ? (int) round($encaisse / $facture * 100) : 0;
        $reste = max(0, round($facture - $encaisse, 2));

        // Impayés échus : montant restant dû sur les factures émises et dépassées.
        $enRetardMontant = 0.0;
        $enRetardCount = 0;
        Invoice::query()
            ->where('statut', InvoiceStatut::Emise->value)
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<', now()->toDateString())
            ->with('payments')
            ->get()
            ->each(function (Invoice $invoice) use (&$enRetardMontant, &$enRetardCount): void {
                $r = $invoice->resteAPayer();
                if ($r > 0) {
                    $enRetardMontant += $r;
                    $enRetardCount++;
                }
            });

        // DSO : délai moyen (jours) entre l'émission et l'encaissement.
        $jours = FinancePayment::query()
            ->whereHas('invoice', fn ($q) => $q->whereNotNull('date_emission'))
            ->with('invoice')
            ->get()
            ->map(fn (FinancePayment $p): int => (int) $p->invoice->date_emission->diffInDays($p->date_paiement))
            ->filter(fn (int $d): bool => $d >= 0);
        $dso = $jours->isNotEmpty() ? (int) round($jours->avg()) : null;

        return [
            Stat::make('Facturé', self::euros($facture))
                ->description('Total des factures émises')
                ->descriptionIcon('heroicon-m-document-currency-euro')
                ->color('info'),
            Stat::make('Encaissé', self::euros($encaisse))
                ->description("Taux de recouvrement : {$taux} %")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($taux >= 80 ? 'success' : ($taux >= 50 ? 'warning' : 'danger')),
            Stat::make('Reste à encaisser', self::euros($reste))
                ->description('Émis non encore encaissé')
                ->descriptionIcon('heroicon-m-clock')
                ->color($reste > 0 ? 'warning' : 'success'),
            Stat::make('Impayés échus', self::euros($enRetardMontant))
                ->description($enRetardCount.' facture(s) en retard')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($enRetardMontant > 0 ? 'danger' : 'success'),
            Stat::make('Délai moyen (DSO)', $dso === null ? '—' : $dso.' j')
                ->description('Émission → encaissement')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('gray'),
        ];
    }
}
