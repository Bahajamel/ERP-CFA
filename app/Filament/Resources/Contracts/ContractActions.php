<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\ContractStatut;
use App\Enums\RuptureInitiateur;
use App\Enums\RuptureMotif;
use App\Jobs\GenererLivrablesJob;
use App\Livret\LivrablePackImporter;
use App\Livret\LivretRsClient;
use App\Enums\ContractSignatureStatut;
use App\Enums\SignatureRequestStatut;
use App\Models\CfaProfile;
use App\Models\Contract;
use App\Services\RuptureService;
use App\Services\SignatureService;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
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

    /** Transition générique vers un état réellement atteignable. */
    public static function changerStatut(): Action
    {
        return Action::make('changerStatut')
            ->label('Faire évoluer')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Contract $record) => count($record->allowedTransitions()) > 0)
            ->schema([
                Select::make('statut')
                    ->label('Nouveau statut')
                    ->options(fn (Contract $record) => collect($record->allowedTransitions())
                        ->mapWithKeys(fn (ContractStatut $s) => [$s->value => $s->getLabel()])
                        ->all())
                    ->required(),
                Textarea::make('commentaire')
                    ->label('Commentaire (optionnel)'),
            ])
            ->action(fn (Contract $record, array $data) => self::executer(
                $record,
                ContractStatut::from($data['statut']),
                $data['commentaire'] ?? null,
            ));
    }

    /**
     * Importer un pack de livrables généré par LivretRS (ZIP) : chaque PDF est
     * classé dans la GED de l'apprenti, marqué « généré par LivretRS », avec
     * les missions CFA suggérées. L'archive n'est pas conservée après import.
     */
    public static function importerLivrables(): Action
    {
        return Action::make('importerLivrables')
            ->label('Importer les livrables')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('info')
            ->visible(fn (Contract $record) => $record->candidate !== null
                && (auth()->user()?->can('access_documents') ?? false))
            ->modalHeading('Importer un pack de livrables LivretRS')
            ->modalDescription('Déposez l\'archive ZIP produite par LivretRS. Chaque PDF sera classé '
                .'dans la GED de l\'apprenti, marqué « généré par LivretRS », avec les missions CFA '
                .'suggérées (que vous pourrez ajuster ensuite).')
            ->schema([
                FileUpload::make('archive')
                    ->label('Archive ZIP des livrables')
                    ->disk('local')
                    ->directory('livret-imports')
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'])
                    ->required(),
            ])
            ->action(function (Contract $record, array $data) {
                $chemin = Storage::disk('local')->path($data['archive']);

                try {
                    $result = app(LivrablePackImporter::class)->import($record, $chemin, auth()->id());
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->title('Import impossible')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                } finally {
                    Storage::disk('local')->delete($data['archive']);
                }

                $details = $result->reconnus.' livrable(s) reconnu(s), '
                    .$result->missionsRattachees.' rattachement(s) de mission.';

                if ($result->nonReconnus() > 0) {
                    $details .= ' '.$result->nonReconnus().' pièce(s) à taguer manuellement.';
                }

                Notification::make()
                    ->title($result->importes.' livrable(s) importé(s) dans la GED')
                    ->body($details)
                    ->success()
                    ->send();
            });
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
     * Ouvrir un dossier de rupture depuis le contrat : le contrat passe à
     * « Rompu » et la régularisation OPCO / Finance est tracée (EPIC-18).
     * Visible pour un contrat engagé sans dossier déjà ouvert.
     */
    public static function ouvrirRupture(): Action
    {
        return Action::make('ouvrirRupture')
            ->label('Ouvrir un dossier de rupture')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('danger')
            ->visible(fn (Contract $record) => (auth()->user()?->can('access_ruptures') ?? false)
                && $record->ruptureCase()->doesntExist()
                && $record->statut_contrat->canTransitionTo(ContractStatut::Rompu))
            ->modalHeading('Ouvrir un dossier de rupture')
            ->modalDescription('Le contrat passera à « Rompu » et une tâche de régularisation sera créée '
                .'côté OPCO et côté Finance. L\'apprenti pourra être accompagné vers un nouvel employeur.')
            ->modalSubmitActionLabel('Ouvrir le dossier')
            ->schema([
                DatePicker::make('date_rupture')
                    ->label('Date de rupture')
                    ->displayFormat('d/m/Y')
                    ->default(now())
                    ->required(),
                Select::make('motif')
                    ->label('Motif de rupture')
                    ->options(RuptureMotif::class)
                    ->default(RuptureMotif::CommunAccord->value)
                    ->required(),
                Select::make('initiateur')
                    ->label("À l'initiative de")
                    ->options(RuptureInitiateur::class)
                    ->default(RuptureInitiateur::CommunAccord->value)
                    ->required(),
                Textarea::make('motif_detail')
                    ->label('Précisions (optionnel)'),
            ])
            ->action(function (Contract $record, array $data) {
                app(RuptureService::class)->ouvrir($record, [
                    'date_rupture' => $data['date_rupture'],
                    'motif' => $data['motif'],
                    'initiateur' => $data['initiateur'],
                    'motif_detail' => $data['motif_detail'] ?? null,
                ]);

                Notification::make()
                    ->title('Dossier de rupture ouvert')
                    ->body('Le contrat est passé à « Rompu ». Régularisation OPCO / Finance créée.')
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
