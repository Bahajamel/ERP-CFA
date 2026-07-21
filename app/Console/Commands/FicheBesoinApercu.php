<?php

namespace App\Console\Commands;

use App\Documents\FicheBesoin;
use App\Models\Need;
use Illuminate\Console\Command;

/**
 * Écrit la fiche besoin d'une offre dans un fichier, pour la relire à l'écran
 * sans passer par l'interface.
 *
 *   php artisan fiche-besoin:apercu 19
 *   php artisan fiche-besoin:apercu 19 --sortie=C:/tmp/fiche.pdf
 */
class FicheBesoinApercu extends Command
{
    protected $signature = 'fiche-besoin:apercu {offre : Identifiant de l\'offre} {--sortie= : Chemin du PDF à écrire}';

    protected $description = 'Génère la fiche besoin d\'une offre dans un fichier PDF';

    public function handle(FicheBesoin $generateur): int
    {
        $need = Need::query()->tousLesCfa()->find((int) $this->argument('offre'));

        if ($need === null) {
            $this->error('Offre introuvable.');

            return self::FAILURE;
        }

        $pdf = $generateur->pour($need);
        $chemin = $this->option('sortie') ?: storage_path('app/'.$generateur->nomFichier($need));

        file_put_contents($chemin, $pdf);

        $this->info('Fiche besoin écrite : '.$chemin);
        $this->line(sprintf(
            '  offre #%d — %s (%s) · %s octets · %d page(s)',
            $need->getKey(),
            $need->intitule_poste,
            $need->company?->raison_sociale ?? 'entreprise inconnue',
            number_format(strlen($pdf), 0, ',', ' '),
            preg_match_all('#/Type\s*/Page[^s]#', $pdf),
        ));

        return self::SUCCESS;
    }
}
