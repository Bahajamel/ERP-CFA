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
            ->modalDescription('Chaque apprenant signe sa présence depuis son appareil via son lien personnel. Diffusez le lien (e-mail, QR, message) ; l\'état se met à jour dès qu\'il a signé.')
            ->modalContent(function (Seance $record) {
                $service = app(SignatureEmargementService::class);

                $lignes = $record->presences()
                    ->with('candidate')
                    ->get()
                    ->map(function ($presence) use ($service): array {
                        $lien = route('emargement.signer', $service->jetonPour($presence));

                        return [
                            'nom' => $presence->candidate?->nom_complet ?? '—',
                            'lien' => $lien,
                            'qr' => $presence->aSigne() ? null : QrCode::dataUri($lien, 120),
                            'signe' => $presence->aSigne(),
                            'signed_at' => $presence->signed_at,
                        ];
                    })
                    ->sortBy('nom')
                    ->values();

                return view('filament.signatures-en-ligne', ['lignes' => $lignes]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->modalWidth('2xl');
    }
}
