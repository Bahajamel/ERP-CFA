<?php

namespace App\Filament\Actions;

use App\Mail\AccesEspaceApprenant;
use App\Models\Candidate;
use App\Models\Organisation;
use App\Portail\PortailApprenantService;
use App\Support\QrCode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

/**
 * « Espace apprenant » : ouvre (ou renvoie) le lien personnel de l'apprenant vers
 * son portail sans mot de passe — QR + lien copiable + envoi par e-mail. Le jeton
 * est émis à la demande et reste stable ; « Régénérer » révoque l'ancien lien.
 */
class EspaceApprenantAction
{
    public static function make(): Action
    {
        return Action::make('espaceApprenant')
            ->label('Espace apprenant')
            ->icon('heroicon-o-identification')
            ->color('info')
            ->modalHeading(fn (Candidate $record): string => "Espace apprenant — {$record->nom_complet}")
            ->modalDescription('Lien personnel vers l\'espace de l\'apprenant (planning, présences, documents), sans mot de passe. Partagez-le par e-mail, QR ou copier-coller.')
            ->modalIcon('heroicon-o-identification')
            ->modalContent(function (Candidate $record) {
                $lien = app(PortailApprenantService::class)->lienPour($record);

                return view('filament.espace-apprenant', [
                    'lien' => $lien,
                    'qr' => QrCode::dataUri($lien, 200),
                    'email' => $record->email,
                ]);
            })
            ->modalSubmitActionLabel('Envoyer le lien par e-mail')
            // Pas d'e-mail au dossier → on masque l'envoi (le lien reste copiable/QR).
            ->modalSubmitAction(fn (Candidate $record) => filled($record->email) ? null : false)
            ->extraModalFooterActions([
                Action::make('regenererLienApprenant')
                    ->label('Régénérer le lien')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Régénérer le lien ?')
                    ->modalDescription('L\'ancien lien cessera immédiatement de fonctionner. Rouvrez « Espace apprenant » pour récupérer le nouveau.')
                    ->action(function (Candidate $record): void {
                        app(PortailApprenantService::class)->regenerer($record);

                        Notification::make()->success()
                            ->title('Nouveau lien généré')
                            ->body('L\'ancien lien est désormais invalide.')
                            ->send();
                    }),
            ])
            ->action(function (Candidate $record): void {
                if (blank($record->email)) {
                    return;
                }

                $service = app(PortailApprenantService::class);
                $cfa = $record->organisation ?? Organisation::defaut();

                Mail::to($record->email)->send(new AccesEspaceApprenant(
                    candidate: $record,
                    lien: $service->lienPour($record),
                    nomCfa: $cfa?->designation() ?? 'CFA',
                ));

                Notification::make()->success()
                    ->title('Lien envoyé')
                    ->body("L'apprenant a reçu son lien d'accès à {$record->email}.")
                    ->send();
            });
    }
}
