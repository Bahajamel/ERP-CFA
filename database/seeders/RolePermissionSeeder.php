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

    /** Permissions granulaires de la couche « tables personnalisées » (façon Monday). */
    public const PERMISSIONS_PERSONNALISATION = [
        'custom_tables.view',
        'custom_tables.create',
        'custom_tables.update',
        'custom_tables.delete',
        'custom_records.view',
        'custom_records.create',
        'custom_records.update',
        'custom_records.delete',
    ];

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
            'Direction' => $allButUsers,
            'Commercial' => ['candidates', 'companies', 'needs', 'matching', 'formations', 'tasks', 'reports'],
            'Admission' => ['candidates', 'admissions', 'documents', 'formations', 'tasks', 'reports'],
            'Administratif' => ['contracts', 'opco', 'documents', 'finance', 'ruptures', 'tasks', 'reports'],
            'Scolarité' => ['attendance', 'formations', 'tasks', 'reports'],
            'Pédagogie' => ['candidates', 'admissions', 'attendance', 'quality', 'ruptures', 'formations', 'tasks', 'reports'],
            'Finance' => ['finance', 'contracts', 'opco', 'tasks', 'reports'],
            'Qualité' => ['quality', 'documents', 'tasks'],
            'Formateur' => ['attendance', 'formations', 'tasks'],
        ];

        // 3) Création des rôles + affectation des permissions
        foreach ($matrix as $roleName => $modules) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $slugs = $modules === '*' ? $all : $modules;
            $role->syncPermissions(array_map(fn ($s) => Modules::permission($s), $slugs));
        }

        // 3 bis) Personnalisation « façon Monday » : permissions granulaires sur les
        // tables et lignes personnalisées, indépendantes des modules métier. Elles
        // ne sont accordées qu'aux rôles de pilotage — un CFA n'ouvre l'accès aux
        // autres rôles que s'il le décide (rien n'est ouvert automatiquement).
        foreach (self::PERMISSIONS_PERSONNALISATION as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Administrateur : tout (y compris suppression définitive des tables).
        Role::findByName('Administrateur')->givePermissionTo(self::PERMISSIONS_PERSONNALISATION);

        // Direction & Commercial : tout sauf la suppression d'une table. Le
        // Commercial pilote au quotidien ses tableaux Candidats (créer, choisir les
        // colonnes et les options des listes, saisir des lignes) ; l'apprenant, via
        // le lien public, ne fait que recevoir les listes figées choisies par eux.
        foreach (['Direction', 'Commercial'] as $role) {
            Role::findByName($role)->givePermissionTo(
                array_values(array_diff(self::PERMISSIONS_PERSONNALISATION, ['custom_tables.delete'])),
            );
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
