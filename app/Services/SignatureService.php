<?php

namespace App\Services;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Enums\SignatureRequestStatut;
use App\Models\CfaProfile;
use App\Models\Contract;
use App\Models\SignatureRequest;
use App\Signature\Contracts\SignatureProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orchestration de la signature électronique multi-parties d'un contrat
 * d'apprentissage (EPIC-08, gap concurrentiel #1).
 *
 * Compose l'enveloppe des signataires (employeur, apprenti, représentant légal
 * si mineur, CFA), la confie au prestataire eIDAS actif, suit les signatures et,
 * une fois toutes les parties signées, passe le contrat à « Signé » et archive
 * la preuve dans la GED (P1-08-7).
 */
class SignatureService
{
    /** Rôles des signataires d'un contrat d'apprentissage. */
    public const ROLE_APPRENTI = 'apprenti';
    public const ROLE_REPRESENTANT = 'representant_legal';
    public const ROLE_EMPLOYEUR = 'employeur';
    public const ROLE_CFA = 'cfa';

    public function __construct(private readonly SignatureProvider $provider)
    {
    }

    public function estActive(): bool
    {
        return $this->provider->estActif();
    }

    public function provider(): SignatureProvider
    {
        return $this->provider;
    }

    /**
     * Compose la liste par défaut des signataires à partir du contrat.
     * Le représentant légal n'est ajouté que si l'apprenti est mineur à la date
     * de début du contrat (art. L6222-1 : contrat signé par le représentant légal).
     *
     * @return list<array{role:string, libelle:string, nom:string, email:?string, ordre:int, signe_at:null}>
     */
    public function signatairesParDefaut(Contract $contract): array
    {
        $contract->loadMissing(['candidate', 'company', 'tuteur']);
        $signataires = [];
        $ordre = 1;

        $apprenti = $contract->candidate;
        $signataires[] = $this->ligne(self::ROLE_APPRENTI, 'Apprenti',
            $apprenti?->nom_complet ?? 'Apprenti', $apprenti?->email, $ordre++);

        if ($this->apprentiEstMineur($contract)) {
            $signataires[] = $this->ligne(self::ROLE_REPRESENTANT, 'Représentant légal',
                '', null, $ordre++);
        }

        $tuteur = $contract->tuteur;
        $signataires[] = $this->ligne(self::ROLE_EMPLOYEUR, 'Employeur',
            $tuteur?->nom_complet ?: ($contract->company?->raison_sociale ?? 'Employeur'),
            $tuteur?->email, $ordre++);

        $cfa = CfaProfile::current();
        $cfaNom = trim(($cfa->representant_prenom ?? '').' '.($cfa->representant_nom ?? '')) ?: $cfa->nom;
        $signataires[] = $this->ligne(self::ROLE_CFA, 'CFA', $cfaNom, $cfa->email, $ordre++);

        return $signataires;
    }

    /**
     * Ouvre une demande de signature et l'envoie au prestataire. Idempotent :
     * une demande en cours pour ce contrat est renvoyée telle quelle.
     *
     * @param  list<array<string,mixed>>|null  $signataires
     */
    public function envoyer(Contract $contract, ?array $signataires = null): SignatureRequest
    {
        if (! $this->estActive()) {
            throw new RuntimeException('Signature électronique désactivée.');
        }

        if ($contract->statut_signature === ContractSignatureStatut::Signe) {
            throw new RuntimeException('Ce contrat est déjà signé.');
        }

        $enCours = $contract->signatureRequests()
            ->whereIn('statut', SignatureRequestStatut::enCours())
            ->latest()
            ->first();

        if ($enCours !== null) {
            return $enCours;
        }

        return DB::transaction(function () use ($contract, $signataires) {
            $request = $contract->signatureRequests()->create([
                'provider' => $this->provider->nom(),
                'statut' => SignatureRequestStatut::Brouillon->value,
                'signataires' => $signataires ?? $this->signatairesParDefaut($contract),
            ]);

            $externalId = $this->provider->envoyer($request);

            $request->forceFill([
                'external_id' => $externalId,
                'statut' => SignatureRequestStatut::Envoyee->value,
                'sent_at' => now(),
            ])->save();

            $contract->forceFill(['statut_signature' => ContractSignatureStatut::Envoye->value])->saveQuietly();

            return $request->refresh();
        });
    }

