<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatut;
use App\Finance\InvoiceGenerator;
use App\Models\FinancePayment;
use App\Models\Invoice;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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

    /** Moyens de paiement proposés à la saisie d'un encaissement. */
    private const MOYENS_PAIEMENT = [
        'Virement' => 'Virement',
        'Chèque' => 'Chèque',
        'Prélèvement' => 'Prélèvement',
        'Espèces' => 'Espèces',
        'Carte bancaire' => 'Carte bancaire',
    ];

    /**
     * Enregistrer un encaissement sur une facture émise (Phase B). Le montant
     * par défaut est le reste à payer ; la facture bascule automatiquement en
     * « Payée » dès qu'elle est soldée (règle portée par {@see FinancePayment}).
     */
    public static function encaisser(): Action
    {
        return Action::make('encaisser')
            ->label('Encaisser')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->statut === InvoiceStatut::Emise
                && $record->resteAPayer() > 0)
            ->modalHeading('Enregistrer un encaissement')
            ->modalDescription(fn (Invoice $record): string => 'Reste à payer : '
                .number_format($record->resteAPayer(), 2, ',', ' ').' € — '
                .'la facture passera « Payée » une fois soldée.')
            ->schema([
                TextInput::make('montant')
                    ->label('Montant encaissé')
                    ->numeric()
                    ->prefix('€')
                    ->required()
                    ->minValue(0.01)
                    ->default(fn (Invoice $record): float => $record->resteAPayer())
                    ->maxValue(fn (Invoice $record): float => $record->resteAPayer())
                    ->helperText('Ne peut pas dépasser le reste à payer.'),
                DatePicker::make('date_paiement')
                    ->label('Date de l\'encaissement')
                    ->required()
                    ->default(now())
                    ->maxDate(now()),
                Select::make('moyen')
                    ->label('Moyen de paiement')
                    ->options(self::MOYENS_PAIEMENT)
                    ->placeholder('Non précisé'),
                TextInput::make('reference')
                    ->label('Référence')
                    ->helperText('N° de virement, de chèque, etc. (facultatif)')
                    ->maxLength(255),
            ])
            ->action(function (Invoice $record, array $data): void {
                FinancePayment::create([
                    'invoice_id' => $record->id,
                    'finance_line_id' => $record->finance_line_id,
                    'montant' => $data['montant'],
                    'date_paiement' => $data['date_paiement'],
                    'moyen' => $data['moyen'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $record->refresh();

                $solde = $record->statut === InvoiceStatut::Payee;

                Notification::make()->success()
                    ->title($solde ? 'Facture soldée' : 'Encaissement enregistré')
                    ->body($solde
                        ? 'La facture est intégralement payée.'
                        : 'Reste à payer : '.number_format($record->resteAPayer(), 2, ',', ' ').' €.')
                    ->send();
            })
            ->modalSubmitActionLabel('Enregistrer l\'encaissement');
    }

    /**
     * Consulter l'historique des encaissements d'une facture (Phase B).
     * Lecture seule : rappel des versements déjà saisis et du reste à payer.
     */
    public static function paiements(): Action
    {
        return Action::make('paiements')
            ->label('Encaissements')
            ->icon('heroicon-o-list-bullet')
            ->color('gray')
            ->modalHeading('Historique des encaissements')
            ->visible(fn (Invoice $record): bool => $record->payments()->exists())
            ->modalContent(fn (Invoice $record) => view(
                'filament.resources.invoices.payments-history',
                ['invoice' => $record],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer');
    }

    /**
     * Relancer une facture impayée échue (Phase C) : ouvre/actualise la tâche de
     * relance (visible dans Tâches & Alertes) sans quitter l'écran Factures.
     */
    public static function relancer(): Action
    {
        return Action::make('relancer')
            ->label('Relancer')
            ->icon('heroicon-o-bell-alert')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Relancer l\'impayé')
            ->modalDescription(fn (Invoice $record): string => 'Créer une tâche de relance pour cette facture échue depuis '
                .$record->joursDeRetard().' jour(s) (reste '
                .number_format($record->resteAPayer(), 2, ',', ' ').' €) ?')
            ->modalSubmitActionLabel('Créer la relance')
            ->visible(fn (Invoice $record): bool => $record->estEnRetard())
            ->action(function (Invoice $record): void {
                $tache = $record->ouvrirRelance(Auth::id());

                if ($tache === null) {
                    Notification::make()->warning()
                        ->title('Aucune relance nécessaire')
                        ->body('Cette facture n\'est plus en retard.')
                        ->send();

                    return;
                }

                Notification::make()->success()
                    ->title('Relance ouverte')
                    ->body('Tâche « '.$tache->titre.' » à traiter dans Tâches & Alertes.')
                    ->send();
            });
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