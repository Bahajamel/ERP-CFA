<?php

namespace App\Filament\Resources\FinanceLines\RelationManagers;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Enums\InvoiceStatut;
use App\Enums\TaskPriorite;
use App\Enums\TaskStatut;
use App\Filament\Resources\FinanceLines\Tables\FinanceLinesTable;
use App\Finance\InvoiceGenerator;
use App\Models\FinanceLine;
use App\Models\Invoice;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Factures d'une ligne financière (P1-16-3). Une facture est créée en brouillon
 * (générée ou importée), puis émise (numérotée) — l'émission est refusée sans
 * montant ni destinataire (P1-16-4).
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Factures';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-currency-euro';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('destinataire')
                ->label('Destinataire (client facturé)')
                ->maxLength(255)
                ->columnSpanFull(),
            TextInput::make('montant')
                ->label('Montant')
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->prefix('€')
                ->required(),
            DatePicker::make('date_echeance')
                ->label('Échéance')
                ->displayFormat('d/m/Y'),
            Textarea::make('commentaire')
                ->label('Commentaire')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero')
            ->columns([
                TextColumn::make('numero')
                    ->label('N°')
                    ->placeholder('brouillon')
                    ->searchable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('destinataire')
                    ->label('Destinataire')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('montant')
                    ->label('Montant')
                    ->formatStateUsing(fn ($state): string => FinanceLinesTable::euros($state))
                    ->alignEnd(),
                TextColumn::make('reste')
                    ->label('Reste à payer')
                    ->state(fn (Invoice $record): string => FinanceLinesTable::euros($record->resteAPayer()))
                    ->alignEnd(),
                TextColumn::make('date_echeance')
                    ->label('Échéance')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                IconColumn::make('retard')
                    ->label('Retard')
                    ->state(fn (Invoice $record): bool => $record->estEnRetard())
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nouvelle facture (brouillon)')
                    ->mutateDataUsing(function (array $data): array {
                        $data['statut'] = InvoiceStatut::Brouillon->value;
                        $data['created_by'] = Auth::id();

                        return $data;
                    }),
                Action::make('importer')
                    ->label('Importer une facture')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->modalDescription('Rattache la facture émise par la comptabilité (source de vérité).')
                    ->schema([
                        TextInput::make('numero')
                            ->label('N° de facture (comptabilité)')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('destinataire')
                            ->label('Destinataire')
                            ->required(),
                        TextInput::make('montant')
                            ->label('Montant')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->required(),
                        DatePicker::make('date_emission')
                            ->label('Date d\'émission')
                            ->default(now())
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('date_echeance')
                            ->label('Échéance')
                            ->displayFormat('d/m/Y'),
                        FileUpload::make('fichier')
                            ->label('Fichier de la facture (PDF)')
                            ->disk('public')
                            ->directory('imports/factures')
                            ->acceptedFileTypes(['application/pdf'])
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->importerFacture($data)),
            ])
            ->recordActions([
                Action::make('emettre')
                    ->label('Marquer émise')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->modalHeading('Facture émise en comptabilité')
                    ->modalDescription('Renseigne le numéro de la facture émise par la comptabilité.')
                    ->visible(fn (Invoice $record): bool => $record->statut === InvoiceStatut::Brouillon)
                    ->schema([
                        TextInput::make('numero')
                            ->label('N° de facture (comptabilité)')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('date_emission')
                            ->label('Date d\'émission')
                            ->default(now())
                            ->displayFormat('d/m/Y')
                            ->required(),
                    ])
                    ->action(function (Invoice $record, array $data): void {
                        $record->numero = $data['numero'];
                        $record->date_emission = $data['date_emission'];
                        $this->appliquerTransition($record, InvoiceStatut::Emise, 'Facture marquée émise');
                    }),
                Action::make('genererProforma')
                    ->label('Proforma (PDF)')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->tooltip('Document interne sans valeur comptable')
                    ->visible(fn (Invoice $record): bool => $record->statut === InvoiceStatut::Brouillon)
                    ->action(function (Invoice $record): void {
                        $document = app(InvoiceGenerator::class)->generer($record, Auth::id());

                        Notification::make()
                            ->success()
                            ->title('Proforma généré')
                            ->body("Document interne v{$document->version} (sans valeur comptable).")
                            ->send();
                    }),
                Action::make('relancer')
                    ->label('Relancer')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->tooltip('Créer une tâche de relance pour cet impayé')
                    ->visible(fn (Invoice $record): bool => $record->estEnRetard())
                    ->requiresConfirmation()
                    ->modalDescription('Créer une tâche de relance pour cette facture échue impayée ?')
                    ->action(fn (Invoice $record) => $this->relancerImpaye($record)),
                Action::make('annuler')
                    ->label('Annuler')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Invoice $record): bool => in_array($record->statut, [InvoiceStatut::Brouillon, InvoiceStatut::Emise], true))
                    ->action(fn (Invoice $record) => $this->appliquerTransition($record, InvoiceStatut::Annulee, 'Facture annulée')),
                EditAction::make()
                    ->visible(fn (Invoice $record): bool => $record->statut === InvoiceStatut::Brouillon),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /** Applique une transition d'état en remontant l'erreur métier éventuelle. */
    protected function appliquerTransition(Invoice $invoice, InvoiceStatut $to, string $succes): void
    {
        try {
            $invoice->transitionTo($to);

            Notification::make()->success()->title($succes)->send();
        } catch (InvalidTransitionException $e) {
            Notification::make()->danger()->title('Action impossible')->body($e->getMessage())->send();
        }
    }

    /**
     * Rattache une facture émise par la comptabilité : créée directement en
     * « Émise » (la pièce fiscale existe déjà) avec son PDF archivé en GED.
     */
    protected function importerFacture(array $data): void
    {
        /** @var FinanceLine $line */
        $line = $this->getOwnerRecord();

        $invoice = $line->invoices()->create([
            'statut' => InvoiceStatut::Emise->value,
            'numero' => $data['numero'],
            'destinataire' => $data['destinataire'],
            'montant' => $data['montant'],
            'date_emission' => $data['date_emission'],
            'date_echeance' => $data['date_echeance'] ?? null,
            'importee' => true,
            'created_by' => Auth::id(),
        ]);

        $document = $invoice->documents()->create([
            'type' => DocumentType::Facture,
            'statut' => DocumentStatut::Recu,
            'nom_fichier' => 'Facture '.$data['numero'],
            'version' => 1,
            'uploaded_by' => Auth::id(),
        ]);

        $document->addMediaFromDisk($data['fichier'], 'public')
            ->toMediaCollection('fichier');

        Notification::make()
            ->success()
            ->title('Facture importée')
            ->body('Facture '.$data['numero'].' rattachée et archivée dans la GED.')
            ->send();
    }

    /**
     * Relance d'impayé : crée (idempotent) une tâche de relance rattachée à la
     * facture, assignée à son émetteur. Même clé que l'alerte automatique pour
     * éviter les doublons.
     */
    protected function relancerImpaye(Invoice $record): void
    {
        $tache = $record->tasks()->firstOrCreate(
            ['cle' => $record->cleRelance()],
            [
                'titre' => 'Relancer la facture impayée '.($record->numero ?: '#'.$record->id),
                'description' => 'Facture échue le '.$record->date_echeance?->format('d/m/Y')
                    .' — reste '.FinanceLinesTable::euros($record->resteAPayer()).' à encaisser.',
                'assignee_id' => $record->created_by,
                'due_date' => now(),
                'priorite' => TaskPriorite::Haute->value,
                'statut' => TaskStatut::AFaire->value,
                'source' => 'manuelle',
            ],
        );

        Notification::make()
            ->{$tache->wasRecentlyCreated ? 'success' : 'info'}()
            ->title($tache->wasRecentlyCreated ? 'Relance créée' : 'Relance déjà en cours')
            ->body('Tâche de relance dans la section Tâches.')
            ->send();
    }
}
