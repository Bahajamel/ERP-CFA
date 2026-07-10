<?php

namespace App\Http\Controllers;

use App\Models\SignatureRequest;
use App\Services\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Réception des notifications de signature d'un prestataire eIDAS (EPIC-08).
 *
 * Un prestataire réel (Yousign, Docaposte…) appelle cette URL à chaque signature
 * de partie. On identifie l'enveloppe (external_id), on enregistre la signature
 * du signataire ; quand toutes les parties ont signé, le SignatureService passe
 * le contrat à « Signé » et archive la preuve (P1-08-7).
 */
class SignatureWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, SignatureService $service): JsonResponse
    {
        $secret = config('signature.webhook_secret');
        if (! empty($secret) && ! hash_equals($secret, (string) $request->header('X-Signature-Secret'))) {
            return response()->json(['message' => 'Signature du webhook invalide.'], 401);
        }

        $data = $request->validate([
            'external_id' => ['required', 'string'],
            'signataire' => ['required', 'string'], // email ou rôle du signataire
        ]);

        $signatureRequest = SignatureRequest::query()
            ->where('provider', $provider)
            ->where('external_id', $data['external_id'])
            ->first();

        if ($signatureRequest === null) {
            return response()->json(['message' => 'Enveloppe inconnue.'], 404);
        }

        $service->enregistrerSignature($signatureRequest, $data['signataire']);

        return response()->json(['statut' => $signatureRequest->fresh()->statut->value]);
    }
}
