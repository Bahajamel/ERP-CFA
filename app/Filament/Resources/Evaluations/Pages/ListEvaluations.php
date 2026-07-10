<?php

namespace App\Filament\Resources\Evaluations\Pages;

use App\Enums\EvaluationType;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Filament\Resources\Seances\Schemas\SeanceForm;
use App\Models\Candidate;
use App\Models\Evaluation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

class ListEvaluations extends ListRecords
{
    protected static string $resource = EvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Saisie rapide : une épreuve, toute une classe d'un coup.
            Action::make('saisirClasse')
                ->label('Saisir les notes d\'une classe')
                ->icon('heroicon-o-table-cells')
                ->schema([
                    Section::make('Épreuve')
                        ->columns(3)
                        ->schema([
                            Select::make('promotion_id')
                                ->label('Classe')
                                ->relationship('promotion', 'libelle', fn ($query) => $query->with('formation'))
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->nom_complet)
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('matiere')
                                ->label('Matière')
                                ->datalist(fn (Get $get): array => SeanceForm::matieresCatalogue($get('promotion_id')))
                                ->required(),
                            Select::make('type')
                                ->label('Type')
                                ->options(EvaluationType::class)
                                ->default(EvaluationType::Devoir->value)
                                ->required(),
                            DatePicker::make('date')
                                ->label('Date')
                                ->default(now())
                                ->displayFormat('d/m/Y'),
                            TextInput::make('bareme')
                                ->label('Barème (sur)')
                                ->numeric()->minValue(1)->default(20)->required(),
                            TextInput::make('coefficient')
                                ->label('Coefficient')
                                ->numeric()->minValue(0.5)->step(0.5)->default(1)->required(),
                        ]),
                    Section::make('Notes des apprenants')
                        ->description('Laissez vide un apprenant non noté (il sera ignoré).')
                        ->schema(fn (Get $get): array => static::champsNotes($get('promotion_id'), (float) ($get('bareme') ?: 20))),
                ])
                ->action(function (array $data): void {
                    $apprenants = static::apprenantsDe($data['promotion_id'] ?? null);
                    $creees = 0;

                    foreach ($apprenants as $c) {
                        $valeur = $data['note_'.$c->id] ?? null;

                        if ($valeur === null || $valeur === '') {
                            continue;
                        }

                        Evaluation::create([
                            'candidate_id' => $c->id,
                            'promotion_id' => $data['promotion_id'],
                            'matiere' => $data['matiere'],
                            'type' => $data['type'],
                            'note' => $valeur,
                            'bareme' => $data['bareme'] ?? 20,
                            'coefficient' => $data['coefficient'] ?? 1,
                            'date' => $data['date'] ?? now(),
                            'author_id' => Auth::id(),
                        ]);
                        $creees++;
                    }

                    Notification::make()
                        ->success()
                        ->title($creees.' note'.($creees > 1 ? 's' : '').' enregistrée'.($creees > 1 ? 's' : ''))
                        ->body($data['matiere'].' — '.($creees > 0 ? 'ajoutées à la classe.' : 'aucune note saisie.'))
                        ->send();
                })
                ->modalSubmitActionLabel('Enregistrer les notes')
                ->modalWidth('3xl'),
            CreateAction::make(),
        ];
    }

    /** Un champ « note » par apprenant de la classe, dans une zone défilante. */
    protected static function champsNotes(mixed $promotionId, float $bareme): array
    {
        $apprenants = static::apprenantsDe($promotionId);

        if ($apprenants->isEmpty()) {
            return [
                TextInput::make('_aucun')->hidden(),
            ];
        }

        $champs = $apprenants
            ->map(fn (Candidate $c) => TextInput::make('note_'.$c->id)
                ->label($c->nom_complet)
                ->numeric()
                ->minValue(0)
                ->maxValue($bareme)
                ->step(0.25)
                ->suffix('/ '.rtrim(rtrim(number_format($bareme, 2, ',', ''), '0'), ',')))
            ->all();

        return [
            Grid::make(['default' => 1, 'md' => 2])
                ->extraAttributes(['style' => 'max-height: 45vh; overflow-y: auto; gap: .75rem; padding: .5rem;'])
                ->schema($champs),
        ];
    }

    /** Apprenants de la classe, par ordre alphabétique. */
    protected static function apprenantsDe(mixed $promotionId): \Illuminate\Support\Collection
    {
        if (blank($promotionId)) {
            return collect();
        }

        return Candidate::whereHas('promotions', fn ($q) => $q->whereKey($promotionId))
            ->orderBy('nom')->orderBy('prenom')
            ->get();
    }
}
