<?php

use App\Filament\Widgets\ContratsSignesParMoisChart;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Courbe contrats : comparaison N-1
// ---------------------------------------------------------------------------

it('affiche 2 courbes en mode comparaison N-1, 1 seule sinon', function () {
    $chart = new ContratsSignesParMoisChart;

    $chart->filter = 'comparer';
    $avecCompare = Closure::bind(fn () => $this->getData(), $chart, $chart)();
    expect($avecCompare['datasets'])->toHaveCount(2);

    $chart->filter = 'simple';
    $sansCompare = Closure::bind(fn () => $this->getData(), $chart, $chart)();
    expect($sansCompare['datasets'])->toHaveCount(1);
});

it('propose les filtres de période sur la courbe', function () {
    $chart = new ContratsSignesParMoisChart;
    $filtres = Closure::bind(fn () => $this->getFilters(), $chart, $chart)();

    expect($filtres)->toHaveKeys(['simple', 'comparer']);
});
