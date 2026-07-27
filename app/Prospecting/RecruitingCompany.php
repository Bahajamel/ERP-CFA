<?php

namespace App\Prospecting;

/**
 * Entreprise « qui recrute en alternance » renvoyée par l'API La Bonne Alternance.
 * Objet de transfert immuable : découple le format de l'API de notre domaine.
 *
 * Le mapping depuis la réponse JSON est centralisé dans {@see self::fromApi()} :
 * c'est le seul endroit à ajuster si le schéma de l'API évolue.
 */
final class RecruitingCompany
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $siret,
        public readonly ?string $secteur,
        public readonly ?string $adresse,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?string $effectif,
        public readonly ?string $siteWeb,
        public readonly ?string $telephone,
        public readonly ?string $urlCandidature,
        public readonly string $source = 'La Bonne Alternance',
    ) {}

    /**
     * Construit le DTO depuis un élément « recruteur » de la réponse LBA.
     * Accès défensif (null-safe) car de nombreux champs sont facultatifs.
     *
     * @param  array<string, mixed>  $item
     */
    public static function fromApi(array $item): self
    {
        $coords = data_get($item, 'workplace.location.geopoint.coordinates', [null, null]); // [lon, lat]

        return new self(
            name: data_get($item, 'workplace.name')
                ?? data_get($item, 'workplace.brand')
                ?? data_get($item, 'workplace.legal_name')
                ?? 'Entreprise inconnue',
            siret: data_get($item, 'workplace.siret'),
            secteur: data_get($item, 'workplace.domain.naf.label'),
            adresse: data_get($item, 'workplace.location.address'),
            latitude: isset($coords[1]) ? (float) $coords[1] : null,
            longitude: isset($coords[0]) ? (float) $coords[0] : null,
            effectif: data_get($item, 'workplace.size'),
            siteWeb: data_get($item, 'workplace.website'),
            telephone: data_get($item, 'apply.phone'),
            urlCandidature: data_get($item, 'apply.url'),
        );
    }
}
