<?php

namespace App\Support;

use App\Models\Opco;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Détection automatique de l'OPCO d'une entreprise à partir de son SIRET,
 * via l'API officielle France Compétences (SIRO — le backend du service
 * public « Quel est mon OPCO », quel-est-mon-opco.francecompetences.fr).
 * Clé d'API publique (embarquée dans le site officiel), configurable via
 * services.francecompetences. En cas d'indisponibilité, la sélection
 * manuelle reste possible : la détection ne bloque jamais la création.
 */
class OpcoDetector
{
    /** Supprime tout ce qui n'est pas un chiffre (espaces, points…). */
    public static function normaliserSiret(?string $siret): string
    {
        return preg_replace('/\D/', '', (string) $siret) ?? '';
    }

    /** Un SIRET est valide s'il comporte exactement 14 chiffres. */
    public static function siretValide(?string $siret): bool
    {
        return preg_match('/^\d{14}$/', self::normaliserSiret($siret)) === 1;
    }

    /**
     * Détecte l'OPCO et le rattache au référentiel local.
     *
     * @return array{statut: 'ok'|'introuvable'|'indisponible', opco: ?Opco, nom: ?string}
     */
    public function detecter(?string $siret): array
    {
        $siret = self::normaliserSiret($siret);

        if (! self::siretValide($siret)) {
            return ['statut' => 'introuvable', 'opco' => null, 'nom' => null];
        }

        $reponse = $this->appel($siret);

        if ($reponse === null || $reponse->serverError()) {
            return ['statut' => 'indisponible', 'opco' => null, 'nom' => null];
        }

        // Réponse SIRO : { etat, siret, idcc, opcoDsn: {code, nom}, opcoGestion: {code, nom} }.
        // L'OPCO de gestion prime s'il est renseigné, sinon l'OPCO déclaré en DSN.
        $nom = $this->nomValide($reponse->json('opcoGestion.nom'))
            ?? $this->nomValide($reponse->json('opcoDsn.nom'));

        if (! $reponse->successful() || $nom === null) {
            return ['statut' => 'introuvable', 'opco' => null, 'nom' => null];
        }

        $opco = $this->rattacher($nom);

        return ['statut' => 'ok', 'opco' => $opco, 'nom' => $opco->nom];
    }

    /**
     * Rattache le libellé renvoyé par CFA Dock au référentiel local des
     * 11 OPCO. CFA Dock renvoie souvent des libellés longs (« Opco
     * entreprises et salariés des services à forte intensité de
     * main-d'œuvre » = AKTO) : on passe par des mots-clés discriminants,
     * puis par une correspondance exacte insensible à la casse, et en
     * dernier recours on crée l'entrée (référentiel enrichi, jamais bloqué).
     */
    private function rattacher(string $nom): Opco
    {
        $normalise = $this->normaliserLibelle($nom);

        // Mots-clés discriminants → libellé canonique du référentiel
        // (les plus spécifiques d'abord).
        $alias = [
            'afdas' => 'AFDAS',
            'akto' => 'AKTO',
            'forte intensite' => 'AKTO',
            'atlas' => 'OPCO Atlas',
            'constructys' => 'Constructys',
            'opcommerce' => 'L\'Opcommerce',
            'commerce' => 'L\'Opcommerce',
            'ocapiat' => 'OCAPIAT',
            '2i' => 'OPCO 2i',
            'interindustriel' => 'OPCO 2i',
            'proximite' => 'OPCO EP',
            'mobilite' => 'OPCO Mobilités',
            'sante' => 'OPCO Santé',
            'uniformation' => 'Uniformation',
            'cohesion sociale' => 'Uniformation',
        ];

        foreach ($alias as $motCle => $canonique) {
            if (str_contains($normalise, $motCle)) {
                return Opco::query()->firstOrCreate(['nom' => $canonique]);
            }
        }

        return Opco::query()->whereRaw('LOWER(nom) = ?', [mb_strtolower($nom)])->first()
            ?? Opco::query()->create(['nom' => $nom]);
    }

    /** Minuscules sans accents ni ponctuation, pour la recherche de mots-clés. */
    private function normaliserLibelle(string $nom): string
    {
        $sansAccents = strtr(mb_strtolower($nom), [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', '’' => ' ', "'" => ' ', '-' => ' ',
        ]);

        return trim(preg_replace('/\s+/', ' ', $sansAccents) ?? $sansAccents);
    }

    /** « N/C » (non communiqué) et vides ne comptent pas comme un OPCO. */
    private function nomValide(mixed $nom): ?string
    {
        $nom = trim((string) $nom);

        return ($nom === '' || strtoupper($nom) === 'N/C') ? null : $nom;
    }

    /** Appel HTTP, avec repli sans vérification SSL en local (poste sans bundle CA). */
    private function appel(string $siret): ?Response
    {
        $url = rtrim(config('services.francecompetences.siro_url'), '/')."/nico/siret/{$siret}";
        $headers = [
            'Accept' => 'application/json',
            'X-Gravitee-Api-Key' => config('services.francecompetences.siro_key'),
        ];

        try {
            return Http::timeout(8)->withHeaders($headers)->get($url);
        } catch (Throwable $e) {
            if (app()->environment('local')) {
                try {
                    return Http::timeout(8)->withHeaders($headers)->withoutVerifying()->get($url);
                } catch (Throwable) {
                    return null;
                }
            }

            report($e);

            return null;
        }
    }
}
