<?php

namespace App\Livret;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client HTTP du service de génération LivretRS (moteur core/ exposé en HTTP,
 * stateless). Envoie un payload structuré (aucun CERFA, minimisation du NIR),
 * reçoit l'archive ZIP des livrables et l'écrit dans un fichier temporaire dont
 * le chemin est renvoyé à l'appelant (charge ensuite à lui de l'importer puis
 * de le supprimer).
 */
class LivretRsClient
{
    public function estConfigure(): bool
    {
        return filled(config('services.livretrs.url'));
    }

    /**
     * Recherche intelligente d'un CFA par nom (sources officielles gratuites :
     * recherche-entreprises.api.gouv.fr + liste publique DGEFP, sans clé).
     * Renvoie une liste de candidats (nom, siren, siret, naf, nda, adresse…).
     *
     * @throws LivretRsException
     */
    public function rechercherCfa(string $nom): array
    {
        $base = rtrim((string) config('services.livretrs.url'), '/');

        if ($base === '') {
            throw new LivretRsException('Service LivretRS non configuré (LIVRETRS_URL vide).');
        }

        try {
            $reponse = Http::timeout(20)
                ->acceptJson()
                ->get($base.'/search-cfa', ['nom' => $nom]);
        } catch (Throwable $e) {
            throw new LivretRsException('Service LivretRS injoignable : '.$e->getMessage(), previous: $e);
        }

        if (! $reponse->successful()) {
            throw new LivretRsException('Recherche CFA : le service a répondu '.$reponse->status().'.');
        }

        return $reponse->json('results') ?? [];
    }

    /**
     * Demande la génération et renvoie le chemin d'un ZIP temporaire.
     *
     * @throws LivretRsException
     */
    public function genererLivrables(array $payload): string
    {
        $base = rtrim((string) config('services.livretrs.url'), '/');

        if ($base === '') {
            throw new LivretRsException('Service LivretRS non configuré (LIVRETRS_URL vide).');
        }

        try {
            $reponse = Http::timeout((int) config('services.livretrs.timeout', 180))
                ->acceptJson()
                ->asJson()
                ->post($base.'/generate', $payload);
        } catch (Throwable $e) {
            throw new LivretRsException('Service LivretRS injoignable : '.$e->getMessage(), previous: $e);
        }

        if (! $reponse->successful()) {
            $detail = (string) $reponse->body();
            throw new LivretRsException(
                'Le service LivretRS a répondu '.$reponse->status().
                ($detail !== '' ? ' : '.mb_strimwidth($detail, 0, 300, '…') : '.')
            );
        }

        $corps = $reponse->body();

        if ($corps === '') {
            throw new LivretRsException('Le service LivretRS a renvoyé une archive vide.');
        }

        $chemin = tempnam(sys_get_temp_dir(), 'livretrs').'.zip';
        file_put_contents($chemin, $corps);

        return $chemin;
    }
}
