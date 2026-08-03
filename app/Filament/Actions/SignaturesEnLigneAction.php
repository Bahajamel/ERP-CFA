<?php

namespace App\Filament\Actions;

use App\Emargement\SignatureEmargementService;
use App\Models\Seance;
use App\Support\QrCode;
use Filament\Actions\Action;

/**
 * « Signatures en ligne » d'une séance : génère (idempotent) le lien personnel de
 * signature de chaque apprenant et affiche l'état (signé / en attente). Le
 * formateur diffuse ces liens (mail, QR, affichage) ; chacun signe sur son appareil.
 */
class SignaturesEnLigneAction
{
    public static function make(): Action
    {
        return Action::make('signaturesEnLigne')
            ->label('Signatures en ligne')
            ->icon('heroicon-o-link')
            ->color('gray')
            ->modalHeading(fn (Seance $record): string => 'Signatures en ligne — '
                .($record->libelle ?? 'Séance')
                .' du '.$record->date->format('d/m/Y'))
            ->modalDescription('Projetez ou partagez CE QR unique : chaque apprenant le scanne, choisit son nom et signe depuis son appareil. L\'état se met à jour en direct.')
            ->modalContent(function (Seance $record) {
                $service = app(SignatureEmargementService::class);
                $lienSeance = route('emargement.seance', $service->jetonSeance($record));

                $lignes = $record->presences()
                    ->with('candidate')
                    ->get()
                    ->map(fn ($presence): array => [
                        'nom' => $presence->candidate?->nom_complet ?? '—',
                        'signe' => $presence->aSigne(),
                        'signed_at' => $presence->signed_at,
                    ])
                    ->sortBy('nom')
                    ->values();

                return view('filament.signatures-en-ligne', [
                    'lienSeance' => $lienSeance,
                    'qrSeance' => QrCode::dataUri($lienSeance, 240),
                    'lignes' => $lignes,
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->modalWidth('2xl');
    }
}
