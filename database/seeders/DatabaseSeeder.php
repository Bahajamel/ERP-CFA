<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed de base de l'ERP : rôles & permissions, administrateur, et un compte
     * de démonstration par rôle (mot de passe : "password").
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
        ]);

        // Un utilisateur de démonstration par rôle (hors Administrateur, déjà créé)
        $demoRoles = [
            'Direction'     => 'direction',
            'Commercial'    => 'commercial',
            'Admission'     => 'admission',
            'Administratif' => 'administratif',
            'Scolarité'     => 'scolarite',
            'Pédagogie'     => 'pedagogie',
            'Finance'       => 'finance',
            'Qualité'       => 'qualite',
            'Formateur'     => 'formateur',
        ];

        foreach ($demoRoles as $role => $slug) {
            $user = User::firstOrCreate(
                ['email' => "{$slug}@cfa-v2s.fr"],
                [
                    'name' => $role,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );
            $user->syncRoles([$role]);
        }
    }
}
