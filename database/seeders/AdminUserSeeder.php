<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crée le compte administrateur par défaut (développement) et lui attribue
     * le rôle Administrateur. Nécessite que RolePermissionSeeder ait été joué.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@cfa-v2s.fr'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $admin->syncRoles(['Administrateur']);
    }
}
