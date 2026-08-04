<?php

namespace App\Http\Controllers\Portail;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Portail\PortailEntrepriseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Espace personnel de l'entreprise (portail sans mot de passe), pendant employeur
 * du portail apprenant. Accès par un jeton personnel porté par l'URL
 * (App\Portail\PortailEntrepriseService). Aucune session ERP : chaque page
 * revalide le jeton et ne montre QUE les données de cette entreprise.
 */
class PortailEntrepriseController extends Controller
{
    /**
     * Types de documents que l'entreprise peut consulter : les pièces
     * contractuelles et de facturation qui la concernent. Les pièces internes au
     * CFA (fiche besoin, qualité, pédagogie…) sont volontairement exclues.
     */
    private const DOCUMENTS_VISIBLES = [
        DocumentType::Convention,
        DocumentType::Cerfa,
        DocumentType::Contrat,
        DocumentType::Facture,
        DocumentType::Calendrier,
    ];

    public function __construct(private readonly PortailEntrepriseService $service) {}

    /** Accueil : synthèse alternants, assiduité globale, alertes, volumétrie. */
    public function accueil(string $token): Response
    {
        $company = $this->resoudre($token);
        $alternants = $this->alternants($company);
        $global = $this->assiduiteGlobale($alternants);

        return response()->view('portail.entreprise.accueil', $this->contexte($company, 'accueil', [
            'alternants' => $alternants,
            'assiduite' => $global,
            'nbDocuments' => $this->documentsVisibles($company)->count(),
            'nbFactures' => $this->facturesQuery($company)->count(),
        ]));
    }

    /** Mes alternants : liste détaillée avec assiduité et tuteur. */
    public function alternantsPage(string $token): Response
    {
        $company = $this->resoudre($token);

        return response()->view('portail.entreprise.alternants', $this->contexte($company, 'alternants', [
            'alternants' => $this->alternants($company),
        ]));
    }

    /** Documents : conventions, CERFA, contrats, calendrier — téléchargeables. */
    public function documents(string $token): Response
    {
        $company = $this->resoudre($token);

        return response()->view('portail.entreprise.documents', $this->contexte($company, 'documents', [
            'documents' => $this->documentsVisibles($company),
        ]));
    }

    /** Factures de l'entreprise (statut, montant, échéance, reste à payer). */
    public function factures(string $token): Response
    {
        $company = $this->resoudre($token);

        $factures = $this->facturesQuery($company)
            ->with(['financeLine.contract.candidate', 'documents.media'])
            ->latest('date_emission')
            ->latest('id')
            ->get();

        return response()->view('portail.entreprise.factures', $this->contexte($company, 'factures', [
            'factures' => $factures,
        ]));
    }

    /**
     * Télécharge une pièce de l'entreprise. Double garde : type autorisé ET
     * rattaché à l'entreprise (elle-même, l'un de ses contrats ou l'une de ses
     * factures) — un jeton ne donne jamais accès aux pièces d'autrui.
     */
    public function document(string $token, Document $document): StreamedResponse|RedirectResponse
    {
        $company = $this->resoudre($token);

        abort_unless($this->documentAppartient($document, $company), 404);

        $media = $document->getFirstMedia('fichier');

        if ($media === null || ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
            return redirect()
                ->route('portail.entreprise.documents', ['token' => $token])
                ->with('erreur', "Ce document n'est pas disponible au téléchargement.");
        }

        return response()->streamDownload(
            fn () => print (file_get_contents($media->getPath())),
            $media->file_name,
            ['Content-Type' => $media->mime_type],
        );
    }

    /** Résout l'entreprise depuis son jeton (404 si inconnu). */
    private function resoudre(string $token): Company
    {
        $company = $this->service->parToken($token);

        abort_if($company === null, 404, 'Ce lien ne correspond à aucun espace entreprise.');

        return $company;
    }

