<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed de base de l'ERP :
     * - CFA « maison » + CFA courant du seeding (OrganisationSeeder) — EN PREMIER
     * - rôles & permissions + matrice d'accès (RolePermissionSeeder)
     * - compte administrateur (AdminUserSeeder)
     * - données de démonstration : utilisateurs par rôle + candidats, entreprises,
     *   besoins, contrats, OPCO, etc. (DemoSeeder)
     * - rattachement du personnel au CFA (OrganisationRattachementSeeder) — EN DERNIER
     */
    public function run(): void
    {
        $this->call([
            // Multi-tenant : ouvre le contexte CFA. Tout ce qui est créé ensuite
            // est rattaché automatiquement. Déplacer ce seeder plus bas ferait
            // naître les données à organisation_id nul, donc invisibles.
            OrganisationSeeder::class,

            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            QualiopiIndicatorSeeder::class,
            CfaMissionSeeder::class,
            OpcoSeeder::class,
            DemoSeeder::class,
            DemoAccountsSeeder::class,
            ClasseDemoSeeder::class,

            // Les comptes créés ci-dessus (firstOrCreate, hors factory) n'ont pas
            // de CFA : on les rattache une fois qu'ils existent tous.
            OrganisationRattachementSeeder::class,
        ]);
    }
}
