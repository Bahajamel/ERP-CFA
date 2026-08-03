<?php

namespace App\Http\Controllers;

use App\Emargement\SignatureEmargementService;
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
