<?php

namespace Database\Seeders;

use App\Models\Opco;
use Illuminate\Database\Seeder;

/**
 * Référentiel officiel des 11 opérateurs de compétences (réforme 2019,
 * agréés par le ministère du Travail — source France Compétences).
 * Idempotent : réutilise les OPCO déjà présents (mêmes libellés que le
 * jeu de démonstration). La détection automatique par SIRET (CFA Dock)
 * rattache ses résultats à ce référentiel via OpcoDetector.
 */
class OpcoSeeder extends Seeder
{
    /** @var array<string> Les 11 OPCO agréés. */
    public const OPCOS = [
        'AFDAS',            // Culture, médias, loisirs, sport
        'AKTO',             // Services à forte intensité de main-d'œuvre
        'OPCO Atlas',       // Services financiers et conseil
        'Constructys',      // Construction (BTP)
        'L\'Opcommerce',    // Commerce
        'OCAPIAT',          // Agriculture, pêche, agroalimentaire
        'OPCO 2i',          // Interindustriel
        'OPCO EP',          // Entreprises de proximité (artisanat, professions libérales)
        'OPCO Mobilités',   // Transport, logistique, services de l'automobile
        'OPCO Santé',       // Santé, médico-social
        'Uniformation',     // Cohésion sociale
    ];

    public function run(): void
    {
        foreach (self::OPCOS as $nom) {
            Opco::query()->firstOrCreate(['nom' => $nom]);
        }
    }
}
