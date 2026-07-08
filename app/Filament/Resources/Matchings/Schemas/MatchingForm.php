<?php

namespace App\Filament\Resources\Matchings\Schemas;

use App\Enums\CandidateStatut;
use App\Enums\MatchingStatut;
use App\Models\Candidate;
use App\Models\Matching;
use App\Models\Need;
use App\Parcours\CycleApprenant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MatchingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proposition candidat ↔ entreprise')
                    ->description('Seuls les candidats acceptés par le CFA entrent au Matching. '
                        .'Un matching « Accepté » permet ensuite de créer le contrat.')
                    ->columns(2)
                    ->schema([
                        Select::make('candidate_id')
                            ->label('Candidat (accepté par le CFA)')
                            // Cycle apprenant : seuls les candidats acceptés sont proposables.
                            // Le candidat déjà rattaché reste visible en édition.
                            ->relationship(
                                'candidate',
                                'nom',
                                fn (Builder $query, ?Matching $record): Builder => $query->where(
                                    fn (Builder $q) => $q
                                        ->where('statut', CandidateStatut::Accepte->value)
                                        ->when($record?->candidate_id, fn (Builder $qq, $id) => $qq->orWhere('id', $id)),
                                ),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Candidate $record): string => $record->nom_complet)
                            ->searchable(['nom', 'prenom'])
                            ->preload()
                            ->required()
                            ->helperText('Un candidat « Entretien prévu » ou « Refusé » ne peut pas entrer au Matching.'),
                        Select::make('need_id')
                            ->label('Besoin entreprise')
                            ->relationship('need', 'intitule_poste')
                            ->getOptionLabelFromRecordUsing(fn (Need $record): string => $record->intitule_poste
                                .($record->company ? ' — '.$record->company->raison_sociale : ''))
                            ->searchable()
                            ->preload()
                            ->helperText('Optionnel tant que la recherche démarre : rattachez l\'entreprise dès '
                                .'qu\'elle est identifiée (obligatoire pour avancer au-delà d\'« En recherche »).'),
                        Select::make('origine')
                            ->label('Origine de l\'entreprise')
                            ->options([
                                CycleApprenant::ORIGINE_CFA => 'Entreprise partenaire (proposée par le CFA)',
                                CycleApprenant::ORIGINE_CANDIDAT => 'Entreprise trouvée par le candidat',
                            ])
                            ->default(CycleApprenant::ORIGINE_CFA)
                            ->required(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options(MatchingStatut::class)
                            ->default(MatchingStatut::EnRecherche->value)
                            ->required()
                            ->live(),
                        Toggle::make('cv_envoye')
                            ->label('CV envoyé'),
                        DatePicker::make('date_entretien')
                            ->label('Date d\'entretien entreprise')
                            ->displayFormat('d/m/Y'),
                        Textarea::make('retour_entreprise')
                            ->label('Retour entreprise')
                            ->placeholder('ex : Entretien positif, en attente de décision')
                            ->columnSpanFull(),
                        Textarea::make('refusal_reason')
                            ->label('Motif de refus')
                            ->placeholder('ex : Profil non retenu, désistement du candidat…')
                            ->helperText('Obligatoire pour passer au statut « Refusé » (motif ou retour entreprise).')
                            ->visible(fn (Get $get): bool => $get('statut') === MatchingStatut::Refuse->value)
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notes internes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
