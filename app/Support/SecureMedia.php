<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Génère des liens sécurisés vers les pièces sensibles (données personnelles /
 * NIR). Ces pièces vivent sur un disque privé (cf. config/documents.php) et ne
 * sont jamais accessibles par une URL publique devinable : le seul accès est la
 * route `documents.securise`, protégée par authentification ET signature à
 * expiration. Ce helper produit ces URL signées et temporaires.
 */
class SecureMedia
{
    /** Collections média considérées comme sensibles (servies en privé). */
    public const COLLECTIONS_SENSIBLES = [
        'piece_identite',
        'carte_vitale',
        'attestation_projet',
        'cv',
        'cerfa',
        'fichier',
        'justificatif',
        'signature',
        'cachet',
    ];

    /** URL signée temporaire vers une pièce média précise (null si absente). */
    public static function url(?Media $media): ?string
    {
        if ($media === null) {
            return null;
        }

        return URL::temporarySignedRoute(
            'documents.securise',
            now()->addMinutes(config('documents.lien_expiration_minutes', 15)),
            ['media' => $media->getKey()],
        );
    }

    /** URL signée vers la première pièce d'une collection d'un modèle (ou null). */
    public static function pour(HasMedia $modele, string $collection): ?string
    {
        return self::url($modele->getFirstMedia($collection));
    }

    /** Une collection donnée doit-elle être stockée sur le disque privé ? */
    public static function estSensible(string $collection): bool
    {
        return in_array($collection, self::COLLECTIONS_SENSIBLES, true);
    }
}
