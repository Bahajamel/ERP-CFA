<?php

namespace App\Filament\Resources\Admissions\RelationManagers;

use App\Enums\ChecklistItemStatut;
use App\Enums\DocumentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Pièces obligatoires';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_type')
                    ->label('Type de pièce')
                    ->options(DocumentType::optionsPour(DocumentType::pourAdmission()))
                    ->required(),
                Select::make('statut')
                    ->label('Statut')
                    ->options(ChecklistItemStatut::class)
                    ->default(ChecklistItemStatut::Manquante->value)
                    ->required(),
                Toggle::make('est_obligatoire')
                    ->label('Obligatoire')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_type')
            ->columns([
                TextColumn::make('document_type')
                    ->label('Pièce')
                    ->badge()
                    ->color('gray'),
                IconColumn::make('est_obligatoire')
                    ->label('Obligatoire')
                    ->boolean(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()->label('Ajouter une pièce'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
