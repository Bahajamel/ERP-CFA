<?php

namespace Database\Seeders;

use App\Models\Organisation;
use Filament\Facades\Filament;
use Illuminate\Database\Seeder;

/**
 * Crée le CFA « maison » (V2S) et l'établit comme CFA courant pour la suite du
 * seeding. Idempotent : rejouable sans doublon.
 *
 * ⚠️ Doit tourner EN PREMIER. Les seeders s'exécutent hors panel : sans CFA
 * courant, le trait BelongsToOrganisation n'a rien à rattacher et toutes les
 * données naissent avec `organisation_id` nul — donc invisibles de tous les CFA.
 * C'est ce qui vidait intégralement la démo sur un `migrate:fresh --seed`.
 *
 * Le rattachement du personnel a lieu en fin de course (voir
 * OrganisationRattachementSeeder) : les comptes n'existent pas encore ici.
 */
class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $organisation = Organisation::firstOrCreate(
            ['slug' => 'cfa-v2s'],
            ['nom' => config('cfa.nom', 'CFA V2S'), 'actif' => true],
        );

        Filament::setCurrentPanel('admin');
        Filament::setTenant($organisation, isQuiet: true);
    }
}
