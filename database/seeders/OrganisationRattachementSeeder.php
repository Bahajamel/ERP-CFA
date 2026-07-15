<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Rattache le personnel au CFA « maison », en fin de seeding (une fois tous les
 * comptes créés). Les seeders créent les utilisateurs via `User::firstOrCreate`
 * et non par factory : ils n'héritent donc pas du rattachement automatique.
 *
 * Un compte sans CFA passe canAccessPanel() mais getTenants() lui renvoie une
 * liste vide : il se connecte et n'a accès à aucun espace. Idempotent.
 */
class OrganisationRattachementSeeder extends Seeder
{
    public function run(): void
    {
        $organisation = Organisation::firstOrCreate(
            ['slug' => 'cfa-v2s'],
            ['nom' => config('cfa.nom', 'CFA V2S'), 'actif' => true],
        );

        User::query()
            ->whereDoesntHave('organisations')
            ->each(fn (User $user) => $organisation->users()->syncWithoutDetaching($user));
    }
}
