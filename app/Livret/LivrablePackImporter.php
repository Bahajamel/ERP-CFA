<?php

namespace App\Livret;

use App\Enums\DocumentSource;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Models\CfaMission;
use App\Models\Contract;
use App\Models\Document;
use App\Support\LivrableMissionMap;
use RuntimeException;
use ZipArchive;

/**
 * Importe un pack de livrables généré par LivretRS (archive ZIP) dans la GED.
 *
 * Chaque PDF de l'archive devient un Document rattaché à l'apprenti du contrat,
 * marqué « généré par LivretRS », avec le code livrable détecté depuis le nom
 * de fichier et les missions CFA suggérées (voir LivrableMissionMap — ces
 * missions restent modifiables ensuite). Aucune donnée du CERFA n'est renvoyée
 * vers LivretRS : on n'importe que le résultat déjà produit en local.
 */
class LivrablePackImporter
{
    public function import(Contract $contract, string $cheminZip, ?int $userId = null): LivrableImportResult
    {
        $candidate = $contract->candidate;

        if ($candidate === null) {
            throw new RuntimeException("Le contrat n'est rattaché à aucun apprenti : impossible d'importer les livrables.");
        }

        if (! is_file($cheminZip)) {
            throw new RuntimeException('Archive introuvable.');
        }

        $zip = new ZipArchive;
        if ($zip->open($cheminZip) !== true) {
            throw new RuntimeException("Impossible d'ouvrir l'archive ZIP.");
        }

        // Cache numéro de mission => id, pour rattacher sans requête par document.
        $missionIdsParNumero = CfaMission::query()->pluck('id', 'numero')->all();

        $result = new LivrableImportResult;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entree = $zip->getNameIndex($i);

                if ($entree === false || str_ends_with($entree, '/')) {
                    continue; // dossier
                }

                $nomFichier = basename($entree);

                if (! str_ends_with(strtolower($nomFichier), '.pdf')) {
                    $result->ignores[] = $nomFichier;

                    continue;
                }

                $contenu = $zip->getFromIndex($i);
                if ($contenu === false) {
                    $result->ignores[] = $nomFichier;

                    continue;
                }

                $code = LivrableMissionMap::detect($nomFichier);

                $document = Document::create([
                    'documentable_id' => $candidate->id,
                    'documentable_type' => $candidate->getMorphClass(),
                    'type' => DocumentType::DocumentQualite,
                    'source' => DocumentSource::LivretRs,
                    'statut' => DocumentStatut::Recu,
                    'livrable_code' => $code,
                    'nom_fichier' => $nomFichier,
                    'uploaded_by' => $userId,
                ]);

                $document->addMediaFromString($contenu)
                    ->usingFileName($nomFichier)
                    ->toMediaCollection('fichier');

                $result->importes++;

                if ($code !== null) {
                    $result->reconnus++;
                }

                $numeros = LivrableMissionMap::missionNumeros($code);
                $ids = array_values(array_filter(array_map(
                    fn (int $numero) => $missionIdsParNumero[$numero] ?? null,
                    $numeros,
                )));

                if ($ids !== []) {
                    $document->missions()->syncWithoutDetaching($ids);
                    $result->missionsRattachees += count($ids);
                }
            }
        } finally {
            $zip->close();
        }

        return $result;
    }
}
