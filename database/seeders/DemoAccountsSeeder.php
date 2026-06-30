<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration prévisibles : un par rôle, au format
 * <role>@cfa-v2s.fr / mot de passe « password ». Pratique pour tester la
 * visibilité par rôle. Idempotent (firstOrCreate par email).
 *
 * NB : le mot de passe est passé en clair ; le cast 'password' => 'hashed' du
 * modèle User le hache automatiquement (sans double hachage).
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Administrateur', 'admin@cfa-v2s.fr',         'Administrateur'],
            ['Direction',      'direction@cfa-v2s.fr',     'Direction'],
            ['Commercial',     'commercial@cfa-v2s.fr',    'Commercial'],
            ['Admission',      'admission@cfa-v2s.fr',     'Admission'],
            ['Administratif',  'administratif@cfa-v2s.fr', 'Administratif'],
            ['Scolarité',      'scolarite@cfa-v2s.fr',     'Scolarité'],
            ['Pédagogie',      'pedagogie@cfa-v2s.fr',     'Pédagogie'],
            ['Finance',        'finance@cfa-v2s.fr',       'Finance'],
            ['Qualité',        'qualite@cfa-v2s.fr',       'Qualité'],
            ['Formateur',      'formateur@cfa-v2s.fr',     'Formateur'],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'is_active' => true],
            );

            $user->syncRoles([$role]);
        }
    }
}
