<?php

namespace App\Http\Controllers;

use App\Emargement\SignatureEmargementService;
use App\Enums\SeanceStatut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Signature publique de l'émargement (sans accès ERP) : l'apprenant ouvre son
 * lien personnel tokenisé et signe sa présence à une séance depuis son appareil.
 * L'accès est gardé par le jeton (non devinable) ; la signature est horodatée.
 */
class EmargementSignatureController extends Controller
{
    public function __construct(private readonly SignatureEmargementService $service) {}

    /**
     * QR unique de séance : liste les apprenants qui n'ont pas encore signé.
     * Chacun choisit son nom puis signe via son propre pavé (lien tokenisé).
     */
    public function seance(string $token): View
    {
        $seance = $this->service->seanceParToken($token);

        if ($seance === null || $seance->statut === SeanceStatut::Annulee) {
            return view('emargement.invalide');
        }

        $seance->load('promotion.formation', 'formateur');

        $nonSignes = $seance->presences()
            ->with('candidate')
            ->whereNull('signed_at')
            ->get()
            ->sortBy(fn ($p) => $p->candidate?->nom.' '.$p->candidate?->prenom)
            ->map(fn ($p): array => [
                'nom' => $p->candidate?->nom_complet ?? '—',
                'lien' => route('emargement.signer', $this->service->jetonPour($p)),
            ])
            ->values();

        $total = $seance->presences()->count();

        return view('emargement.seance', [
            'seance' => $seance,
            'nonSignes' => $nonSignes,
            'total' => $total,
            'signes' => $total - $nonSignes->count(),
        ]);
    }

    /** Affiche le pavé de signature (ou un état déjà signé / lien invalide). */
    public function show(string $token): View
    {
        $presence = $this->service->parToken($token);

        if ($presence === null) {
            return view('emargement.invalide');
        }

        $presence->load('candidate', 'seance.promotion.formation', 'seance.formateur');

        if ($presence->aSigne()) {
            return view('emargement.deja', ['presence' => $presence]);
        }

        if (! $this->service->signable($presence)) {
            return view('emargement.invalide');
        }

        return view('emargement.signer', [
            'token' => $token,
            'presence' => $presence,
        ]);
    }

    /** Enregistre la signature de l'apprenant. */
    public function store(Request $request, string $token): RedirectResponse
    {
        $presence = $this->service->parToken($token);

        if ($presence === null || ! $this->service->signable($presence)) {
            return redirect()->route('emargement.signer', $token);
        }

        $data = $request->validate(
            ['signature' => ['required', 'string', 'starts_with:data:image/png;base64,']],
            ['signature.required' => 'Merci de signer dans le cadre avant de valider.'],
        );

        try {
            $this->service->enregistrer($presence, $data['signature'], $request->ip());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['signature' => $e->getMessage()]);
        }

        return redirect()->route('emargement.merci');
    }
}
