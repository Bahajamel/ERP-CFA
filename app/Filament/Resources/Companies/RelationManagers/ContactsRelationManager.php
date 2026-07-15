<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Rules\TelephoneInternational;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contacts & tuteurs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')
                    ->label('Nom')
                    ->required(),
                TextInput::make('prenom')
                    ->label('Prénom'),
                TextInput::make('email')
                    ->label('Adresse e-mail')
                    ->email(),
                TextInput::make('telephone')
                    ->label('Téléphone')
                    ->tel()
                    ->placeholder('ex : +33 6 12 34 56 78')
                    ->helperText('Format international avec indicatif pays (+33…).')
                    ->rule(new TelephoneInternational),
                TextInput::make('fonction')
                    ->label('Fonction'),
                Toggle::make('is_principal')
                    ->label('Contact principal'),
                Toggle::make('is_tuteur')
                    ->label("Maître d'apprentissage / tuteur"),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nom')
            ->columns([
                TextColumn::make('nom_complet')
                    ->label('Contact')
                    ->getStateUsing(fn ($record) => $record->nom_complet)
                    ->searchable(['nom', 'prenom']),
                TextColumn::make('fonction')
                    ->label('Fonction')
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->placeholder('—'),
                TextColumn::make('telephone')
                    ->label('Téléphone')
                    ->placeholder('—'),
                IconColumn::make('is_principal')
                    ->label('Principal')
                    ->boolean(),
                IconColumn::make('is_tuteur')
                    ->label('Tuteur')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Ajouter un contact'),
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