    /**
     * Enregistre la signature d'une partie (par email ou par rôle). Quand toutes
     * les parties ont signé, finalise la demande.
     */
    public function enregistrerSignature(SignatureRequest $request, string $emailOuRole): void
    {
        $signataires = collect($request->signataires ?? [])
            ->map(function (array $s) use ($emailOuRole) {
                $correspond = ($s['email'] ?? null) === $emailOuRole || ($s['role'] ?? null) === $emailOuRole;

                if ($correspond && empty($s['signe_at'])) {
                    $s['signe_at'] = now()->toIso8601String();
                }

                return $s;
            })
            ->all();

        $request->forceFill(['signataires' => $signataires])->save();
        $request->refresh();

        if ($request->tousSignes()) {
            $this->completer($request);
        } elseif ($request->nombreSignes() > 0) {
            $request->forceFill(['statut' => SignatureRequestStatut::PartiellementSignee->value])->save();
        }
    }

    /**
     * Finalise : marque la demande signée, passe le contrat à « Signé » et
     * archive la preuve de signature dans la GED (P1-08-7).
     */
    public function completer(SignatureRequest $request): void
    {
        $request->forceFill([
            'statut' => SignatureRequestStatut::Signee->value,
            'completed_at' => now(),
        ])->save();

        $contract = $request->contract;
        $contract->forceFill(['statut_signature' => ContractSignatureStatut::Signe->value])->saveQuietly();

        // Toutes les parties ont signé → le contrat est signé. On l'amène à « Signé »
        // par la machine à états quand l'état de départ le permet (effets de bord
        // inclus, dont l'ouverture du dossier OPCO). Sinon (contrat resté en amont,
        // ex. jamais passé par « Envoyé pour signature »), on force la cohérence
        // depuis un état pré-signature et on ouvre le dossier OPCO explicitement,
        // pour que le suivi du financement démarre systématiquement (P0-08-4/09).
        if ($contract->statut_contrat->canTransitionTo(ContractStatut::Signe)) {
            try {
                $contract->transitionTo(ContractStatut::Signe, 'Signature électronique de toutes les parties.');
            } catch (\Throwable) {
                // Cohérence forcée ci-dessous.
            }

            $contract->refresh();
        }

        $preSignature = [
            ContractStatut::Brouillon,
            ContractStatut::InfosManquantes,
            ContractStatut::PretAVerifier,
            ContractStatut::EnvoyeSignature,
        ];

        if (in_array($contract->statut_contrat, $preSignature, true)) {
            $contract->forceFill(['statut_contrat' => ContractStatut::Signe->value])->saveQuietly();
            $contract->ouvrirDossierOpco();
        }

        $this->archiverPreuve($request);
    }

    /**
     * Simulation : signe toutes les parties d'un coup et finalise. Réservé au
     * driver « simulation » (démo / test).
     */
    public function simulerSignatureComplete(SignatureRequest $request): void
    {
        $signataires = collect($request->signataires ?? [])
            ->map(function (array $s) {
                $s['signe_at'] ??= now()->toIso8601String();

                return $s;
            })
            ->all();

        $request->forceFill(['signataires' => $signataires])->save();

        $this->completer($request->refresh());
    }

    private function archiverPreuve(SignatureRequest $request): void
    {
        $contract = $request->contract;

        $contract->documents()->create([
            'type' => DocumentType::Contrat->value,
            'statut' => DocumentStatut::Recu->value,
            'nom_fichier' => 'Contrat signé électroniquement ('.$request->provider.') — '
                .now()->format('d/m/Y'),
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function apprentiEstMineur(Contract $contract): bool
    {
        $naissance = $contract->candidate?->date_naissance;

        if ($naissance === null) {
            return false;
        }

        $reference = $contract->date_debut ?? now();

        return $naissance->diffInYears($reference) < 18;
    }

    /**
     * @return array{role:string, libelle:string, nom:string, email:?string, ordre:int, signe_at:null}
     */
    private function ligne(string $role, string $libelle, string $nom, ?string $email, int $ordre): array
    {
        return [
            'role' => $role,
            'libelle' => $libelle,
            'nom' => $nom,
            'email' => $email,
            'ordre' => $ordre,
            'signe_at' => null,
        ];
    }
}
