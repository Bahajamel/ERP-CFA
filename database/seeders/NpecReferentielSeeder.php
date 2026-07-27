<?php

namespace Database\Seeders;

use App\Models\NpecReferentiel;
use Illuminate\Database\Seeder;

/**
 * Amorce le référentiel NPEC avec un ÉCHANTILLON illustratif, permettant de
 * démontrer la détection automatique dans l'onglet Contrat.
 *
 * ⚠️ Les montants ci-dessous sont des VALEURS D'EXEMPLE (source explicitement
 * marquée) : ils NE remplacent PAS le référentiel officiel. Pour charger les
 * vraies valeurs France Compétences, utiliser la commande :
 *   php artisan npec:importer --file=... (CSV officiel data.gouv.fr)
 * L'import écrase l'échantillon pour les RNCP concernés (updateOrCreate).
 */
class NpecReferentielSeeder extends Seeder
{
    public function run(): void
    {
        $source = 'Exemple (remplacer par npec:importer)';

        $echantillon = [
            ['code_rncp' => '37873', 'code_idcc' => null, 'npec_annuel' => 7130, 'libelle' => 'Concepteur développeur d\'applications'],
            ['code_rncp' => '35080', 'code_idcc' => null, 'npec_annuel' => 6800, 'libelle' => 'BTS Services informatiques aux organisations'],
            ['code_rncp' => '34079', 'code_idcc' => null, 'npec_annuel' => 6500, 'libelle' => 'BTS Management commercial opérationnel'],
        ];

        foreach ($echantillon as $ligne) {
            NpecReferentiel::query()->updateOrCreate(
                ['code_rncp' => $ligne['code_rncp'], 'code_idcc' => $ligne['code_idcc']],
                ['npec_annuel' => $ligne['npec_annuel'], 'libelle' => $ligne['libelle'], 'source' => $source],
            );
        }
    }
}
