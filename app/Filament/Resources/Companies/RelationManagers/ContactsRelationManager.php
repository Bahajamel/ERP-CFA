<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Rules\TelephoneInternational;
use App\Support\Indicatifs;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contacts & tuteurs';

    /**
     * Filament passe les RelationManagers en lecture seule sur une page de
     * consultation (ViewRecord) : les actions Créer / Modifier / Supprimer sont
     * alors masquées. On les réactive ici pour pouvoir gérer les contacts
     * directement depuis la fiche entreprise (P0-03-4), sans passer par Modifier.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

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
                Group::make([
                    Select::make('indicatif_pays')
                        ->label('Pays')
                        ->options(Indicatifs::options())
                        ->default(Indicatifs::defaut())
                        ->selectablePlaceholder(false)
                        ->searchable()
                        ->dehydrated(false)
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('telephone', Indicatifs::appliquer($get('telephone'), $state)))
                        ->afterStateHydrated(function (Select $component, Get $get): void {
                            if (filled($get('telephone'))) {
                                $component->state(Indicatifs::detecter($get('telephone')));
                            }
                        })
                        ->columnSpan(2),
                    TextInput::make('telephone')
                        ->label('Téléphone')
                        ->tel()
                        ->placeholder('ex : +33 6 12 34 56 78')
                        ->helperText('Choisissez le pays puis saisissez le numéro.')
                        ->rule(new TelephoneInternational)
                        ->columnSpan(3),
                ])->columns(5),
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
            // Sans ces libellés, Filament fabrique le nom depuis la classe
            // (« company contact ») → l'état vide s'affichait en anglais.
            ->modelLabel('contact')
            ->pluralModelLabel('contacts')
            ->emptyStateHeading('Aucun contact')
            ->emptyStateDescription("Ajoutez un contact ou un tuteur de l'entreprise pour commencer.")
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
