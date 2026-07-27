<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Qualiopi\ReferentielQualiopi;
use Illuminate\Database\Seeder;

/**
 * Charge le référentiel Qualiopi (7 critères / 32 indicateurs du RNQ) POUR
 * CHAQUE CFA : l'état de conformité est désormais cloisonné par organisation,
 * donc chaque CFA possède ses propres 32 lignes.
 *
 * Idempotent : rafraîchit les libellés de référence sans écraser l'état de
 * conformité (statut, responsable, commentaire) déjà saisi. Le texte des
 * indicateurs vit dans {@see ReferentielQualiopi} (partagé avec le provisioning
 * d'un nouveau CFA).
 */
class QualiopiIndicatorSeeder extends Seeder
{
    public function run(): void
    {
        Organisation::query()->each(
            fn (Organisation $organisation) => ReferentielQualiopi::provisionner($organisation->id),
        );
    }
}
