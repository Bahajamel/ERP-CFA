<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\ContractStatut;
use App\Livret\LivrablePackImporter;
use App\Livret\LivrablePayloadBuilder;
use App\Livret\LivretRsClient;
use App\Livret\LivretRsException;
use App\Models\CfaProfile;
use App\Models\Contract;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                // La génération (rendu de plusieurs PDF) dépasse la limite web
                // par défaut (30 s) ; on l'aligne sur le timeout du service.
                @set_time_limit((int) config('services.livretrs.timeout', 180) + 30);

                try {
                    $payload = app(LivrablePayloadBuilder::class)->pour($record, $data);
                    $zip = app(LivretRsClient::class)->genererLivrables($payload);
                } catch (LivretRsException|RuntimeException $e) {
                    Notification::make()
                        ->title('Génération impossible')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $result = app(LivrablePackImporter::class)->import($record, $zip, auth()->id());
                } finally {
                    @unlink($zip);
                }

                $details = $result->reconnus.' livrable(s) reconnu(s), '
                    .$result->missionsRattachees.' rattachement(s) de mission.';

                if ($result->nonReconnus() > 0) {
                    $details .= ' '.$result->nonReconnus().' pièce(s) à taguer manuellement.';
                }

                Notification::make()
                    ->title($result->importes.' livrable(s) générés et importés')
                    ->body($details)
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
