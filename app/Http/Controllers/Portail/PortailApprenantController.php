<?php

namespace App\Http\Controllers\Portail;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organisation;
use App\Models\Presence;
use App\Portail\PortailApprenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Espace personnel de l'apprenant (portail sans mot de passe). L'accès repose sur
 * un jeton personnel porté par l'URL (App\Portail\PortailApprenantService), dans
 * la lignée des liens tokenisés existants (inscription, émargement). Aucune
 * session ERP : chaque page revalide le jeton et ne montre QUE les données de cet
 * apprenant.
 */
class PortailApprenantController extends Controller
{
    /**
     * Types de documents que l'apprenant peut consulter dans son espace. On
     * exclut volontairement les pièces internes (CV du maître d'apprentissage,
     * fiche besoin, documents qualité…) : l'apprenant ne voit que ce qui le
     * concerne directement.
     */
    private const DOCUMENTS_VISIBLES = [
        DocumentType::Contrat,
        DocumentType::Cerfa,
        DocumentType::Convention,
        DocumentType::Calendrier,
        DocumentType::Bulletin,
        DocumentType::DocumentPedagogique,
    ];

    public function __construct(private readonly PortailApprenantService $service) {}

    /** Accueil : identité, contrat, classe, assiduité et prochaine séance. */
    public function accueil(string $token): Response
    {
        $candidate = $this->resoudre($token);
        $contrat = $candidate->contracts()->with(['company', 'tuteur', 'formation', 'promotion'])->latest('id')->first();

        $prochaine = $candidate->presences()
            ->whereHas('seance', fn ($s) => $s->whereDate('date', '>=', now()->toDateString()))
            ->with(['seance.promotion.formation', 'seance.formateur'])
            ->get()
            ->sortBy(fn (Presence $p) => $p->seance?->date)
            ->first();

        return response()->view('portail.apprenant.accueil', $this->contexte($candidate, 'accueil', [
            'contrat' => $contrat,
            'assiduite' => $candidate->assiduite(),
            'classes' => $candidate->promotions()->with('formation')->get(),
            'prochaine' => $prochaine,
            'nbDocuments' => $this->documentsVisibles($candidate)->count(),
        ]));
    }

    /** Planning : calendrier mensuel des séances de l'apprenant (avec sa présence). */
    public function planning(string $token): Response
    {
        $candidate = $this->resoudre($token);

        $presences = $candidate->presences()
            ->with(['seance.promotion.formation', 'seance.formateur'])
            ->get()
            ->filter(fn (Presence $p) => $p->seance !== null);

        $mois = $this->moisAffiche($presences);

        $duMois = $presences
            ->filter(fn (Presence $p) => $p->seance->date->isSameMonth($mois))
            ->sortBy(fn (Presence $p) => $p->seance->date->format('Y-m-d').$p->seance->heure_debut);

        return response()->view('portail.apprenant.planning', $this->contexte($candidate, 'planning', [
            'mois' => $mois,
            'semaines' => $this->grilleMois($mois),
            'parJour' => $duMois->groupBy(fn (Presence $p) => $p->seance->date->toDateString()),
            'duMois' => $duMois->values(),
            'moisPrecedent' => $mois->copy()->subMonthNoOverflow()->format('Y-m'),
            'moisSuivant' => $mois->copy()->addMonthNoOverflow()->format('Y-m'),
            'moisCourant' => now()->format('Y-m'),
        ]));
    }

    /**
     * Mois à afficher : ?mois=AAAA-MM si valide, sinon le mois de la prochaine
     * séance (ou, à défaut, de la plus récente), sinon le mois courant.
     *
     * @param  Collection<int, Presence>  $presences
     */
    private function moisAffiche(Collection $presences): Carbon
    {
        $param = request('mois');

        if (is_string($param) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $param)) {
            return Carbon::createFromFormat('Y-m-d', $param.'-01')->startOfMonth();
        }

        $prochaine = $presences
            ->filter(fn (Presence $p) => $p->seance->date->isToday() || $p->seance->date->isFuture())
            ->sortBy(fn (Presence $p) => $p->seance->date->timestamp)
            ->first();

        $reference = $prochaine
            ?? $presences->sortByDesc(fn (Presence $p) => $p->seance->date->timestamp)->first();

