<?php

namespace App\Filament\Actions;

use App\Mail\AccesEspaceEntreprise;
use App\Models\Company;
use App\Models\Organisation;
use App\Portail\PortailEntrepriseService;
use App\Support\QrCode;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

/**
 * « Espace entreprise » : ouvre (ou renvoie) le lien personnel de l'entreprise
 * vers son portail sans mot de passe — QR + lien copiable + envoi par e-mail au
 * contact. « Régénérer » révoque l'ancien lien.
 */
class EspaceEntrepriseAction
{
    public static function make(): Action
    {
        return Action::make('espaceEntreprise')
            ->label('Espace entreprise')
            ->icon('heroicon-o-building-office-2')
            ->color('info')
            ->modalHeading(fn (Company $record): string => "Espace entreprise — {$record->raison_sociale}")
            ->modalDescription('Lien personnel vers l\'espace de l\'entreprise (alternants, assiduité, documents, factures), sans mot de passe. Partagez-le par e-mail, QR ou copier-coller.')
            ->modalIcon('heroicon-o-building-office-2')
            ->modalContent(function (Company $record) {
                $service = app(PortailEntrepriseService::class);
                $lien = $service->lienPour($record);

                return view('filament.espace-entreprise', [
                    'lien' => $lien,
                    'qr' => QrCode::dataUri($lien, 200),
                    'email' => $service->emailDestinataire($record),
                ]);
            })
            ->modalSubmitActionLabel('Envoyer le lien par e-mail')
            ->modalSubmitAction(fn (Company $record) => filled(app(PortailEntrepriseService::class)->emailDestinataire($record)) ? null : false)
            ->extraModalFooterActions([
                Action::make('regenererLienEntreprise')
                    ->label('Régénérer le lien')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Régénérer le lien ?')
                    ->modalDescription('L\'ancien lien cessera immédiatement de fonctionner. Rouvrez « Espace entreprise » pour récupérer le nouveau.')
                    ->action(function (Company $record): void {
                        app(PortailEntrepriseService::class)->regenerer($record);

                        Notification::make()->success()
                            ->title('Nouveau lien généré')
                            ->body('L\'ancien lien est désormais invalide.')
                            ->send();
                    }),
            ])
            ->action(function (Company $record): void {
                $service = app(PortailEntrepriseService::class);
                $email = $service->emailDestinataire($record);

                if (blank($email)) {
                    return;
                }

                $cfa = $record->organisation ?? Organisation::defaut();

                Mail::to($email)->send(new AccesEspaceEntreprise(
                    company: $record,
                    lien: $service->lienPour($record),
                    nomCfa: $cfa?->designation() ?? 'CFA',
                ));

                Notification::make()->success()
                    ->title('Lien envoyé')
                    ->body("L'entreprise a reçu son lien d'accès à {$email}.")
                    ->send();
            });
    }
}
