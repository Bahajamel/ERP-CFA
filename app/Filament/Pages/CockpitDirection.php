<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ContratsSignesParMoisChart;
use App\Filament\Widgets\ConversionFunnelChart;
use App\Filament\Widgets\DirectionStatsOverview;
use App\Filament\Widgets\FinancementChart;
use App\Models\User;
use App\Services\RapportPilotageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Cockpit de pilotage Direction : rassemble les indicateurs et graphiques clés
 * sur une seule page, et permet d'exporter une synthèse chiffrée en PDF.
 */
class CockpitDirection extends Page
{
    protected string $view = 'filament.pages.cockpit-direction';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Cockpit Direction';

    protected static ?string $title = 'Cockpit de pilotage';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasAnyRole(['Direction', 'Administrateur']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->hasAnyRole(['Direction', 'Administrateur']) ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DirectionStatsOverview::class,
            ContratsSignesParMoisChart::class,
            ConversionFunnelChart::class,
            FinancementChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rapportPdf')
                ->label('Télécharger le rapport (PDF)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $donnees = app(RapportPilotageService::class)->donnees();
                    $pdf = Pdf::loadView('pdf.rapport-pilotage', ['r' => $donnees]);

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        'rapport-pilotage-'.now()->format('Y-m-d').'.pdf',
                    );
                }),
        ];
    }
}
