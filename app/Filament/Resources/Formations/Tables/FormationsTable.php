<?php

namespace App\Filament\Resources\Formations\Tables;

use App\Livret\LivretRsClient;
use App\Livret\LivretRsException;
use App\Models\Formation;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('libelle')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code_rncp')
                    ->label('Code RNCP')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('niveau')
                    ->label('Niveau')
                    ->placeholder('—'),
                TextColumn::make('duree_mois')
                    ->label('Durée (mois)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rythme_defaut')
                    ->label('Rythme')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('verifierRncp')
                    ->label('Vérifier RNCP')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('gray')
                    ->visible(fn (Formation $record) => filled($record->code_rncp)
                        && app(LivretRsClient::class)->estConfigure())
                    ->action(function (Formation $record) {
                        try {
                            $info = app(LivretRsClient::class)->verifierRncp((string) $record->code_rncp);
                        } catch (LivretRsException $e) {
                            Notification::make()->title('Vérification impossible')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        if (! ($info['found'] ?? false)) {
                            Notification::make()
                                ->title('Code RNCP introuvable')
                                ->body('Aucune fiche pour '.$record->code_rncp.' sur France Compétences.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $actif = $info['actif'] ?? null;
                        $statut = $actif === true ? 'Active' : ($actif === false ? 'Inactive' : 'État inconnu');

                        Notification::make()
                            ->title($record->code_rncp.' — '.$statut)
                            ->body(trim(($info['intitule'] ?? '').' · Niveau '.($info['niveau'] ?? '?')))
                            ->color($actif === true ? 'success' : ($actif === false ? 'danger' : 'warning'))
                            ->persistent()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('libelle');
    }
}
