<?php

namespace App\Filament\Resources\Seances\Schemas;

use App\Enums\SeanceStatut;
use App\Models\Candidate;
use App\Models\Promotion;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SeanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('promotion_id')
                    ->label('Classe / Promotion')
                    ->relationship('promotion', 'libelle', fn ($query) => $query->with('formation'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nom_complet)
                    // Pré-remplie quand on arrive du « + » de l'emploi du temps.
                    ->default(fn () => request('promotion') ?: null)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    // Par défaut, toute la cohorte est cochée — on décoche ensuite
                    // les apprenants qui ne suivent pas cette matière (options).
                    ->afterStateUpdated(fn ($state, Set $set) => $set(
                        'participants_ids',
                        $state ? static::apprenantsDe($state)->pluck('id')->all() : [],
                    )),
                TextInput::make('libelle')
                    ->label('Matière')
                    ->placeholder('Ex. Développement web, Anglais professionnel')
                    // Synchronisé avec le catalogue : suggère les matières du
                    // programme de la formation de la classe (saisie libre possible).
                    ->datalist(fn (Get $get): array => static::matieresCatalogue($get('promotion_id')))
                    ->helperText('Proposées depuis le programme de la formation — ou saisissez une autre matière.')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('date')
                    ->label('Date')
                    // Pré-remplie quand on arrive du « + » de l'emploi du temps.
                    ->default(fn () => request('date') ?? now())
                    ->displayFormat('d/m/Y')
                    ->required(),
                TimePicker::make('heure_debut')
                    ->label('Heure de début')
                    ->seconds(false),
                TimePicker::make('heure_fin')
                    ->label('Heure de fin')
                    ->seconds(false),
                Select::make('formateur_id')
                    ->label('Formateur')
                    ->relationship('formateur', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('statut')
                    ->label('Statut')
                    ->options(SeanceStatut::class)
                    ->default(SeanceStatut::Planifiee->value)
                    ->required(),
                CheckboxList::make('participants_ids')
                    ->label('Apprenants concernés par cette matière')
                    ->helperText('Toute la classe est cochée par défaut — décochez ceux qui ne suivent pas cette matière (options différentes). L\'émargement ne portera que sur les apprenants cochés.')
                    ->options(fn (Get $get): array => filled($get('promotion_id'))
                        ? static::apprenantsDe($get('promotion_id'))->mapWithKeys(
                            fn (Candidate $c): array => [$c->id => $c->nom_complet],
                        )->all()
                        : [])
                    ->hint(fn (Get $get): ?string => filled($get('promotion_id')) ? null : 'Choisissez d\'abord la classe.')
                    ->columns(2)
                    ->bulkToggleable()
                    ->columnSpanFull(),
            ]);
    }

    /** Les matières du catalogue de la formation d'une classe (pour les suggestions). */
    public static function matieresCatalogue(mixed $promotionId): array
    {
        if (blank($promotionId)) {
            return [];
        }

        return Promotion::with('formation')->find($promotionId)?->formation?->programme() ?? [];
    }

    /** Les apprenants de la cohorte, par ordre alphabétique. */
    protected static function apprenantsDe(mixed $promotionId): \Illuminate\Support\Collection
    {
        return Candidate::query()
            ->whereHas('promotions', fn ($q) => $q->whereKey($promotionId))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }
}
