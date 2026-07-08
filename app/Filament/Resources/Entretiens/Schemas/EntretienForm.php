<?php

namespace App\Filament\Resources\Entretiens\Schemas;

use App\Enums\CandidateStatut;
use App\Enums\EntretienMode;
use App\Enums\EntretienStatut;
use App\Models\Candidate;
use App\Models\Entretien;
use App\Parcours\CycleApprenant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class EntretienForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entretien candidat')
                    ->description('Un entretien « Planifié » (date + heures) fait passer le candidat à '
                        .'« Entretien prévu ». Après un entretien « Réalisé », acceptez ou refusez le candidat '
                        .'— l\'acceptation ouvre automatiquement la recherche d\'entreprise au Matching.')
                    ->columns(2)
                    ->schema([
                        Select::make('candidate_id')
                            ->label('Candidat')
                            // Seuls les candidats sans décision finale sont proposables ;
                            // le candidat déjà rattaché reste visible en édition.
                            ->relationship(
                                'candidate',
                                'nom',
                                fn (Builder $query, ?Entretien $record): Builder => $query->where(
                                    fn (Builder $q) => $q
                                        ->whereIn('statut', array_map(
                                            fn (CandidateStatut $s) => $s->value,
                                            CandidateStatut::statutsEntretien(),
                                        ))
                                        ->when($record?->candidate_id, fn (Builder $qq, $id) => $qq->orWhere('id', $id)),
                                ),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Candidate $record): string => $record->nom_complet)
                            ->searchable(['nom', 'prenom'])
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => request()->integer('candidate') ?: null)
                            ->live(),
                        Select::make('responsable_id')
                            ->label('Responsable de l\'entretien')
                            ->relationship('responsable', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn () => Auth::id()),
                        Placeholder::make('formation_visee')
                            ->label('Formation souhaitée')
                            ->content(fn (Get $get): string => Candidate::query()
                                ->find($get('candidate_id'))?->formationVisee?->libelle ?? '—'),
                        Select::make('mode')
                            ->label('Mode d\'entretien')
                            ->options(EntretienMode::class)
                            ->default(EntretienMode::Presentiel->value)
                            ->required()
                            ->live(),
                        TextInput::make('lien_visio')
                            ->label('Lien visio')
                            ->url()
                            ->placeholder('https://…')
                            ->visible(fn (Get $get): bool => $get('mode') === EntretienMode::Visio->value)
                            ->columnSpanFull(),
                        DatePicker::make('date_entretien')
                            ->label('Date de l\'entretien')
                            ->displayFormat('d/m/Y')
                            ->native(false),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(EntretienStatut::class)
                            ->default(EntretienStatut::APlanifier->value)
                            ->required()
                            ->helperText(CycleApprenant::MSG_ENTRETIEN_INCOMPLET),
                        TimePicker::make('heure_debut')
                            ->label('Heure de début')
                            ->seconds(false),
                        TimePicker::make('heure_fin')
                            ->label('Heure de fin')
                            ->seconds(false)
                            ->after('heure_debut'),
                    ]),
                Section::make('Compte-rendu & décision')
                    ->columns(1)
                    ->schema([
                        Textarea::make('compte_rendu')
                            ->label('Compte-rendu de l\'entretien')
                            ->rows(4)
                            ->placeholder('Points abordés, motivation, projet professionnel…'),
                        Textarea::make('note_interne')
                            ->label('Note interne (non communiquée)')
                            ->rows(2),
                        Placeholder::make('resultat_libelle')
                            ->label('Décision')
                            ->content(fn (?Entretien $record): string => match ($record?->resultat) {
                                'accepte' => '✅ Candidat accepté',
                                'refuse' => '❌ Candidat refusé',
                                default => 'Aucune décision — utilisez « Accepter » ou « Refuser » après un entretien réalisé.',
                            })
                            ->visible(fn (?Entretien $record): bool => $record !== null),
                    ]),
            ]);
    }
}
