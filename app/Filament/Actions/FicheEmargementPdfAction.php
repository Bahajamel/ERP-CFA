<?php

namespace App\Filament\Actions;

use App\Documents\FicheEmargement;
use App\Models\Seance;
use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Génère et télécharge la FICHE D'ÉMARGEMENT (PDF A4) d'une séance, à imprimer et
 * faire signer en cours. Distincte de FeuilleEmargementAction, qui archive le
 * SCAN signé : ici on produit la feuille vierge à partir des données de l'ERP
 * (séance + présences), sans nouvelle table.
 */
class FicheEmargementPdfAction
{
    public static function make(): Action
    {
        return Action::make('ficheEmargementPdf')
            ->label("Fiche d'émargement (PDF)")
            ->icon('heroicon-o-document-arrow-down')
            ->color('primary')
            ->action(function (Seance $record): StreamedResponse {
                $generateur = app(FicheEmargement::class);

                return response()->streamDownload(
                    fn () => print ($generateur->pour($record)),
                    $generateur->nomFichier($record),
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }
}
