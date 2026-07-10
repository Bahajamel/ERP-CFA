<?php

namespace App\Filament\Resources\Matchings\Pages;

use App\Enums\CandidateStatut;
use App\Filament\Resources\Matchings\MatchingResource;
use App\Models\Candidate;
use App\Parcours\CycleApprenant;
use App\Parcours\CycleBloqueException;
use App\Support\OpcoDetector;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;

class ListMatchings extends ListRecords
{
    protected static string $resource = MatchingResource::class;

    public function getSubheading(): ?string
    {
        return 'Le rapprochement d\'un candidat accepté par le CFA avec une entreprise — partenaire '
            .'ou trouvée par le candidat lui-même. Un matching « Accepté » permet de créer le contrat.';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->entrepriseTrouveeParCandidat(),
            CreateAction::make(),
        ];
    }

    /**
     * Cas « entreprise externe » du cycle apprenant : le candidat a trouvé
     * lui-même une entreprise non partenaire. Elle est créée comme prospect
     * à qualifier, un besoin minimal est ouvert et le matching est tracé
     * origine « candidat ».
     */
    private function entrepriseTrouveeParCandidat(): Action
    {
        return Action::make('entrepriseExterne')
            ->label('Entreprise trouvée par le candidat')
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->modalHeading('Entreprise trouvée par le candidat')
            ->modalDescription('L\'entreprise sera créée comme prospect à qualifier (avec détection '
                .'automatique de sa fiche par SIRET côté Entreprises), un besoin sera ouvert et le '
                .'matching tracé « trouvé par le candidat ».')
            ->schema([
                Select::make('candidate_id')
                    ->label('Candidat (accepté par le CFA)')
                    ->options(fn (): array => Candidate::query()
                        ->where('statut', CandidateStatut::Accepte->value)
                        ->orderBy('nom')
                        ->get()
                        ->mapWithKeys(fn (Candidate $c): array => [$c->id => $c->nom_complet])
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('raison_sociale')
                    ->label('Raison sociale de l\'entreprise')
                    ->placeholder('ex : Boulangerie Martin SARL')
                    ->required(),
                TextInput::make('siret')
                    ->label('SIRET')
                    ->placeholder('ex : 123 456 789 00012')
                    ->helperText('14 chiffres — si l\'entreprise existe déjà dans le référentiel, elle sera réutilisée.')
                    ->required()
                    ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                        if (! OpcoDetector::siretValide($value)) {
                            $fail('Le SIRET doit comporter exactement 14 chiffres.');
                        }
                    }),
                TextInput::make('intitule_poste')
                    ->label('Poste visé (optionnel)')
                    ->placeholder('ex : Apprenti cuisinier'),
            ])
            ->action(function (array $data): void {
                try {
                    $matching = app(CycleApprenant::class)->entrepriseTrouveeParCandidat(
                        Candidate::query()->findOrFail($data['candidate_id']),
                        [
                            'raison_sociale' => $data['raison_sociale'],
                            'siret' => $data['siret'],
                            'intitule_poste' => $data['intitule_poste'] ?? null,
                        ],
                    );

                    Notification::make()->success()
                        ->title('Matching créé')
                        ->body('Entreprise « '.$matching->need?->company?->raison_sociale.' » enregistrée comme '
                            .'prospect à qualifier, besoin ouvert et matching tracé « trouvé par le candidat ».')
                        ->send();
                } catch (CycleBloqueException $e) {
                    Notification::make()->danger()->title('Création impossible')->body($e->getMessage())->send();
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title('Création impossible')->body(collect($e->errors())->flatten()->first())->send();
                }
            });
    }
}
