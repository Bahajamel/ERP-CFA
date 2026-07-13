<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crée le CFA « maison » (V2S) comme organisation par défaut et y rattache tout
 * le personnel existant. Idempotent : rejouable sans doublon. À terme, chaque
 * nouveau CFA client sera une organisation supplémentaire.
 */
class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $organisation = Organisation::firstOrCreate(
            ['slug' => 'cfa-v2s'],
            ['nom' => config('cfa.nom', 'CFA V2S'), 'actif' => true],
        );

        // Rattache tout utilisateur pas encore lié à une organisation.
        User::query()
            ->whereDoesntHave('organisations')
            ->each(fn (User $user) => $organisation->users()->syncWithoutDetaching($user));
    }
}