    /**
     * Contexte commun à toutes les pages : entreprise, CFA (branding white-label),
     * jeton, onglet actif — fusionné aux données propres à la page.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function contexte(Company $company, string $actif, array $donnees = []): array
    {
        $cfa = $company->organisation ?? Organisation::defaut();

        return array_merge([
            'company' => $company,
            'cfa' => $cfa,
            'token' => $company->portail_token,
            'actif' => $actif,
        ], $donnees);
    }

    /**
     * Alternants de l'entreprise (via ses contrats), chacun enrichi de son
     * assiduité. Structure : contract, candidate, assiduite.
     *
     * @return Collection<int, array{contract: Contract, candidate: Candidate, assiduite: array}>
     */
    private function alternants(Company $company): Collection
    {
        return $company->contracts()
            ->with(['candidate', 'formation', 'tuteur'])
            ->get()
            ->filter(fn (Contract $c) => $c->candidate !== null)
            ->map(fn (Contract $c) => [
                'contract' => $c,
                'candidate' => $c->candidate,
                'assiduite' => $c->candidate->assiduite(),
            ])
            ->sortBy(fn (array $row) => $row['candidate']->nom)
            ->values();
    }

    /**
     * Assiduité agrégée sur tous les alternants (séances renseignées, présents,
     * absences injustifiées, taux global).
     *
     * @param  Collection<int, array>  $alternants
     * @return array{renseignees: int, presents: int, absences_injustifiees: int, taux: int|null}
     */
    private function assiduiteGlobale(Collection $alternants): array
    {
        $renseignees = (int) $alternants->sum(fn (array $r) => $r['assiduite']['renseignees']);
        $presents = (int) $alternants->sum(fn (array $r) => $r['assiduite']['presents']);
        $absences = (int) $alternants->sum(fn (array $r) => $r['assiduite']['absences_injustifiees']);

        return [
            'renseignees' => $renseignees,
            'presents' => $presents,
            'absences_injustifiees' => $absences,
            'taux' => $renseignees > 0 ? (int) round($presents / $renseignees * 100) : null,
        ];
    }

    /**
     * Documents visibles par l'entreprise (types autorisés, avec un fichier),
     * rattachés à elle-même ou à l'un de ses contrats.
     *
     * @return Collection<int, Document>
     */
    private function documentsVisibles(Company $company): Collection
    {
        $types = array_map(fn (DocumentType $t) => $t->value, self::DOCUMENTS_VISIBLES);
        $contratIds = $company->contracts()->pluck('id')->all();

        return Document::query()
            ->tousLesCfa()
            ->whereIn('type', $types)
            ->whereHas('media')
            ->where(function ($q) use ($company, $contratIds): void {
                $q->where(fn ($sub) => $sub->where('documentable_type', $company->getMorphClass())
                    ->where('documentable_id', $company->getKey()));

                if ($contratIds !== []) {
                    $q->orWhere(fn ($sub) => $sub->where('documentable_type', (new Contract)->getMorphClass())
                        ->whereIn('documentable_id', $contratIds));
                }
            })
            ->latest('id')
            ->get();
    }

    /** Requête des factures de l'entreprise (via ligne financière → contrat). */
    private function facturesQuery(Company $company)
    {
        return Invoice::query()
            ->tousLesCfa()
            ->whereHas('financeLine.contract', fn ($q) => $q->where('company_id', $company->getKey()));
    }

    /** La pièce appartient-elle à l'entreprise (elle, un contrat ou une facture) ? */
    private function documentAppartient(Document $document, Company $company): bool
    {
        if (! in_array($document->type, self::DOCUMENTS_VISIBLES, true)) {
            return false;
        }

        if ($document->documentable_type === $company->getMorphClass()
            && (int) $document->documentable_id === (int) $company->getKey()) {
            return true;
        }

        if ($document->documentable_type === (new Contract)->getMorphClass()) {
            return $company->contracts()->whereKey($document->documentable_id)->exists();
        }

        if ($document->documentable_type === (new Invoice)->getMorphClass()) {
            return $this->facturesQuery($company)->whereKey($document->documentable_id)->exists();
        }

        return false;
    }
}
