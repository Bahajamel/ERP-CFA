<?php

namespace App\Filament\Resources\Evaluations\Schemas;

use App\Enums\EvaluationType;
use App\Filament\Resources\Seances\Schemas\SeanceForm;
use App\Models\Candidate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class EvaluationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('promotion_id')
                    ->label('Classe')
                    ->relationship('promotion', 'libelle', fn ($query) => $query->with('formation'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nom_complet)
                    ->searchable()
                    ->preload()
                    ->live()
                    // Changer de classe réinitialise l'apprenant (il doit en faire partie).
                    ->afterStateUpdated(fn (Set $set) => $set('candidate_id', null)),
                Select::make('candidate_id')
                    ->label('Apprenant')
                    ->options(fn (Get $get): array => filled($get('promotion_id'))
                        ? Candidate::whereHas('promotions', fn ($q) => $q->whereKey($get('promotion_id')))
                            ->orderBy('nom')->orderBy('prenom')->get()
                            ->mapWithKeys(fn (Candidate $c): array => [$c->id => $c->nom_complet])->all()
                        : Candidate::orderBy('nom')->orderBy('prenom')->get()
                            ->mapWithKeys(fn (Candidate $c): array => [$c->id => $c->nom_complet])->all())
                    ->searchable()
                    ->required(),
                TextInput::make('matiere')
                    ->label('Matière')
                    // Synchronisée avec le catalogue de la formation de la classe.
                    ->datalist(fn (Get $get): array => SeanceForm::matieresCatalogue($get('promotion_id')))
                    ->helperText('Proposée depuis le programme de la formation.')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Type')
                    ->options(EvaluationType::class)
                    ->default(EvaluationType::Devoir->value)
                    ->required(),
                TextInput::make('note')
                    ->label('Note')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.25)
                    ->required(),
                TextInput::make('bareme')
                    ->label('Barème (sur)')
                    ->numeric()
                    ->minValue(1)
                    ->default(20)
                    ->required()
                    ->helperText('20 par défaut. La note est ramenée sur 20 pour les moyennes.'),
                TextInput::make('coefficient')
                    ->label('Coefficient')
                    ->numeric()
                    ->minValue(0.5)
                    ->step(0.5)
                    ->default(1)
                    ->required(),
                DatePicker::make('date')
                    ->label('Date')
                    ->default(now())
                    ->displayFormat('d/m/Y'),
                Textarea::make('appreciation')
                    ->label('Appréciation')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}
