<?php

namespace App\Support;

use App\Models\Opco;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Détection automatique de l'OPCO d'une entreprise à partir de son SIRET,
 * via l'API publique CFA Dock (France Compétences) — gratuite, sans clé.
 * En cas d'indisponibilité, la sélection manuelle reste toujours possible
 * (la détection ne bloque jamais la création de l'entreprise).
 */
class OpcoDetector
{
    private const ENDPOINT = 'https://www.cfadock.fr/api/opcos';

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

        if ($reponse === null) {
            return ['statut' => 'indisponible', 'opco' => null, 'nom' => null];
        }

        $nom = trim((string) $reponse->json('opcoName', ''));

        if (! $reponse->successful() || strtoupper((string) $reponse->json('searchStatus')) !== 'OK' || $nom === '') {
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

    /** Appel HTTP, avec repli sans vérification SSL en local (poste sans bundle CA). */
    private function appel(string $siret): ?Response
    {
        try {
            return Http::timeout(6)->get(self::ENDPOINT, ['siret' => $siret]);
        } catch (Throwable $e) {
            if (app()->environment('local')) {
                try {
                    return Http::timeout(6)->withoutVerifying()->get(self::ENDPOINT, ['siret' => $siret]);
                } catch (Throwable) {
                    return null;
                }
            }

            report($e);

            return null;
        }
    }
}
