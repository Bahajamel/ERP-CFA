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

        // Rattachement au référentiel local : correspondance insensible à la
        // casse d'abord (« AKTO » ↔ « Akto »), création sinon (référentiel
        // simple à un champ, enrichi au fil des détections).
        $opco = Opco::query()->whereRaw('LOWER(nom) = ?', [mb_strtolower($nom)])->first()
            ?? Opco::query()->create(['nom' => $nom]);

        return ['statut' => 'ok', 'opco' => $opco, 'nom' => $nom];
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
