<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatut;
use App\Finance\InvoiceGenerator;
use App\Models\Invoice;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Actions opérationnelles de la facturation (Phase A) : émettre, télécharger le
 * proforma, importer la facture comptable, annuler. Le cycle de vie repose sur
 * la machine à états de {@see Invoice} ; l'ERP n'est pas le logiciel comptable.
 */
class InvoiceActions
{
    /**
     * Émettre une facture (Brouillon → Émise). Le numéro légal est optionnel :
     * il peut être attribué par la comptabilité et saisi/importé plus tard.
     * L'émission est refusée sans montant ni destinataire (garde du modèle).
     */
    public static function emettre(): Action
    {
        return Action::make('emettre')
            ->label('Émettre')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->visible(fn (Invoice $record): bool => $record->statut === InvoiceStatut::Brouillon)
            ->modalHeading('Émettre la facture')
            ->modalDescription('La facture passe en « Émise » : elle engage le CFA et entre dans le suivi financier.')
            ->schema([
                TextInput::make('numero')
                    ->label('Numéro de facture (comptabilité)')
                    ->helperText('Facultatif : laissez vide si le numéro légal sera attribué par la comptabilité.')
                    ->unique(Invoice::class, 'numero', ignoreRecord: true),
                DatePicker::make('date_emission')
                    ->label('Date d\'émission')
                    ->default(now()),
            ])
            ->action(function (Invoice $record, array $data): void {
                try {
                    $record->forceFill(array_filter([
                        'numero' => $data['numero'] ?? null,
                        'date_emission' => $data['date_emission'] ?? null,
                    ], fn ($v) => filled($v)));

                    $record->transitionTo(InvoiceStatut::Emise);

                    Notification::make()->success()
                        ->title('Facture émise')
                        ->body($record->numero ? 'N° '.$record->numero : 'En attente du numéro comptable.')
                        ->send();
                } catch (InvalidTransitionException $e) {
                    Notification::make()->danger()
                        ->title('Émission refusée')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    /** Télécharger le proforma PDF (sans valeur comptable), sans l'archiver. */
    public static function proforma(): Action
    {
        return Action::make('proforma')
            ->label('Proforma')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(fn (Invoice $record): StreamedResponse => response()->streamDownload(
                fn () => print (app(InvoiceGenerator::class)->pdf($record)),
                'proforma-facture-'.$record->id.'.pdf',
                ['Content-Type' => 'application/pdf'],
            ));
    }

    /**
     * Importer la facture comptable officielle (PDF + numéro). La pièce fiscale
     * fait foi : elle est archivée dans la GED et la facture est marquée émise.
     */
    public static function importer(): Action
    {
        return Action::make('importerFacture')
            ->label('Importer la facture')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn (Invoice $record): bool => $record->statut !== InvoiceStatut::Annulee)
            ->modalHeading('Importer la facture comptable')
            ->schema([
                TextInput::make('numero')
                    ->label('Numéro de facture')
                    ->required()
                    ->unique(Invoice::class, 'numero', ignoreRecord: true),
                FileUpload::make('fichier')
                    ->label('Facture (PDF)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(10240)
                    ->disk('public')
                    ->storeFileNamesIn('nom_original')
                    ->required(),
            ])
            ->action(function (Invoice $record, array $data): void {
                $document = app(InvoiceGenerator::class)->importer(
                    $record,
                    $data['fichier'],
                    $data['nom_original'] ?? null,
                    Auth::id(),
                );

                $record->forceFill([
                    'numero' => $data['numero'],
                    'importee' => true,
                    'date_emission' => $record->date_emission ?? now()->toDateString(),
                ])->saveQuietly();

                // Une facture comptable importée est de fait émise.
                if ($record->statut === InvoiceStatut::Brouillon) {
                    try {
                        $record->transitionTo(InvoiceStatut::Emise);
                    } catch (InvalidTransitionException) {
                        // Garde du modèle non satisfaite : on laisse en brouillon.
                    }
                }

                Notification::make()->success()
                    ->title('Facture importée')
                    ->body('Version '.$document->version.' archivée dans les documents.')
                    ->send();
            })
            ->modalSubmitActionLabel('Importer');
    }

    /** Annuler une facture (brouillon ou émise) : elle sort du suivi financier. */
    public static function annuler(): Action
    {
        return Action::make('annuler')
            ->label('Annuler')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Annuler cette facture ? Elle ne sera plus comptée dans le suivi financier.')
            ->visible(fn (Invoice $record): bool => in_array(
                $record->statut,
                [InvoiceStatut::Brouillon, InvoiceStatut::Emise],
                true,
            ))
            ->action(function (Invoice $record): void {
                try {
                    $record->transitionTo(InvoiceStatut::Annulee);

                    Notification::make()->success()->title('Facture annulée')->send();
                } catch (InvalidTransitionException $e) {
                    Notification::make()->danger()
                        ->title('Annulation refusée')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }
}