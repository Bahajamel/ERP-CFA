<?php

namespace Database\Seeders;

use App\Support\Modules;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crée les 10 rôles, les permissions par module et la matrice d'accès (CDC §20).
 * Idempotent : peut être rejoué sans créer de doublon.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1) Permissions d'accès par module
        foreach (Modules::slugs() as $slug) {
            Permission::firstOrCreate(['name' => Modules::permission($slug)]);
        }

        $all = Modules::slugs();
        $allButUsers = array_values(array_diff($all, ['users']));

        // 2) Matrice rôle => modules accessibles ('*' = tous les modules)
        $matrix = [
            'Administrateur' => '*',
            'Direction'      => $allButUsers,
            'Commercial'     => ['candidates', 'companies', 'needs', 'matching', 'formations', 'tasks'],
            'Admission'      => ['candidates', 'admissions', 'documents', 'formations', 'tasks'],
            'Administratif'  => ['contracts', 'opco', 'documents', 'finance', 'tasks'],
            'Scolarité'      => ['attendance', 'formations', 'tasks'],
            'Pédagogie'      => ['candidates', 'admissions', 'attendance', 'quality', 'ruptures', 'formations', 'tasks'],
            'Finance'        => ['finance', 'contracts', 'opco', 'tasks'],
            'Qualité'        => ['quality', 'documents', 'tasks'],
            'Formateur'      => ['attendance', 'formations', 'tasks'],
        ];

        // 3) Création des rôles + affectation des permissions
        foreach ($matrix as $roleName => $modules) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $slugs = $modules === '*' ? $all : $modules;
            $role->syncPermissions(array_map(fn ($s) => Modules::permission($s), $slugs));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
