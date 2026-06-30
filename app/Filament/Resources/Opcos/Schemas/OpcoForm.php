<?php

namespace App\Filament\Resources\Opcos\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OpcoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')
                    ->label('Nom')
                    ->required(),
            ]);
    }
}
