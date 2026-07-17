<?php

namespace App\Signature\Providers;

use App\Cerfa\CerfaApprentissage;
use App\Models\SignatureRequest;
use App\Signature\Contracts\SignatureProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Prestataire de signature électronique Yousign (API v3).
 *
 * Parcours d'envoi, tel que documenté par Yousign :
 *   1. POST /signature_requests                  → crée l'enveloppe (brouillon)
 *   2. POST /signature_requests/{id}/documents   → dépose le CERFA à signer
 *   3. POST /signature_requests/{id}/signers     → une requête par partie
 *   4. POST /signature_requests/{id}/activate    → déclenche l'envoi des mails
 *
 * Les retours de signature arrivent par webhook sur /webhooks/signature/yousign
 * (cf. SignatureWebhookController), authentifiés en HMAC-SHA256.
 *
 * Tant qu'aucune clé d'API n'est configurée, {@see estActif()} renvoie false :
 * l'ERP se comporte comme si la signature électronique était désactivée, plutôt
 * que d'échouer au moment de l'envoi.
 *
 * @see https://developers.yousign.com/ — documentation officielle
 */
class YousignSignatureProvider implements SignatureProvider
{
    private const BASE_PRODUCTION = 'https://api.yousign.app/v3';

    private const BASE_SANDBOX = 'https://api-sandbox.yousign.app/v3';

    public function __construct(private readonly CerfaApprentissage $cerfa) {}

    public function nom(): string
    {
        return 'yousign';
    }

    /**
     * Sans clé d'API, le driver se déclare inactif : le bouton « Envoyer en
     * signature » disparaît au lieu de partir en erreur chez l'utilisateur.
     */
    public function estActif(): bool
    {
        return filled($this->cle());
    }

    /**
     * Dépose le contrat chez Yousign et déclenche l'envoi aux signataires.
     *
     * @return string identifiant de l'enveloppe Yousign (stocké en external_id)
     */
    public function envoyer(SignatureRequest $request): string
    {
        $contract = $request->contract;

        if ($contract === null) {
            throw new RuntimeException('Envoi Yousign impossible : la demande de signature n\'est rattachée à aucun contrat.');
        }

        $enveloppeId = $this->creerEnveloppe($contract->id);
        $documentId = $this->deposerDocument($enveloppeId, $this->cerfa->pour($contract), $contract->id);

        foreach ($request->signataires ?? [] as $signataire) {
            $this->ajouterSignataire($enveloppeId, $documentId, $signataire);
        }

        $this->activer($enveloppeId);

        return $enveloppeId;
    }

    /**
     * Annule l'enveloppe chez Yousign. Best-effort : l'annulation côté ERP ne
     * doit pas échouer parce que le prestataire est injoignable — le contrat
     * reste maître, on trace et on continue.
     */
    public function annuler(SignatureRequest $request): void
    {
        if (blank($request->external_id) || ! $this->estActif()) {
            return;
        }

        try {
            $this->client()
                ->post("/signature_requests/{$request->external_id}/cancel", [
                    'reason' => 'contractualization_aborted',
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Yousign : annulation de l\'enveloppe impossible.', [
                'external_id' => $request->external_id,
                'erreur' => $e->getMessage(),
            ]);
        }
    }

    /** Étape 1 — enveloppe en brouillon. */
    private function creerEnveloppe(int $contractId): string
    {
        $reponse = $this->client()->post('/signature_requests', [
            'name' => "Contrat d'apprentissage n° {$contractId}",
            // « email » : Yousign notifie lui-même chaque partie, dans l'ordre.
            'delivery_mode' => 'email',
            'timezone' => config('app.timezone', 'Europe/Paris'),
            'expiration_date' => now()->addDays((int) config('signature.expiration_jours', 14))->toDateString(),
        ])->throw()->json();

        return $this->idObligatoire($reponse, 'création de l\'enveloppe');
    }

    /** Étape 2 — dépôt du CERFA (multipart). */
    private function deposerDocument(string $enveloppeId, string $pdf, int $contractId): string
    {
        $reponse = $this->client()
            ->attach('file', $pdf, "contrat-apprentissage-{$contractId}.pdf")
            ->post("/signature_requests/{$enveloppeId}/documents", [
                'nature' => 'signable_document',
            ])->throw()->json();

        return $this->idObligatoire($reponse, 'dépôt du document');
    }

    /**
     * Étape 3 — une partie au contrat. L'ordre de signature est celui calculé
     * par le SignatureService (apprenti, représentant légal, employeur, CFA).
     *
     * @param  array<string, mixed>  $signataire
     */
    private function ajouterSignataire(string $enveloppeId, string $documentId, array $signataire): void
    {
        [$prenom, $nom] = $this->scinderNom((string) ($signataire['nom'] ?? ''));

        $this->client()->post("/signature_requests/{$enveloppeId}/signers", [
            'info' => array_filter([
                'first_name' => $prenom,
                'last_name' => $nom,
                'email' => $signataire['email'] ?? null,
                'locale' => 'fr',
            ]),
            'signature_level' => config('signature.yousign.niveau'),
            'signature_authentication_mode' => config('signature.yousign.authentification'),
            'fields' => [[
                'type' => 'signature',
                'document_id' => $documentId,
                // Emplacement du bloc de signature. Le CERFA 10103*14 réserve les
                // cadres de signature en bas de la page 1 : à ajuster finement à
                // la première enveloppe réelle (impossible à caler à l'aveugle).
                'page' => 1,
                'x' => 80,
                'y' => 700,
            ]],
        ])->throw();
    }

    /** Étape 4 — activation : c'est elle qui déclenche les mails aux signataires. */
    private function activer(string $enveloppeId): void
    {
        $this->client()->post("/signature_requests/{$enveloppeId}/activate")->throw();
    }

    /**
     * Yousign attend un prénom ET un nom. Nos signataires sont stockés en une
     * seule chaîne (« Marie Dupont », ou « CFA V2S » pour une personne morale) :
     * on scinde au premier espace, et on retombe sur un placeholder plutôt que
     * d'envoyer un champ vide, que l'API refuserait.
     *
     * @return array{0: string, 1: string}
     */
    private function scinderNom(string $nomComplet): array
    {
        $morceaux = preg_split('/\s+/', trim($nomComplet), 2) ?: [];

        return [
            $morceaux[0] ?? '—',
            $morceaux[1] ?? ($morceaux[0] ?? '—'),
        ];
    }

    /** @param array<string, mixed>|null $reponse */
    private function idObligatoire(?array $reponse, string $etape): string
    {
        $id = $reponse['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new RuntimeException("Yousign : réponse inattendue à l'étape « {$etape} » (identifiant absent).");
        }

        return $id;
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->cle())
            ->acceptJson()
            ->baseUrl($this->baseUrl())
            ->timeout(30);
    }

    private function cle(): ?string
    {
        return config('signature.yousign.api_key');
    }

    private function baseUrl(): string
    {
        return config('signature.yousign.base_url')
            ?: (config('signature.yousign.sandbox') ? self::BASE_SANDBOX : self::BASE_PRODUCTION);
    }
}
