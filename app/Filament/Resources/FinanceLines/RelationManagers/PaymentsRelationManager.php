<?php

namespace App\Filament\Resources\FinanceLines\RelationManagers;

use App\Enums\InvoiceStatut;
use App\Filament\Resources\FinanceLines\Tables\FinanceLinesTable;
use App\Models\FinancePayment;
use App\Models\Invoice;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Encaissements d'une ligne financière (P1-16-2). Un paiement peut être rattaché
 * à une facture précise ; lorsqu'une facture est soldée, elle passe à « Payée ».
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Encaissements';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-banknotes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('invoice_id')
                ->label('Facture rattachée')
                ->options(fn (): array => $this->facturesOptions())
                ->placeholder('Sans facture précise')
                ->helperText('Optionnel : rattache le paiement à une facture émise.'),
            TextInput::make('montant')
                ->label('Montant')
                ->numeric()
                ->minValue(0)
                ->prefix('€')
                ->required(),
            DatePicker::make('date_paiement')
                ->label('Date du paiement')
                ->default(now())
                ->displayFormat('d/m/Y')
                ->required(),
            Select::make('moyen')
                ->label('Moyen')
                ->options([
                    'Virement' => 'Virement',
                    'Chèque' => 'Chèque',
                    'Prélèvement' => 'Prélèvement',
                    'Espèces' => 'Espèces',
                ]),
            TextInput::make('reference')
                ->label('Référence')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date_paiement')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('montant')
                    ->label('Montant')
                    ->formatStateUsing(fn ($state): string => FinanceLinesTable::euros($state))
                    ->color('success')
                    ->alignEnd(),
                TextColumn::make('invoice.numero')
                    ->label('Facture')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('moyen')
                    ->label('Moyen')
                    ->placeholder('—'),
                TextColumn::make('reference')
                    ->label('Référence')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Enregistrer un encaissement')
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = Auth::id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('date_paiement', 'desc');
    }

    /** Factures de la ligne, libellées pour le select (émises listées en premier). */
    protected function facturesOptions(): array
    {
        return $this->getOwnerRecord()->invoices()
            ->orderByRaw("statut = '".InvoiceStatut::Emise->value."' desc")
            ->get()
            ->mapWithKeys(fn (Invoice $invoice): array => [
                $invoice->id => ($invoice->numero ?: 'Brouillon #'.$invoice->id)
                    .' — '.FinanceLinesTable::euros($invoice->montant),
            ])
            ->all();
    }
}
