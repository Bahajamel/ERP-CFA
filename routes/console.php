<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alertes financières : détection quotidienne des versements OPCO en retard.
Schedule::command('opco:flag-echeances')->dailyAt('06:00');

// Risque de rupture : recalcul des scores AVANT la génération des alertes.
Schedule::command('app:evaluer-risques')->dailyAt('05:45');

// Alertes proactives transverses (dossiers, signatures, OPCO, échéances, ruptures).
Schedule::command('app:generer-alertes')->dailyAt('06:15');
