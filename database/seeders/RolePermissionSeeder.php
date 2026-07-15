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
    /** Rôle et permission de l'exploitant de la solution (panneau /editeur). */
    public const ROLE_EDITEUR = 'Éditeur';

    public const PERMISSION_EDITEUR = 'access_editeur';

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
            'Commercial'     => ['candidates', 'companies', 'needs', 'matching', 'formations', 'tasks', 'reports'],
            'Admission'      => ['candidates', 'admissions', 'documents', 'formations', 'tasks', 'reports'],
            'Administratif'  => ['contracts', 'opco', 'documents', 'finance', 'ruptures', 'tasks', 'reports'],
            'Scolarité'      => ['attendance', 'formations', 'tasks', 'reports'],
            'Pédagogie'      => ['candidates', 'admissions', 'attendance', 'quality', 'ruptures', 'formations', 'tasks', 'reports'],
            'Finance'        => ['finance', 'contracts', 'opco', 'tasks', 'reports'],
            'Qualité'        => ['quality', 'documents', 'tasks'],
            'Formateur'      => ['attendance', 'formations', 'tasks'],
        ];

        // 3) Création des rôles + affectation des permissions
        foreach ($matrix as $roleName => $modules) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $slugs = $modules === '*' ? $all : $modules;
            $role->syncPermissions(array_map(fn ($s) => Modules::permission($s), $slugs));
        }

        // 4) Rôle « Éditeur » : nous, exploitant de la solution — hors matrice CFA.
        // Volontairement absent de $matrix : « Administrateur » => '*' ne couvre que
        // les modules, un administrateur de CFA n'hérite donc jamais de ce pouvoir.
        // Seul ce rôle ouvre le panneau /editeur (création/suspension des CFA).
        Permission::firstOrCreate(['name' => self::PERMISSION_EDITEUR]);
        Role::firstOrCreate(['name' => self::ROLE_EDITEUR])
            ->syncPermissions([self::PERMISSION_EDITEUR]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
