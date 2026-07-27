<?php

namespace App\Signature\Providers;

use App\Models\SignatureRequest;
use App\Signature\Contracts\SignatureProvider;
use RuntimeException;

/**
 * Driver « none » : signature électronique désactivée. Le workflow reste
 * manuel (on marque le contrat « signé » à la main). Toute tentative d'envoi
 * échoue explicitement.
 */
class NullSignatureProvider implements SignatureProvider
{
    public function nom(): string
    {
        return 'none';
    }

    public function estActif(): bool
    {
        return false;
    }

    public function envoyer(SignatureRequest $request): string
    {
        throw new RuntimeException('Signature électronique désactivée (SIGNATURE_DRIVER=none).');
    }

    public function annuler(SignatureRequest $request): void {}
}
