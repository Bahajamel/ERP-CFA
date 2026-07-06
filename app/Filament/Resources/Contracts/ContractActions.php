<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\SignatureRequestStatut;
use App\Jobs\GenererLivrablesJob;
use App\Livret\LivretRsClient;
use App\Models\CfaProfile;
use App\Models\Contract;
use App\Services\SignatureService;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/**
 * Actions de workflow du contrat, pilotées par la machine à états
 * (colonne statut_contrat). Réutilisées table + en-tête d'édition.
 */
class ContractActions
{
    /**
     * Marquer signé. Visible dès que la structure l'autorise ; si la garde
     * métier bloque (pas de preuve de signature), l'utilisateur voit le motif.
     */
    public static function signer(): Action
    {
        return Action::make('signer')
            ->label('Marquer signé')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Confirmer la signature du contrat ? Un document contractuel ou une signature marquée « signée » est requis.')
            ->visible(fn (Contract $record) => $record->statut_contrat->canTransitionTo(ContractStatut::Signe))
            ->action(fn (Contract $record) => self::executer($record, ContractStatut::Signe));
    }

    /**
     * Générer automatiquement les livrables via le service LivretRS, puis les
     * importer dans la GED de l'apprenti. Visible uniquement si le service est
     * configuré (LIVRETRS_URL). Sinon, l'import manuel du ZIP reste disponible.
     */
    public static function genererLivrables(): Action
    {
        return Action::make('genererLivrables')
            ->label('Générer les livrables (auto)')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('primary')
            ->visible(fn (Contract $record) => app(LivretRsClient::class)->estConfigure()
                && $record->candidate !== null
                && (auth()->user()?->can('access_documents') ?? false))
            ->modalHeading('Générer les livrables via LivretRS')
            ->modalDescription('L\'ERP va générer les livrables de cet apprenti puis les importer dans '
                .'la GED avec les missions CFA suggérées. Le service ne conserve aucune donnée.')
            ->modalSubmitActionLabel('Générer')
            ->schema([
                Select::make('theme_code')
                    ->label('Thème graphique')
                    ->options([
                        'institutionnel' => 'Institutionnel',
                        'premium' => 'Premium graphique',
                        'sobre' => 'Sobre',
                    ])
                    ->default(fn () => CfaProfile::current()->theme_defaut ?: 'institutionnel')
                    ->required(),
                Select::make('format')
                    ->label('Format')
                    ->options([
                        'pdf' => 'PDF uniquement',
                        'pdf_docx' => 'PDF + DOCX (éditable)',
                    ])
                    ->default(fn () => CfaProfile::current()->format_defaut ?: 'pdf')
                    ->required(),
                Toggle::make('verifier_rncp')
                    ->label('Vérifier le code RNCP en ligne')
                    ->default(fn () => (bool) CfaProfile::current()->verifier_rncp),
            ])
            ->action(function (Contract $record, array $data) {
                // Génération en tâche de fond (~30 s) : on ne bloque pas la page.
                GenererLivrablesJob::dispatch($record->id, auth()->id(), [
                    'theme_code' => $data['theme_code'] ?? null,
                    'format' => $data['format'] ?? null,
                    'verifier_rncp' => (bool) ($data['verifier_rncp'] ?? false),
                ]);

                Notification::make()
                    ->title('Génération lancée')
                    ->body('Les livrables sont en cours de génération. Vous serez notifié dès qu\'ils sont dans la GED.')
                    ->info()
                    ->send();
            });
    }

    /**
     * Envoyer le contrat en signature électronique multi-parties via le
     * prestataire eIDAS actif (EPIC-08). Visible tant que le contrat n'est pas
     * signé et que la signature électronique est activée.
     */
    public static function envoyerSignature(): Action
    {
        return Action::make('envoyerSignature')
            ->label('Envoyer en signature électronique')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->visible(fn (Contract $record) => app(SignatureService::class)->estActive()
                && $record->statut_signature !== ContractSignatureStatut::Signe
                && (auth()->user()?->can('access_contracts') ?? false))
            ->modalHeading('Signature électronique du contrat')
            ->modalDescription('Chaque partie recevra une demande de signature. À la signature de '
                .'toutes les parties, le contrat passera automatiquement à « Signé ».')
            ->modalSubmitActionLabel('Envoyer aux signataires')
            ->fillForm(fn (Contract $record) => [
                'signataires' => app(SignatureService::class)->signatairesParDefaut($record),
            ])
            ->schema([
                Repeater::make('signataires')
                    ->label('Signataires')
                    ->schema([
                        TextInput::make('libelle')
                            ->label('Rôle')
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('nom')
                            ->label('Nom')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required(),
                    ])
                    ->columns(3)
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
            ])
            ->action(function (Contract $record, array $data) {
                try {
                    $request = app(SignatureService::class)->envoyer($record, $data['signataires']);
                } catch (RuntimeException $e) {
                    Notification::make()->title('Envoi impossible')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title('Demande de signature envoyée')
                    ->body($request->nombreSignataires().' signataire(s) notifié(s).')
                    ->success()
                    ->send();
            });
    }

    /**
     * Simuler la signature de toutes les parties (driver « simulation » only) :
     * déroule le parcours complet jusqu'au contrat signé, pour la démo.
     */
    public static function simulerSignature(): Action
    {
        return Action::make('simulerSignature')
            ->label('Simuler la signature (démo)')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Simuler la signature de toutes les parties ? Le contrat passera à « Signé ».')
            ->visible(fn (Contract $record) => app(SignatureService::class)->provider()->nom() === 'simulation'
                && $record->signatureRequests()
                    ->whereIn('statut', SignatureRequestStatut::enCours())
                    ->exists())
            ->action(function (Contract $record) {
                $request = $record->signatureRequests()
                    ->whereIn('statut', SignatureRequestStatut::enCours())
                    ->latest()
                    ->first();

                if ($request === null) {
                    return;
                }

                app(SignatureService::class)->simulerSignatureComplete($request);

                Notification::make()
                    ->title('Contrat signé (simulation)')
                    ->body('Toutes les parties ont signé. Preuve archivée dans la GED.')
                    ->success()
                    ->send();
            });
    }

    private static function executer(Contract $record, ContractStatut $cible, ?string $comment = null): void
    {
        try {
            $record->transitionTo($cible, $comment);

            Notification::make()
                ->title('Statut du contrat : '.$cible->getLabel())
                ->success()
                ->send();
        } catch (InvalidTransitionException $e) {
            Notification::make()
                ->title('Transition refusée')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
