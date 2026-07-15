<?php

use App\Finance\FinanceDashboardData;
use App\Models\Company;
use App\Models\FinanceLine;
use App\Models\Invoice;
use App\Models\Organisation;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Qualité des données de démonstration du dashboard Finance.
 *
 * FinanceDashboardData se replie sur des factures FICTIVES quand la base n'en
 * contient aucune. Résultat en démo : les cartes annonçaient « 0 € facturé »
 * pendant que la table listait juste en dessous cinq factures inventées, émises
 * à des sociétés introuvables ailleurs dans l'outil. L'écran se contredisait.
 */
beforeEach(function () {
    Filament::setTenant(null, isQuiet: true);
    $this->seed(DatabaseSeeder::class);
    Filament::setTenant(Organisation::where('slug', 'cfa-v2s')->sole(), isQuiet: true);
});

it('facture et encaisse réellement dans les données de démonstration', function () {
    $kpis = collect(app(FinanceDashboardData::class)->kpis())->keyBy('key');

    expect(Invoice::query()->count())->toBeGreaterThan(0)
        ->and($kpis['facture']['valeur'])->toBeGreaterThan(0)
        ->and($kpis['encaisse']['valeur'])->toBeGreaterThan(0)
        // Une facture échue impayée : alimente le récit « relance / recouvrement ».
        ->and($kpis['retard']['valeur'])->toBeGreaterThan(0);
});

it('n’affiche que des factures adossées à de vraies entreprises', function () {
    $raisonsSociales = Company::query()->pluck('raison_sociale')->all();

    $entreprisesFacturees = collect(app(FinanceDashboardData::class)->facturesRecentes())
        ->pluck('entreprise')
        ->unique();

    // Le repli de démonstration invente « Restaurant Alpha », « Boulangerie Beta »…
    // Si l'une de ces sociétés apparaît, c'est qu'aucune facture réelle n'existe.
    expect($entreprisesFacturees)->not->toBeEmpty();
    $entreprisesFacturees->each(
        fn (string $e) => expect($raisonsSociales)->toContain($e)
    );
});

it('accorde le montant bloqué avec le nombre de dossiers OPCO bloqués', function () {
    $kpis = collect(app(FinanceDashboardData::class)->kpis())->keyBy('key');

    // Annoncer « 9 200 € bloqués » et « 0 dossier bloqué » laisse l'utilisateur
    // sans porte de sortie : le montant doit désigner un dossier à débloquer.
    expect($kpis['bloque']['valeur'])->toBeGreaterThan(0)
        ->and($kpis['opco']['valeur'])->toBeGreaterThan(0);
});

it('ne finance pas deux fois le même contrat', function () {
    $doublons = FinanceLine::query()
        ->whereNotNull('contract_id')
        ->get()
        ->groupBy('contract_id')
        ->filter(fn ($lignes) => $lignes->count() > 1);

    // Une ligne est créée automatiquement à l'acceptation OPCO : en créer une
    // seconde à la main gonflait le « Montant attendu » de 8 000 €.
    expect($doublons)->toBeEmpty();
});
