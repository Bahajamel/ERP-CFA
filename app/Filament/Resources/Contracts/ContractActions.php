<?php

namespace App\Filament\Resources\Contracts;

use App\Cerfa\CerfaApprentissage;
use App\Enums\ContractSignatureStatut;
use App\Enums\ContractStatut;
use App\Enums\SignatureRequestStatut;
use App\Jobs\GenererLivrablesJob;
use App\Livret\LivretRsClient;
use App\Models\CfaProfile;
use App\Models\Contract;
use App\Services\ContractDocumentService;
use App\Services\SignatureService;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
            ->visible(fn (Contract $record) => $record->statut_contrat->canTransitionTo(ContractStatut::Complet))
            ->action(fn (Contract $record) => self::executer($record, ContractStatut::Complet));
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

    /**
     * Télécharger le CERFA 10103*14 pré-rempli automatiquement à partir des
     * données du contrat (employeur, apprenti, maître d'apprentissage,
     * formation, rémunération légale). Le document officiel est prêt à
     * imprimer et à signer.
     */
    public static function telechargerCerfa(): Action
    {
        return Action::make('telechargerCerfa')
            ->label('CERFA pré-rempli')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('primary')
            ->visible(fn () => auth()->user()?->can('access_contracts') ?? false)
            ->action(function (Contract $record): StreamedResponse {
                $pdf = app(CerfaApprentissage::class)->pour($record);
                $nom = 'CERFA_'.str($record->candidate?->nom_complet ?? 'contrat_'.$record->id)->slug().'.pdf';

                return response()->streamDownload(fn () => print($pdf), $nom, [
                    'Content-Type' => 'application/pdf',
                ]);
            });
    }

    /**
     * Générer le CERFA pré-rempli : il est enregistré dans les documents du
     * contrat ET téléchargé immédiatement (un seul geste). Signale les
     * informations manquantes sans bloquer.
     */
    public static function genererCerfa(): Action
    {
        return Action::make('genererCerfa')
            ->label('Générer le CERFA')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('primary')
            ->visible(fn () => auth()->user()?->can('access_contracts') ?? false)
            ->action(function (Contract $record) {
                $service = app(ContractDocumentService::class);
                $manquants = $service->champsManquantsCerfa($record);
                $document = $service->genererCerfa($record);

                self::notifierGeneration('CERFA', $manquants);

                return self::telecharger($document);
            });
    }

    /**
     * Générer la convention de formation (Annexe n°2) pré-remplie : enregistrée
     * dans les documents du contrat ET téléchargée immédiatement. Cohérente
     * avec le CERFA (mêmes données). Signale les informations manquantes.
     */
    public static function genererConvention(): Action
    {
        return Action::make('genererConvention')
            ->label('Générer la convention')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('primary')
            ->visible(fn () => auth()->user()?->can('access_contracts') ?? false)
            ->action(function (Contract $record) {
                $service = app(ContractDocumentService::class);
                $manquants = $service->champsManquantsConvention($record);
                $document = $service->genererConvention($record);

                self::notifierGeneration('Convention', $manquants);

                return self::telecharger($document);
            });
    }

    /** Télécharge le fichier d'un document généré (PDF), sans le supprimer. */
    private static function telecharger(\App\Models\Document $document): ?StreamedResponse
    {
        $media = $document->getFirstMedia('fichier');

        if ($media === null) {
            return null;
        }

        return response()->streamDownload(
            fn () => print(file_get_contents($media->getPath())),
            $media->file_name,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Vérifier l'état documentaire du contrat avant génération : score de
     * complétude, état de chaque document et informations manquantes.
     */
    public static function verifierDocuments(): Action
    {
        return Action::make('verifierDocuments')
            ->label('Vérifier avant génération')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->modalHeading(fn (Contract $record): string => 'Documents du contrat — '
                .($record->candidate?->nom_complet ?? 'contrat'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->modalContent(fn (Contract $record) => view('filament.contracts.completude', [
                'etat' => app(ContractDocumentService::class)->completude($record),
            ]));
    }

    /** Notifie la génération d'un document, en listant les champs manquants. */
    private static function notifierGeneration(string $document, array $manquants): void
    {
        if (filled($manquants)) {
            Notification::make()
                ->warning()
                ->title($document.' généré — informations à compléter')
                ->body('Le document contient des zones à compléter ('.count($manquants).') : '
                    .implode(', ', array_slice($manquants, 0, 6)).(count($manquants) > 6 ? '…' : '.').' '
                    .'Complétez le contrat puis régénérez.')
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($document.' généré avec succès')
            ->body('Le document est disponible dans la section « Documents » du contrat.')
            ->send();
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
