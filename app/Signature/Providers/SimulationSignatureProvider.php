<?php

namespace App\Signature\Providers;

use App\Models\SignatureRequest;
use App\Signature\Contracts\SignatureProvider;

/**
 * Driver de DÉMONSTRATION : simule un prestataire de signature sans appel réseau.
 *
 * L'envoi génère un identifiant d'enveloppe local ; la signature de chaque partie
 * est ensuite déclenchée à la main (action « Simuler une signature »). Permet de
 * dérouler tout le parcours multi-parties en démo et en test, SANS valeur
 * probante eIDAS. À remplacer par un prestataire qualifié en production.
 */
class SimulationSignatureProvider implements SignatureProvider
{
    public function nom(): string
    {
        return 'simulation';
    }

    public function estActif(): bool
    {
        return true;
    }

    public function envoyer(SignatureRequest $request): string
    {
        // Identifiant d'enveloppe déterministe (pas de Date::now()/random requis).
        return 'SIMU-'.$request->getKey();
    }

    public function annuler(SignatureRequest $request): void
    {
        // Rien à faire : aucune enveloppe distante n'existe en simulation.
    }
}
