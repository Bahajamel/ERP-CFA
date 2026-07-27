<?php

namespace App\Filament\Resources\OpcoFiles\RelationManagers;

use App\Enums\PaymentStatut;
use App\Models\OpcoFile;
use App\Models\OpcoPayment;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Échéancier de versement (décret 2025-585)';

    public function table(Table $table): Table
    {
        return $table
            // Un échéancier dont le total ne fait plus le montant accepté annonce
            // un financement qui n'existe plus. On ne peut pas le refaire (des
            // versements sont déjà encaissés, et l'argent reçu est un fait) : on
            // le dit, plutôt que d'afficher un plan périmé sans commentaire.
            ->description(function (): ?string {
                /** @var OpcoFile $dossier */
                $dossier = $this->getOwnerRecord();

                if (! $dossier->echeancierEstPerime()) {
                    return null;
                }

                return sprintf(
                    'Échéancier périmé : le plan totalise %s € alors que le montant accepté est de %s €. '
                    .'Des versements sont déjà encaissés — ajustez les échéances restantes à la main.',
                    number_format((float) $dossier->payments()->sum('montant_prevu'), 2, ',', ' '),
                    number_format((float) $dossier->montant_accepte, 2, ',', ' '),
                );
            })
            ->emptyStateHeading('Aucune échéance')
            ->emptyStateDescription('L\'échéancier de versement est généré automatiquement dès que l\'OPCO accepte le dossier et que le montant accepté est renseigné.')
            ->columns([
                TextColumn::make('libelle')
                    ->label('Échéance'),
                TextColumn::make('montant_prevu')
                    ->label('Montant prévu')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('date_prevue')
                    ->label('Échéance le')
                    ->date('d/m/Y')
                    ->color(fn (OpcoPayment $record) => $record->estEnRetard() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('montant_verse')
                    ->label('Montant versé')
                    ->money('EUR')
                    ->placeholder('—'),
                TextColumn::make('date_versement')
                    ->label('Versé le')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('marquerVerse')
                    ->label('Marquer versé')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (OpcoPayment $record) => $record->statut !== PaymentStatut::Verse)
                    ->schema([
                        TextInput::make('montant_verse')
                            ->label('Montant versé (€)')
                            ->numeric()
                            ->prefix('€')
                            ->default(fn (OpcoPayment $record) => $record->montant_prevu)
                            ->required(),
                        DatePicker::make('date_versement')
                            ->label('Date du versement')
                            ->default(now())
                            ->displayFormat('d/m/Y')
                            ->required(),
                    ])
                    ->action(function (OpcoPayment $record, array $data) {
                        $record->marquerVerse((float) $data['montant_verse'], $data['date_versement']);

                        Notification::make()
                            ->title('Versement enregistré')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('ordre');
    }
}
