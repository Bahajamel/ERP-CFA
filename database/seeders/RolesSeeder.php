<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Crée les 10 rôles métier de l'ERP (cf. CDC §20).
     */
    public function run(): void
    {
        $roles = [
            'Administrateur',
            'Direction',
            'Commercial',
            'Admission',
            'Administratif',
            'Scolarité',
            'Pédagogie',
            'Finance',
            'Qualité',
            'Formateur',
        ];

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