        return ($reference?->seance->date ?? now())->copy()->startOfMonth();
    }

    /**
     * Grille du mois : semaines (lundi→dimanche), chacune une liste de 7 jours
     * (Carbon), débordant sur les mois voisins pour compléter la première et la
     * dernière semaine.
     *
     * @return array<int, array<int, Carbon>>
     */
    private function grilleMois(Carbon $mois): array
    {
        $jour = $mois->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $fin = $mois->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $semaines = [];
        while ($jour->lte($fin)) {
            $semaine = [];
            for ($i = 0; $i < 7; $i++) {
                $semaine[] = $jour->copy();
                $jour->addDay();
            }
            $semaines[] = $semaine;
        }

        return $semaines;
    }

    /** Documents : convention, CERFA, bulletins, calendrier… téléchargeables. */
    public function documents(string $token): Response
    {
        $candidate = $this->resoudre($token);

        return response()->view('portail.apprenant.documents', $this->contexte($candidate, 'documents', [
            'documents' => $this->documentsVisibles($candidate),
        ]));
    }

    /**
     * Télécharge une pièce de l'apprenant. Double garde : le document doit être
     * dans la liste des types visibles ET rattaché à cet apprenant (lui-même ou
     * l'un de ses contrats) — un jeton ne donne jamais accès aux pièces d'autrui.
     */
    public function document(string $token, Document $document): StreamedResponse|RedirectResponse
    {
        $candidate = $this->resoudre($token);

        abort_unless($this->documentAppartient($document, $candidate), 404);

        $media = $document->getFirstMedia('fichier');

        if ($media === null || ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
            return redirect()
                ->route('portail.apprenant.documents', ['token' => $token])
                ->with('erreur', "Ce document n'est pas disponible au téléchargement.");
        }

        return response()->streamDownload(
            fn () => print (file_get_contents($media->getPath())),
            $media->file_name,
            ['Content-Type' => $media->mime_type],
        );
    }

    /** Résout l'apprenant depuis son jeton (404 si inconnu). */
    private function resoudre(string $token): Candidate
    {
        $candidate = $this->service->parToken($token);

        abort_if($candidate === null, 404, 'Ce lien ne correspond à aucun espace apprenant.');

        return $candidate;
    }

    /**
     * Contexte commun à toutes les pages du portail : apprenant, CFA (branding
     * white-label), jeton, onglet actif — fusionné aux données propres à la page.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function contexte(Candidate $candidate, string $actif, array $donnees = []): array
    {
        $cfa = $candidate->organisation ?? Organisation::defaut();

        return array_merge([
            'candidate' => $candidate,
            'cfa' => $cfa,
            'token' => $candidate->portail_token,
            'actif' => $actif,
        ], $donnees);
    }

    /**
     * Documents visibles par l'apprenant (types autorisés, avec un fichier),
     * qu'ils soient rattachés à lui-même ou à l'un de ses contrats.
     *
     * @return Collection<int, Document>
     */
    private function documentsVisibles(Candidate $candidate): Collection
    {
        $types = array_map(fn (DocumentType $t) => $t->value, self::DOCUMENTS_VISIBLES);
        $contratIds = $candidate->contracts()->pluck('id')->all();

        return Document::query()
            ->tousLesCfa()
            ->whereIn('type', $types)
            ->whereHas('media')
            ->where(function ($q) use ($candidate, $contratIds): void {
                $q->where(fn ($sub) => $sub->where('documentable_type', $candidate->getMorphClass())
                    ->where('documentable_id', $candidate->getKey()));

                if ($contratIds !== []) {
                    $q->orWhere(fn ($sub) => $sub->where('documentable_type', (new Contract)->getMorphClass())
                        ->whereIn('documentable_id', $contratIds));
                }
            })
            ->latest('id')
            ->get();
    }

    /** Le document appartient-il bien à cet apprenant (lui ou un de ses contrats) ? */
    private function documentAppartient(Document $document, Candidate $candidate): bool
    {
        if (! in_array($document->type, self::DOCUMENTS_VISIBLES, true)) {
            return false;
        }

        if ($document->documentable_type === $candidate->getMorphClass()
            && (int) $document->documentable_id === (int) $candidate->getKey()) {
            return true;
        }

        return $document->documentable_type === (new Contract)->getMorphClass()
            && $candidate->contracts()->whereKey($document->documentable_id)->exists();
    }
}
