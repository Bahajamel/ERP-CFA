<?php

namespace App\Console\Commands;

use App\Models\NpecReferentiel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Importe le référentiel officiel des NPEC (France Compétences) dans la table
 * `npec_referentiels`. Source : fichier CSV publié par France Compétences /
 * data.gouv.fr (« Niveaux de prise en charge des contrats d'apprentissage »).
 *
 * Usage :
 *   php artisan npec:importer --file=storage/app/npec.csv
 *   php artisan npec:importer --url=https://.../referentiel-npec.csv
 *
 * Le parseur est tolérant sur les en-têtes : il repère les colonnes RNCP,
 * IDCC et NPEC quel que soit leur intitulé exact (accents / casse / libellés).
 */
class ImporterNpec extends Command
{
    protected $signature = 'npec:importer
        {--file= : Chemin d\'un CSV local}
        {--url= : URL d\'un CSV distant}
        {--separateur=; : Séparateur de colonnes}
        {--source=France Compétences : Étiquette de source enregistrée}';

    protected $description = 'Importe le référentiel NPEC (France Compétences) depuis un CSV';

    public function handle(): int
    {
        $contenu = $this->lireSource();

        if ($contenu === null) {
            $this->error('Aucune source lisible : précisez --file ou --url.');

            return self::FAILURE;
        }

        $lignes = preg_split('/\r\n|\r|\n/', trim($contenu)) ?: [];

        if (count($lignes) < 2) {
            $this->error('CSV vide ou sans données.');

            return self::FAILURE;
        }

        $sep = (string) $this->option('separateur');
        $entetes = array_map(fn ($c) => $this->cle($c), str_getcsv(array_shift($lignes), $sep));

        $iRncp = $this->colonne($entetes, ['rncp', 'coderncp', 'cod:rncp']);
        $iIdcc = $this->colonne($entetes, ['idcc', 'codeidcc']);
        $iNpec = $this->colonne($entetes, ['npec', 'valeurnpec', 'montantnpec', 'niveaupriseencharge']);
        $iLib = $this->colonne($entetes, ['libelle', 'intitule', 'certification']);

        if ($iRncp === null || $iNpec === null) {
            $this->error('Colonnes RNCP et/ou NPEC introuvables dans les en-têtes : '.implode(', ', $entetes));

            return self::FAILURE;
        }

        $source = (string) $this->option('source');
        $importes = 0;
        $ignores = 0;

        foreach ($lignes as $ligne) {
            if (trim($ligne) === '') {
                continue;
            }

            $cols = str_getcsv($ligne, $sep);
            $rncp = preg_replace('/\D/', '', $cols[$iRncp] ?? '');
            $npec = $this->montant($cols[$iNpec] ?? '');

            if ($rncp === '' || $npec === null) {
                $ignores++;

                continue;
            }

            $idcc = $iIdcc !== null ? preg_replace('/\D/', '', $cols[$iIdcc] ?? '') : '';
            $idcc = $idcc !== '' && ! in_array($idcc, ['9998', '9999'], true) ? $idcc : null;

            NpecReferentiel::query()->updateOrCreate(
                ['code_rncp' => $rncp, 'code_idcc' => $idcc],
                [
                    'npec_annuel' => $npec,
                    'libelle' => $iLib !== null ? ($cols[$iLib] ?? null) : null,
                    'source' => $source,
                ],
            );
            $importes++;
        }

        $this->info("Import terminé : {$importes} NPEC enregistrés, {$ignores} lignes ignorées.");

        return self::SUCCESS;
    }

    private function lireSource(): ?string
    {
        if ($fichier = $this->option('file')) {
            return is_file($fichier) ? (string) file_get_contents($fichier) : null;
        }

        if ($url = $this->option('url')) {
            $reponse = Http::timeout(60)->get($url);

            return $reponse->successful() ? $reponse->body() : null;
        }

        return null;
    }

    /** Normalise un en-tête pour comparaison (minuscules, sans accents ni séparateurs). */
    private function cle(string $entete): string
    {
        $sansAccents = iconv('UTF-8', 'ASCII//TRANSLIT', trim($entete)) ?: $entete;

        return preg_replace('/[^a-z0-9:]/', '', strtolower($sansAccents)) ?? '';
    }

    /**
     * @param  list<string>  $entetes
     * @param  list<string>  $candidats
     */
    private function colonne(array $entetes, array $candidats): ?int
    {
        foreach ($entetes as $i => $entete) {
            foreach ($candidats as $c) {
                if ($entete === $c || str_contains($entete, $c)) {
                    return $i;
                }
            }
        }

        return null;
    }

    /** Convertit « 8 070,00 € » / « 8070.00 » en float, ou null. */
    private function montant(string $brut): ?float
    {
        $normalise = str_replace([' ', "\u{a0}", '€'], '', $brut);
        $normalise = str_replace(',', '.', $normalise);
        $normalise = preg_replace('/[^0-9.]/', '', $normalise) ?? '';

        return $normalise !== '' ? (float) $normalise : null;
    }
}
