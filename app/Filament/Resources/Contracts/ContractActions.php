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

    /**
     * Envoyer le CERFA et la convention aux parties, pour signature manuscrite.
     *
     * Circuit retenu à la place d'un prestataire de signature électronique, dont
     * l'abonnement API ne se justifie pas au volume du CFA : on envoie, la partie
     * imprime, signe, scanne et renvoie ; le scan est déposé via
     * {@see deposerDocumentsSignes()}, qui clôt le contrat.
     *
     * Les documents sont aussi enregistrés en GED au passage : on garde trace de
     * ce qui a été envoyé, exactement.
     */
    public static function envoyerDocumentsASigner(): Action
    {
        return Action::make('envoyerDocumentsASigner')
            ->label('Envoyer à signer')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->visible(fn (Contract $record): bool => (auth()->user()?->can('access_contracts') ?? false)
                && $record->statut_signature !== ContractSignatureStatut::Signe)
            ->modalHeading('Envoyer le contrat à signer')
            ->modalDescription('Le CERFA et la convention seront joints au message. Les destinataires impriment, signent, scannent et renvoient les documents.')
            ->modalSubmitActionLabel('Envoyer')
            ->fillForm(fn (Contract $record): array => [
                'destinataires' => array_values(array_filter([
                    $record->candidate?->email,
                    $record->tuteur?->email ?? $record->company?->contactPrincipal->first()?->email,
                ])),
            ])
            ->schema([
                Select::make('destinataires')
                    ->label('Destinataires')
                    ->multiple()
                    ->required()
                    ->options(fn (Contract $record): array => collect([
                        $record->candidate?->email => 'Apprenti — '.($record->candidate?->nom_complet ?? ''),
                        $record->tuteur?->email => 'Tuteur — '.($record->tuteur?->nom_complet ?? ''),
                        // ⚠️ contactPrincipal() est un HasMany malgré son nom au
                        // singulier : il renvoie une collection, pas un contact.
                        $record->company?->contactPrincipal->first()?->email => 'Contact entreprise — '.($record->company?->contactPrincipal->first()?->nom_complet ?? ''),
                    ])->filter(fn ($libelle, $email): bool => filled($email))->all())
                    ->helperText('Pré-rempli depuis le dossier. Ajoutez ou retirez librement.'),
                \Filament\Forms\Components\Textarea::make('message')
                    ->label('Message d\'accompagnement (optionnel)')
                    ->rows(3)
                    ->placeholder('ex : merci de nous retourner les documents signés avant le 30 du mois.'),
            ])
            ->action(function (Contract $record, array $data): void {
                $service = app(ContractDocumentService::class);

                // Bloquer sur un document incomplet vaut mieux qu'envoyer un CERFA
                // troué à un employeur : il faudrait refaire tout le circuit
                // (impression, signature, scan, retour). Une convention sans SIRET
                // du CFA n'est de toute façon pas valable.
                $manquantsCfa = $service->champsManquantsCfa();
                $manquantsContrat = array_values(array_diff(
                    array_unique(array_merge(
                        $service->champsManquantsCerfa($record),
                        $service->champsManquantsConvention($record),
                    )),
                    $manquantsCfa,
                ));

                if ($manquantsCfa !== [] || $manquantsContrat !== []) {
                    // On distingue ce qui se corrige dans le contrat de ce qui
                    // relève des paramètres du CFA : sans ça, l'utilisateur cherche
                    // le « SIRET du CFA » dans la fiche contrat, où il n'est pas.
                    $body = collect([
                        $manquantsContrat !== []
                            ? 'Dans ce contrat : '.implode(', ', $manquantsContrat).'.'
                            : null,
                        $manquantsCfa !== []
                            ? 'Dans Administration → Paramètres du CFA : '.implode(', ', $manquantsCfa).'.'
                            : null,
                    ])->filter()->implode(' ');

                    Notification::make()
                        ->danger()
                        ->title('Envoi annulé — documents incomplets')
                        ->body($body)
                        ->persistent()
                        ->send();

                    return;
                }

                $pieces = [
                    'CERFA_'.$record->id.'.pdf' => app(CerfaApprentissage::class)->pour($record),
                    'Convention_'.$record->id.'.pdf' => app(\App\Documents\ConventionFormation::class)->pour($record),
                ];

                // Trace de ce qui part réellement.
                $service->genererCerfa($record);
                $service->genererConvention($record);

                foreach ($data['destinataires'] as $email) {
                    \Illuminate\Support\Facades\Mail::to($email)->send(
                        new \App\Mail\DocumentsASigner($record, $pieces, $data['message'] ?? '')
                    );
                }

                $record->forceFill([
                    'statut_signature' => ContractSignatureStatut::Envoye->value,
                ])->save();

                Notification::make()
                    ->success()
                    ->title('Contrat envoyé à signer')
                    ->body(count($data['destinataires']).' destinataire(s). Déposez les documents signés dès leur retour.')
                    ->send();
            });
    }

    /**
     * Déposer les documents signés reçus en retour (scan ou photo) : ils entrent
     * en GED comme pièce contractuelle et le contrat passe à « Signé ».
     *
     * C'est la contrepartie de {@see envoyerDocumentsASigner()} : sans dépôt, le
     * contrat resterait indéfiniment « Envoyé ».
     */
    public static function deposerDocumentsSignes(): Action
    {
        return Action::make('deposerDocumentsSignes')
            ->label('Déposer les documents signés')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('success')
            ->visible(fn (Contract $record): bool => (auth()->user()?->can('access_contracts') ?? false)
                && $record->statut_signature !== ContractSignatureStatut::Signe)
            ->modalHeading('Documents signés reçus')
            ->modalDescription('Déposez le contrat signé par toutes les parties (scan ou photo lisible). Le contrat passera à « Signé ».')
            ->modalSubmitActionLabel('Enregistrer')
            ->schema([
                \Filament\Forms\Components\FileUpload::make('fichiers')
                    ->label('Documents signés')
                    ->multiple()
                    ->required()
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(10240)
                    ->helperText('PDF, JPEG ou PNG — 10 Mo maximum par fichier.'),
            ])
            ->action(function (Contract $record, array $data): void {
                foreach ($data['fichiers'] as $fichier) {
                    $chemin = \Illuminate\Support\Facades\Storage::disk('local')->path($fichier);

                    $document = $record->documents()->create([
                        'type' => \App\Enums\DocumentType::Contrat->value,
                        'nom_fichier' => 'Contrat signé — '.($record->candidate?->nom_complet ?? "contrat {$record->id}"),
                        // « Reçu » : le document nous revient signé de l'extérieur.
                        'statut' => \App\Enums\DocumentStatut::Recu->value,
                        'source' => \App\Enums\DocumentSource::Manuel->value,
                        'uploaded_by' => auth()->id(),
                    ]);

                    $document->addMedia($chemin)->toMediaCollection('fichier');
                }

                $record->forceFill([
                    'statut_signature' => ContractSignatureStatut::Signe->value,
                ])->save();

                Notification::make()
                    ->success()
                    ->title('Contrat signé')
                    ->body('Les documents signés sont archivés dans les pièces du contrat.')
                    ->send();
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
