<?php

namespace App\Filament\Resources\Ruptures\Tables;

use App\Enums\RuptureMotif;
use App\Enums\RuptureStatut;
use App\Filament\Resources\Ruptures\Schemas\RuptureForm;
use App\Jobs\GenererLivrablesJob;
use App\Livret\LivretRsClient;
use App\Models\CfaProfile;
use App\Models\Rupture;
use App\StateMachine\InvalidTransitionException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RupturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('contract')
                    ->label('Apprenant — entreprise')
                    ->state(fn (Rupture $record): string => $record->contract
                        ? RuptureForm::libelleContrat($record->contract)
                        : '—')
                    ->searchable(false)
                    ->wrap(),
                TextColumn::make('date_rupture')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('motif')
                    ->label('Motif')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('initiative')
                    ->label('Initiative')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Ouverte le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(RuptureStatut::class),
                SelectFilter::make('motif')
                    ->label('Motif')
                    ->options(RuptureMotif::class),
            ])
            ->recordActions([
                self::genererLivrables(),
                self::marquerDocumentsGeneres(),
                self::cloturer(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-scissors')
            ->emptyStateHeading('Aucun dossier de rupture')
            ->emptyStateDescription('Une rupture déclarée depuis une admission (ou ouverte ici sur un contrat) '
                .'apparaît « À traiter » : générez les livrables puis clôturez le dossier.');
    }

    /**
     * Livrables de rupture générés depuis cette section (service LivretRS),
     * puis dossier basculé en « Documents générés ».
     */
    private static function genererLivrables(): Action
    {
        return Action::make('genererLivrables')
            ->label('Générer les livrables')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->visible(fn (Rupture $record): bool => $record->statut === RuptureStatut::ATraiter
                && $record->contract?->candidate !== null
                && app(LivretRsClient::class)->estConfigure()
                && (auth()->user()?->can('access_documents') ?? false))
            ->requiresConfirmation()
            ->modalHeading('Générer les livrables de rupture')
            ->modalDescription('Les livrables de l\'apprenti seront générés via LivretRS et importés dans sa '
                .'GED, puis le dossier passera en « Documents générés ».')
            ->action(function (Rupture $record): void {
                GenererLivrablesJob::dispatch($record->contract_id, auth()->id(), [
                    'theme_code' => CfaProfile::current()->theme_defaut ?: 'institutionnel',
                    'format' => CfaProfile::current()->format_defaut ?: 'pdf',
                    'verifier_rncp' => false,
                ]);

                try {
                    $record->transitionTo(RuptureStatut::DocumentsGeneres);
                } catch (InvalidTransitionException) {
                    // Déjà au bon statut : la génération reste lancée.
                }

                Notification::make()->info()
                    ->title('Génération lancée')
                    ->body('Les livrables sont en cours de génération et seront importés dans la GED de l\'apprenti.')
                    ->send();
            });
    }

    /** Chemin manuel : documents joints à la main (onglet Documents du dossier). */
    private static function marquerDocumentsGeneres(): Action
    {
        return Action::make('marquerDocumentsGeneres')
            ->label('Documents générés')
            ->icon('heroicon-o-document-check')
            ->color('info')
            ->visible(fn (Rupture $record): bool => $record->currentState()->canTransitionTo(RuptureStatut::DocumentsGeneres))
            ->requiresConfirmation()
            ->modalDescription('Marquer les documents de rupture comme générés / joints au dossier ?')
            ->action(function (Rupture $record): void {
                try {
                    $record->transitionTo(RuptureStatut::DocumentsGeneres);
                    Notification::make()->success()->title('Documents marqués comme générés')->send();
                } catch (InvalidTransitionException $e) {
                    Notification::make()->danger()->title('Transition impossible')->body($e->getMessage())->send();
                }
            });
    }

    private static function cloturer(): Action
    {
        return Action::make('cloturer')
            ->label('Clôturer')
            ->icon('heroicon-o-lock-closed')
            ->color('gray')
            ->visible(fn (Rupture $record): bool => $record->currentState()->canTransitionTo(RuptureStatut::Cloturee))
            ->requiresConfirmation()
            ->modalDescription('Clôturer ce dossier de rupture ? Cette action marque la fin du traitement administratif.')
            ->action(function (Rupture $record): void {
                try {
                    $record->transitionTo(RuptureStatut::Cloturee);
                    Notification::make()->success()->title('Dossier de rupture clôturé')->send();
                } catch (InvalidTransitionException $e) {
                    Notification::make()->danger()->title('Transition impossible')->body($e->getMessage())->send();
                }
            });
    }
}
