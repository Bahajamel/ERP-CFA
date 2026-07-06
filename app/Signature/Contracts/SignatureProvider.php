<?php

namespace App\Signature\Contracts;

use App\Models\SignatureRequest;

/**
 * Contrat d'un prestataire de signature électronique (EPIC-08).
 *
 * Toute intégration eIDAS réelle (Yousign, Docaposte Contralia, Universign…) se
 * branche ici sans toucher au reste de l'application : le SignatureService et
 * l'UI ne connaissent que cette interface. Le driver actif est choisi dans
 * config/signature.php.
 */
interface SignatureProvider
{
    /** Identifiant technique du driver (ex. « simulation », « yousign »). */
    public function nom(): string;

    /** La signature électronique est-elle réellement disponible via ce driver ? */
    public function estActif(): bool;

    /**
     * Transmet l'enveloppe au prestataire et renvoie l'identifiant d'enveloppe
     * (external_id). Le prestataire notifie ensuite les signatures via webhook ;
     * en simulation, la signature est déclenchée manuellement.
     */
    public function envoyer(SignatureRequest $request): string;

    /** Annule une enveloppe en cours chez le prestataire (best-effort). */
    public function annuler(SignatureRequest $request): void;
}
