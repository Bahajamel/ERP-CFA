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
        // « Encaissé » additionne ce qui est RÉELLEMENT tombé (montant_verse) :
        // l'OPCO verse couramment moins que prévu (proratisation, rupture), et
        // sommer montant_prevu affichait un encaissement fantôme.
        $verse = (float) OpcoPayment::query()
            ->where('statut', PaymentStatut::Verse->value)
            ->sum('montant_verse');

        // Retard = échu et non versé (scope unique), et non « statut = En retard » :
        // sinon une échéance dépassée comptait parmi « À venir » tant que la
        // commande quotidienne ne l'avait pas requalifiée.
        $enRetard = (float) OpcoPayment::enRetard()->sum('montant_prevu');

        $attendu = (float) OpcoPayment::query()
            ->where('statut', '!=', PaymentStatut::Verse->value)
            ->where(fn ($q) => $q
                ->whereNull('date_prevue')
                ->orWhereDate('date_prevue', '>=', now()->toDateString()))
            ->sum('montant_prevu');

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
