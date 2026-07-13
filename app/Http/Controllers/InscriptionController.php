<?php

namespace App\Http\Controllers;

use App\Scolarite\InscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Formulaire public (sans accès ERP) par lequel un apprenant admis choisit ses
 * matières au sein du programme de sa formation. L'accès est gardé par le jeton
 * d'invitation (lien personnel envoyé par email), non devinable et expirant.
 */
class InscriptionController extends Controller
{
    public function __construct(private readonly InscriptionService $service) {}

    /** Affiche le formulaire de choix des matières (ou un état d'invitation invalide/déjà répondue). */
    public function show(string $token): View
    {
        $inscription = $this->service->parToken($token);

        if ($inscription === null || ! $this->service->invitationValide($inscription)) {
            return view('inscription.invalide');
        }

        $inscription->load('candidate', 'promotion.formation');

        if ($inscription->aRepondu()) {
            return view('inscription.deja', ['inscription' => $inscription]);
        }

        return view('inscription.matieres', [
            'token' => $token,
            'inscription' => $inscription,
            'programme' => $inscription->promotion?->formation?->programme() ?? [],
        ]);
    }

    /** Enregistre les matières choisies et clôt l'invitation. */
    public function store(Request $request, string $token): RedirectResponse
    {
        $inscription = $this->service->parToken($token);

        if ($inscription === null
            || ! $this->service->invitationValide($inscription)
            || $inscription->aRepondu()) {
            return redirect()->route('inscription.matieres', $token);
        }

        $inscription->load('promotion.formation');
        $programme = $inscription->promotion?->formation?->programme() ?? [];

        $data = $request->validate(
            [
                'matieres' => ['required', 'array', 'min:1'],
                'matieres.*' => ['string', Rule::in($programme)],
            ],
            [
                'matieres.required' => 'Choisissez au moins une matière.',
                'matieres.*.in' => 'Matière invalide.',
            ],
        );

        $this->service->enregistrerChoix($inscription, $data['matieres']);

        return redirect()->route('inscription.merci');
    }
}
