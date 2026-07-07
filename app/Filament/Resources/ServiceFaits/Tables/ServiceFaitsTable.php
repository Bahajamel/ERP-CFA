<?php

namespace App\Filament\Resources\ServiceFaits\Tables;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\ServiceFait;
use App\Scolarite\ServiceFaitPreuve;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ServiceFaitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('promotion.libelle')
                    ->label('Classe')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('periode')
                    ->label('Période')
                    ->state(fn (ServiceFait $record): string => $record->periodeLibelle())
                    ->sortable(['annee', 'mois']),
                TextColumn::make('nb_seances')
                    ->label('Séances')
                    ->alignCenter(),
                TextColumn::make('nb_heures')
                    ->label('Heures')
                    ->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 1, ',', ' '), '0'), ',').' h')
                    ->alignCenter(),
                TextColumn::make('taux_presence')
                    ->label('Assiduité')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : "{$state} %")
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 90 => 'success',
                        $state >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->alignCenter(),
                TextColumn::make('validatedBy.name')
                    ->label('Validé par')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('validated_at')
                    ->label('Validé le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('promotion_id')
                    ->label('Classe')
                    ->relationship('promotion', 'libelle', fn ($query) => $query->with('formation'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nom_complet)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('genererPreuve')
                    ->label('Générer la preuve')
                    ->icon('heroicon-o-document-text')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription('Générer l\'attestation PDF de service fait pour cette période ?')
                    ->action(function (ServiceFait $record): void {
                        $document = app(ServiceFaitPreuve::class)->generer($record, Auth::id());

                        Notification::make()
                            ->success()
                            ->title('Preuve générée')
                            ->body("Attestation v{$document->version} ajoutée aux documents.")
                            ->send();
                    }),
                Action::make('telechargerPreuve')
                    ->label('Télécharger la preuve')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (ServiceFait $record): bool => self::dernierePreuve($record) !== null)
                    ->url(fn (ServiceFait $record): ?string => self::dernierePreuve($record)?->getFirstMediaUrl('fichier'))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('validated_at', 'desc');
    }

    /** Dernière preuve de service fait archivée pour cette période. */
    private static function dernierePreuve(ServiceFait $serviceFait): ?Document
    {
        return $serviceFait->documents()
            ->where('type', DocumentType::PreuveServiceFait->value)
            ->orderByDesc('version')
            ->first();
    }
}
