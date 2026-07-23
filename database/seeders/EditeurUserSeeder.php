<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crée le compte « éditeur » de développement — l'exploitant de la solution,
 * qui gère les CFA clients et traite les demandes de démonstration.
 *
 * Le panneau /editeur existait sans qu'aucun compte n'y ait accès : sans ce
 * seeder, ni les CFA clients ni les demandes de démo n'étaient consultables.
 * Nécessite que RolePermissionSeeder ait été joué (rôle « Éditeur »).
 *
 * ⚠️ Mot de passe de développement, comme AdminUserSeeder : à changer avant
 * toute mise en production.
 */
class EditeurUserSeeder extends Seeder
{
    public function run(): void
    {
        $editeur = User::firstOrCreate(
            ['email' => 'editeur@meridian-cfa.fr'],
            [
                'name' => 'Éditeur Meridian',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );

        $editeur->syncRoles(['Éditeur']);

        $this->command?->info('Compte éditeur : editeur@meridian-cfa.fr / password');
    }
}
