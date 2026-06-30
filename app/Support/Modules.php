<?php

namespace App\Support;

/**
 * Référentiel des modules de l'ERP et de leurs permissions d'accès.
 * Permission par module : "access_<slug>" (ex. access_finance).
 */
class Modules
{
    /** slug => libellé français */
    public const LIST = [
        'candidates'  => 'Candidats',
        'companies'   => 'Entreprises',
        'needs'       => 'Besoins entreprises',
        'matching'    => 'Matching',
        'admissions'  => 'Admission',
        'documents'   => 'Documents',
        'contracts'   => 'Contrats',
        'opco'        => 'OPCO',
        'attendance'  => 'Assiduité',
        'finance'     => 'Finance',
        'quality'     => 'Qualité',
        'ruptures'    => 'Ruptures',
        'tasks'       => 'Tâches & alertes',
        'reports'     => 'Exports',
        'formations'  => 'Formations (référentiel)',
        'users'       => 'Utilisateurs',
    ];

    public static function slugs(): array
    {
        return array_keys(self::LIST);
    }

    public static function permission(string $slug): string
    {
        return "access_{$slug}";
    }
}
