<?php

namespace App\Livret;

use App\Models\Candidate;
use ZipArchive;

/**
 * Construit une archive ZIP de tous les livrables générés par LivretRS pour un
 * apprenti — l'équivalent du « dossier de sortie » du logiciel desktop.
 */
class LivrablesArchive
{
    /** Chemin d'un ZIP temporaire des livrables de l'apprenti, ou null si aucun. */
    public function pour(Candidate $candidate): ?string
    {
        $documents = $candidate->documents()
            ->where('source', 'livretrs')
            ->get()
            ->filter(fn ($document) => $document->getFirstMedia('fichier') !== null);

        if ($documents->isEmpty()) {
            return null;
        }

        $chemin = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('livrables_', true).'.zip';

        $zip = new ZipArchive;
        $zip->open($chemin, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $nomsUtilises = [];
        foreach ($documents as $document) {
            $media = $document->getFirstMedia('fichier');
            if ($media === null || ! is_file($media->getPath())) {
                continue;
            }

            // Évite les collisions de noms dans l'archive.
            $nom = $media->file_name;
            if (isset($nomsUtilises[$nom])) {
                $nom = $document->id.'_'.$nom;
            }
            $nomsUtilises[$nom] = true;

            $zip->addFile($media->getPath(), $nom);
        }

        $zip->close();

        return $chemin;
    }
}
