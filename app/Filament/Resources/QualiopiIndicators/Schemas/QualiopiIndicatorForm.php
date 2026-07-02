<?php

namespace App\Filament\Resources\QualiopiIndicators\Schemas;

use App\Enums\QualiopiStatut;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class QualiopiIndicatorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Indicateur du RNQ')
                    ->description('Référentiel National Qualité — donnée de référence, non modifiable.')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('numero')
                            ->label('Numéro')
                            ->content(fn ($record) => $record?->numero ? 'Indicateur '.$record->numero : '—'),
                        Placeholder::make('critere')
                            ->label('Critère')
                            ->content(fn ($record) => $record
                                ? 'Critère '.$record->critere.' — '.$record->critere_label
                                : '—'),
                        Placeholder::make('libelle')
                            ->label('Exigence')
                            ->content(fn ($record) => $record?->libelle ?? '—')
                            ->columnSpanFull(),
                        Placeholder::make('specifique_cfa')
                            ->label('Spécificité CFA')
                            ->content(fn ($record) => $record?->specifique_cfa ? 'Oui' : 'Non'),
                    ]),
                Section::make('Conformité de l\'organisme')
                    ->columns(2)
                    ->schema([
                        Select::make('statut')
                            ->label('Statut de conformité')
                            ->options(QualiopiStatut::class)
                            ->required(),
                        Select::make('responsable_id')
                            ->label('Responsable')
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Non assigné'),
                        DatePicker::make('reviewed_at')
                            ->label('Dernière revue')
                            ->displayFormat('d/m/Y'),
                        Textarea::make('commentaire')
                            ->label('Commentaire / plan d\'action')
                            ->placeholder('ex : Preuve à mettre à jour, action prévue le…, responsable identifié')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
