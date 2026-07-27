<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/**
 * Vérifie le CÂBLAGE des sauvegardes (spatie/laravel-backup) — pas l'exécution
 * réelle d'un dump (qui exige pg_dump et une vraie base, indisponibles en test).
 */
it('enregistre les commandes de sauvegarde', function () {
    $commandes = array_keys(Artisan::all());

    expect($commandes)->toContain('backup:run')
        ->and($commandes)->toContain('backup:clean')
        ->and($commandes)->toContain('backup:list');
});

it('sauvegarde la base configurée, sur le disque piloté par l\'environnement', function () {
    // Par défaut : disque local ; en prod on bascule via BACKUP_DISK.
    expect(config('backup.backup.destination.disks'))->toBe(['local'])
        // La base sauvegardée suit la connexion de l'application.
        ->and(config('backup.backup.source.databases'))->toBe([config('database.default')]);
});

it('planifie une sauvegarde quotidienne et son nettoyage', function () {
    $planifiees = collect(app(Schedule::class)->events())
        ->map(fn ($e) => $e->command)
        ->implode(' ');

    expect($planifiees)->toContain('backup:run')
        ->and($planifiees)->toContain('backup:clean');
});
