<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alertes financières : détection quotidienne des versements OPCO en retard.
Schedule::command('opco:flag-echeances')->dailyAt('06:00');

// Alertes proactives transverses (dossiers, signatures, OPCO, échéances).
Schedule::command('app:generer-alertes')->dailyAt('06:15');

// Corbeille candidats : purge définitive après 30 jours de rétention.
Schedule::command('candidats:purger-corbeille')->dailyAt('03:00');

// Essais gratuits : suspension des CFA dont l'essai est arrivé à échéance.
Schedule::command('essai:suspendre-expires')->dailyAt('02:00');

// Sauvegardes (base + fichiers) : nettoyage des anciennes puis nouvelle copie.
// Destination réglée par BACKUP_DISK (« local » en dev, « s3 » en prod).
Schedule::command('backup:clean')->dailyAt('01:30');
Schedule::command('backup:run')->dailyAt('01:45');
