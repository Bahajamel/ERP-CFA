<?php

namespace Database\Seeders;

use App\Models\Organisation;
use Filament\Facades\Filament;
use Illuminate\Database\Seeder;

/**
 * Crée le CFA « maison » (V2S) et l'établit comme CFA courant pour la suite du
 * seeding. Idempotent : rejouable sans doublon.
 *
 * ⚠️ Doit tourner EN PREMIER. Les seeders s'exécutent hors panel : sans CFA
 * courant, le trait BelongsToOrganisation n'a rien à rattacher et toutes les
 * données naissent avec `organisation_id` nul — donc invisibles de tous les CFA.
 * C'est ce qui vidait intégralement la démo sur un `migrate:fresh --seed`.
 *
 * Le rattachement du personnel a lieu en fin de course (voir
 * OrganisationRattachementSeeder) : les comptes n'existent pas encore ici.
 */
class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $organisation = Organisation::firstOrCreate(
            ['slug' => 'cfa-v2s'],
            ['nom' => config('cfa.nom', 'CFA V2S'), 'actif' => true],
        );

        $this->doterIdentite($organisation);

        Filament::setCurrentPanel('admin');
        Filament::setTenant($organisation, isQuiet: true);
    }

    /**
     * Reprend l'identité du CFA depuis config/cfa.php (variables d'environnement).
     *
     * On n'invente RIEN : un SIRET ou un représentant légal de démonstration
     * s'imprimerait tel quel sur de vrais CERFA déposés à l'OPCO et à l'État,
     * sans que personne ne le remarque. Non configuré = laissé vide, et l'envoi
     * des documents reste bloqué avec un message qui dit quoi remplir et où.
     *
     * Ne remplace jamais une valeur déjà saisie dans la Fiche du CFA.
     */
    private function doterIdentite(Organisation $organisation): void
    {
        $depuisConfig = array_filter([
            'raison_sociale' => config('cfa.raison_sociale'),
            'siret' => config('cfa.siret'),
            'siren' => config('cfa.siren'),
            'naf' => config('cfa.naf'),
            'nda' => config('cfa.nda'),
        ], fn ($valeur): bool => filled($valeur));

        $aRemplir = array_filter(
            $depuisConfig,
            fn (string $colonne): bool => blank($organisation->{$colonne}),
            ARRAY_FILTER_USE_KEY,
        );

        if ($aRemplir !== []) {
            $organisation->update($aRemplir);
        }
    }
}
