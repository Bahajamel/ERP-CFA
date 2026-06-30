<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed de base de l'ERP :
     * - rôles & permissions + matrice d'accès (RolePermissionSeeder)
     * - compte administrateur (AdminUserSeeder)
     * - données de démonstration : utilisateurs par rôle + candidats, entreprises,
     *   besoins, contrats, OPCO, etc. (DemoSeeder)
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            DemoSeeder::class,
            DemoAccountsSeeder::class,
        ]);
    }
}
