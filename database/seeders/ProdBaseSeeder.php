<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Socle de production : la base minimale et RÉELLE pour démarrer un CFA, SANS
 * aucune donnée de démonstration (ni candidats, ni entreprises, ni contrats
 * fictifs). C'est le seeder à jouer avant la mise en service / les vrais tests.
 *
 *   php artisan migrate:fresh --seed --seeder=Database\Seeders\ProdBaseSeeder
 *
 * Contient uniquement :
 *  - le CFA « maison » + son contexte tenant (OrganisationSeeder) — EN PREMIER ;
 *  - les rôles & permissions (RolePermissionSeeder) ;
 *  - le compte administrateur (AdminUserSeeder) ;
 *  - les référentiels officiels : indicateurs Qualiopi, missions L6231-2, OPCO ;
 *  - le rattachement du personnel au CFA (OrganisationRattachementSeeder) — EN DERNIER.
 *
 * Volontairement EXCLUS (données de démo) : DemoSeeder, DemoAccountsSeeder,
 * ClasseDemoSeeder. Pour repeupler une démo commerciale, passer plutôt par
 * DatabaseSeeder.
 *
 * ⚠️ L'ordre est significatif : OrganisationSeeder ouvre le contexte CFA en
 * mémoire (Filament::setTenant) pour tout le process ; le déplacer ferait naître
 * d'éventuelles données scopées avec organisation_id nul (donc invisibles).
 */
class ProdBaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            OrganisationSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            EditeurUserSeeder::class,
            QualiopiIndicatorSeeder::class,
            CfaMissionSeeder::class,
            OpcoSeeder::class,
            OrganisationRattachementSeeder::class,
        ]);
    }
}
