<?php

namespace App\Filament\Editeur\Resources\Organisations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OrganisationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité du CFA')
                    ->description('Le nom apparaît dans le sélecteur de CFA ; l\'identifiant compose les adresses (/admin/{identifiant}/…).')
                    ->schema([
                        TextInput::make('nom')
                            ->label('Nom du CFA')
                            ->placeholder('ex : CFA Léonard de Vinci')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            // L'identifiant se déduit du nom à la création, puis se fige :
                            // le modifier casserait les liens déjà partagés vers le CFA.
                            ->afterStateUpdated(function (?string $state, callable $set, string $operation): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Identifiant (adresse)')
                            ->prefix('/admin/')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rule('alpha_dash')
                            ->helperText('Lettres, chiffres et tirets. À ne plus modifier une fois le CFA en service.')
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(),
                    ])
                    ->columns(2),

                Section::make('Accès')
                    ->schema([
                        Toggle::make('actif')
                            ->label('CFA actif')
                            ->default(true)
                            ->helperText('Un CFA suspendu disparaît du sélecteur et ses membres ne peuvent plus s\'y connecter. Les données sont conservées.'),
                    ]),
            ]);
    }
}
