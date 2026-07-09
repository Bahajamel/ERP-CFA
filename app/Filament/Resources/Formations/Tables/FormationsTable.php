<?php

namespace App\Filament\Resources\Formations\Tables;

use App\Filament\Resources\Formations\FormationResource;
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
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn (Formation $record): string => FormationResource::getUrl('view', ['record' => $record])),
                TextColumn::make('matieres')
                    ->label('Matières')
                    ->state(fn (Formation $record): int => count($record->programme()))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (int $state): string => $state.' matière'.($state > 1 ? 's' : ''))
                    ->alignCenter(),
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
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('rncp_statut')
                    ->label('RNCP')
                    ->badge()
                    ->state(fn (Formation $record): string => match (true) {
                        $record->rncp_verifie_at === null => 'Non vérifié',
                        $record->rncp_actif === true => 'Valide',
                        $record->rncp_actif === false => 'Inactif',
                        default => 'Inconnu',
                    })
                    ->color(fn (Formation $record): string => match (true) {
                        $record->rncp_verifie_at === null => 'gray',
                        $record->rncp_actif === true => 'success',
                        $record->rncp_actif === false => 'danger',
                        default => 'warning',
                    })
                    ->tooltip(fn (Formation $record): ?string => $record->rncp_verifie_at
                        ? trim(($record->rncp_intitule ?? '').' · Niveau '.($record->rncp_niveau ?? '?'))
                            .' · vérifié le '.$record->rncp_verifie_at->format('d/m/Y')
                        : null),
                IconColumn::make('is_active')
                    ->label('Au catalogue')
                    ->tooltip('Formation proposée par le CFA (indépendant de l\'état RNCP)')
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
                            $record->update([
                                'rncp_actif' => null,
                                'rncp_etat' => 'Introuvable',
                                'rncp_verifie_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Code RNCP introuvable')
                                ->body('Aucune fiche pour '.$record->code_rncp.' sur France Compétences.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $actif = $info['actif'] ?? null;

                        // Persiste le résultat pour l'afficher en continu (colonne RNCP).
                        $record->update([
                            'rncp_actif' => $actif,
                            'rncp_etat' => $info['etat'] ?? null,
                            'rncp_intitule' => $info['intitule'] ?? null,
                            'rncp_niveau' => $info['niveau'] ?? null,
                            'rncp_verifie_at' => now(),
                        ]);

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
