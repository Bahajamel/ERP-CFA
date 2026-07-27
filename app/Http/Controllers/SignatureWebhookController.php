<?php

namespace App\Http\Controllers;

use App\Models\SignatureRequest;
use App\Services\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Réception des notifications de signature d'un prestataire (EPIC-08).
 *
 * Un prestataire réel appelle cette URL à chaque signature de partie. On
 * identifie l'enveloppe (external_id), on enregistre la signature du signataire ;
 * quand toutes les parties ont signé, le SignatureService passe le contrat à
 * « Signé » et archive la preuve (P1-08-7).
 *
 * Deux formats sont acceptés :
 *  - Yousign : payload `event_name` + `data.…`, authentifié en HMAC-SHA256 sur
 *    le corps brut (en-tête « x-yousign-signature-256 ») ;
 *  - générique : `external_id` + `signataire`, avec un secret partagé en clair
 *    (en-tête « X-Signature-Secret ») — conservé pour les autres prestataires.
 */
class SignatureWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, SignatureService $service): JsonResponse
    {
        return $provider === 'yousign'
            ? $this->yousign($request, $service)
            : $this->generique($request, $provider, $service);
    }

    /**
     * Webhook Yousign (API v3).
     *
     * @see https://developers.yousign.com/docs/use-webhooks-in-your-app
     */
    private function yousign(Request $request, SignatureService $service): JsonResponse
    {
        if (! $this->hmacYousignValide($request)) {
            return response()->json(['message' => 'Signature du webhook invalide.'], 401);
        }

        $evenement = (string) $request->input('event_name');
        $externalId = $request->input('data.signature_request.id');

        if (! is_string($externalId) || $externalId === '') {
            return response()->json(['message' => 'Enveloppe absente du payload.'], 422);
        }

        $signatureRequest = $this->enveloppe('yousign', $externalId);

        if ($signatureRequest === null) {
            return response()->json(['message' => 'Enveloppe inconnue.'], 404);
        }

        // Seule la signature d'une partie nous intéresse : le service recompose
        // l'état global de l'enveloppe et clôt le contrat quand tout est signé.
        // Les autres événements (activation, rappels…) sont acquittés sans effet.
        if ($evenement === 'signer.done') {
            $email = $request->input('data.signer.info.email');

            if (! is_string($email) || $email === '') {
                return response()->json(['message' => 'Signataire absent du payload.'], 422);
            }

            $service->enregistrerSignature($signatureRequest, $email);
        }

        return response()->json([
            'evenement' => $evenement,
            'statut' => $signatureRequest->fresh()->statut->value,
        ]);
    }

    /** Format maison : secret partagé en clair + identification directe. */
    private function generique(Request $request, string $provider, SignatureService $service): JsonResponse
    {
        $secret = config('signature.webhook_secret');

        if (! empty($secret) && ! hash_equals($secret, (string) $request->header('X-Signature-Secret'))) {
            return response()->json(['message' => 'Signature du webhook invalide.'], 401);
        }

        $data = $request->validate([
            'external_id' => ['required', 'string'],
            'signataire' => ['required', 'string'], // email ou rôle du signataire
        ]);

        $signatureRequest = $this->enveloppe($provider, $data['external_id']);

        if ($signatureRequest === null) {
            return response()->json(['message' => 'Enveloppe inconnue.'], 404);
        }

        $service->enregistrerSignature($signatureRequest, $data['signataire']);

        return response()->json(['statut' => $signatureRequest->fresh()->statut->value]);
    }

    private function enveloppe(string $provider, string $externalId): ?SignatureRequest
    {
        // Requête hors contexte CFA (appel serveur à serveur) : le cloisonnement
        // par organisation ne s'applique pas, l'enveloppe est identifiée par un
        // couple prestataire + identifiant externe, non devinable.
        return SignatureRequest::query()
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();
    }

    /**
     * Vérifie l'en-tête « x-yousign-signature-256 » : HMAC-SHA256 du corps BRUT
     * (pas du JSON re-sérialisé), préfixé de « sha256= ».
     */
    private function hmacYousignValide(Request $request): bool
    {
        $secret = config('signature.yousign.webhook_secret');

        // Pas de secret configuré : on n'a aucun moyen de vérifier l'appelant.
        // On refuse plutôt que d'accepter n'importe qui à faire signer un contrat.
        if (blank($secret)) {
            return false;
        }

        $entete = (string) $request->header('x-yousign-signature-256');
        $attendu = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($attendu, $entete);
    }
}
