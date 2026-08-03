<?php

namespace App\Emargement;

use App\Enums\PresenceStatut;
use App\Enums\SeanceStatut;
use App\Models\Presence;
use Illuminate\Support\Str;

/**
 * Émargement dématérialisé (V2) : chaque apprenant signe sa présence à une séance
 * depuis son propre appareil, via un lien personnel tokenisé (même principe que
 * l'invitation d'inscription). La signature est horodatée → preuve réelle de
 * présence, jamais réutilisable ailleurs.
 *
 * S'appuie sur l'existant (Presence = apprenti × séance), sans nouvelle table.
 */
class SignatureEmargementService
{
    /**
     * Jeton de signature de la présence : réutilise celui déjà émis, sinon en
     * génère un (idempotent — rouvrir la signature ne change pas le lien).
     */
    public function jetonPour(Presence $presence): string
    {
        if (blank($presence->signature_token)) {
            $presence->forceFill(['signature_token' => $this->jetonUnique()])->save();
        }

        return $presence->signature_token;
    }

    /** Retrouve la présence par son jeton de signature, ou null. */
    public function parToken(string $token): ?Presence
    {
        return Presence::query()->where('signature_token', $token)->first();
    }

    /**
     * La présence peut-elle encore être signée ? (jeton présent, pas déjà signée,
     * séance non annulée).
     */
    public function signable(Presence $presence): bool
    {
        return filled($presence->signature_token)
            && ! $presence->aSigne()
            && $presence->seance?->statut !== SeanceStatut::Annulee;
    }

    /**
     * Enregistre la signature de l'apprenant : décode l'image (data-URI PNG),
     * l'archive sur disque privé, horodate, mémorise l'IP et bascule le statut sur
     * « Présent ». Idempotent : ne re-signe pas une présence déjà signée.
     */
    public function enregistrer(Presence $presence, string $dataUriPng, ?string $ip): void
    {
        if ($presence->aSigne()) {
            return;
        }

        $binaire = $this->decoderPng($dataUriPng);

        $presence->addMediaFromString($binaire)
            ->usingFileName('signature-'.$presence->getKey().'.png')
            ->toMediaCollection('signature');

        $presence->forceFill([
            'signed_at' => now(),
            'signed_ip' => $ip,
            'statut' => PresenceStatut::Present,
        ])->save();
    }

    /**
     * Décode une image « data:image/png;base64,…. » en binaire.
     *
     * @throws \InvalidArgumentException si le format n'est pas une image PNG base64.
     */
    private function decoderPng(string $dataUri): string
    {
        if (! preg_match('#^data:image/png;base64,#', $dataUri)) {
            throw new \InvalidArgumentException('Signature invalide (PNG attendu).');
        }

        $base64 = substr($dataUri, strpos($dataUri, ',') + 1);
        $binaire = base64_decode($base64, true);

        if ($binaire === false || $binaire === '') {
            throw new \InvalidArgumentException('Signature illisible.');
        }

        return $binaire;
    }

    private function jetonUnique(): string
    {
        do {
            $token = Str::random(48);
        } while (Presence::query()->where('signature_token', $token)->exists());

        return $token;
    }
}
