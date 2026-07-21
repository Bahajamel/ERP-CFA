<?php

namespace App\Console\Commands;

use Database\Seeders\DemoFicheBesoinSeeder;
use Illuminate\Console\Command;

/**
 * Jeu d'essai de la fiche besoin publique : cinq entreprises « Prospect » ayant
 * déposé un besoin en attente de validation.
 *
 *   php artisan demo:fiche-besoin              → crée le jeu d'essai (rejouable)
 *   php artisan demo:fiche-besoin --nettoyer   → le retire entièrement
 */
class DemoFicheBesoin extends Command
{
    protected $signature = 'demo:fiche-besoin {--nettoyer : Supprime le jeu d\'essai au lieu de le créer}';

    protected $description = 'Crée (ou retire) un jeu d\'essai de besoins déposés par des entreprises';

    public function handle(): int
    {
        $seeder = new DemoFicheBesoinSeeder;
        $seeder->setCommand($this);

        if ($this->option('nettoyer')) {
            $seeder->nettoyer();

            return self::SUCCESS;
        }

        $seeder->run();

        return self::SUCCESS;
    }
}
