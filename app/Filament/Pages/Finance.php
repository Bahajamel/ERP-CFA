<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\OpcoFiles\OpcoFileResource;
use App\Finance\FinanceDashboardData;
use App\Models\User;
use App\Services\FinanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard Finance : vue synthétique pour la Direction et le service Finance
 * (montants attendus / facturés / encaissés / en retard / bloqués, cash bloqué
 * par raison, suivi financier, factures & paiements récents, actions prioritaires).
 *
 * Synchronisation AUTOMATIQUE : à chaque ouverture de la page, tous les dossiers
 * Contrats & OPCO sont resynchronisés dans Finance (idempotent) — aucun bouton.
 * Les dossiers acceptés se synchronisent aussi en direct via le modèle OpcoFile.
 *
 * Contenu de page uniquement — sidebar, header global et layout sont fournis par
 * le panneau Filament. Données : cf. App\Finance\FinanceDashboardData.
 */
class Finance extends Page
{
    protected string $view = 'filament.pages.finance';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance & Facturation';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Finance';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $title = 'Finance';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        // Direction, Finance, Administratif, Administrateur.
        return $user instanceof User && $user->can('access_finance');
    }

    /** Synchronisation automatique des dossiers Contrats & OPCO à l'ouverture. */
    public function mount(): void
    {
        app(FinanceService::class)->synchroniserTousLesDossiers(Auth::id());
    }

    public function getSubheading(): ?string
    {
        return 'Suivez vos montants attendus, facturés, encaissés et bloqués.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exporter')
                ->label('Exporter')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Notification::make()->info()->title('Export à brancher')
                    ->body('L\'export du suivi financier sera disponible prochainement.')->send()),
            Action::make('contrats')
                ->label('Contrats')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(ContractResource::getUrl('index')),
            Action::make('dossiersOpco')
                ->label('Dossiers OPCO')
                ->icon('heroicon-o-banknotes')
                ->url(OpcoFileResource::getUrl('index')),
        ];
    }

    protected function getViewData(): array
    {
        $data = app(FinanceDashboardData::class);

        return [
            'kpis' => $data->kpis(),
            'graphique' => $data->graphique(),
            'cashBloque' => $data->cashBloqueParRaison(),
            'factures' => $data->facturesRecentes(),
            'paiements' => $data->paiementsRecents(),
            'actions' => $data->actionsPrioritaires(),
            // Destinations des liens du dashboard (la ressource Lignes financières
            // n'existe plus : tout pointe vers Contrats et Dossiers OPCO).
            'contratsUrl' => ContractResource::getUrl('index'),
            'opcoUrl' => OpcoFileResource::getUrl('index'),
        ];
    }
}
