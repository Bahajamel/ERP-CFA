<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Matching;
use App\Models\Need;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Vue 360° entreprise (P0-03-4) : rassemble en un écran l'identité, le suivi
 * commercial (satisfaction, relance, incidents), les besoins de recrutement,
 * les candidats proposés et les contrats. L'historique (contacts, timeline
 * d'interactions, notes) s'affiche en onglets via les RelationManagers.
 */
class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identité')
                ->columns(3)
                ->schema([
                    TextEntry::make('raison_sociale')
                        ->label('Raison sociale')
                        ->weight('bold'),
                    TextEntry::make('nom_commercial')
                        ->label('Nom commercial')
                        ->placeholder('—'),
                    TextEntry::make('siret')
                        ->label('SIRET')
                        ->placeholder('—'),
                    TextEntry::make('secteur')
                        ->label('Secteur')
                        ->badge()
                        ->color('gray')
                        ->placeholder('—'),
                    TextEntry::make('adresse_complete')
                        ->label('Adresse')
                        ->state(fn (Company $record): string => trim(implode(' ', array_filter([
                            $record->adresse,
                            $record->code_postal,
                            $record->ville,
                        ]))) ?: '—'),
                    TextEntry::make('opco.nom')
                        ->label('OPCO')
                        ->placeholder('—'),
                    TextEntry::make('statut')
                        ->label('Statut')
                        ->badge(),
                ]),

            Section::make('Suivi commercial')
                ->columns(4)
                ->schema([
                    TextEntry::make('satisfaction')
                        ->label('Satisfaction')
                        ->state(fn (Company $record): string => ($n = $record->derniereSatisfaction()) ? "{$n}/5" : '—')
                        ->badge()
                        ->color(fn (Company $record): string => match (true) {
                            ($n = $record->derniereSatisfaction()) === null => 'gray',
                            $n >= 4 => 'success',
                            $n === 3 => 'warning',
                            default => 'danger',
                        }),
                    TextEntry::make('prochaine_relance')
                        ->label('Prochaine relance')
                        ->state(fn (Company $record): string => $record->prochaineRelance()?->prochaine_action_le?->format('d/m/Y') ?? '—')
                        ->badge()
                        ->color(fn (Company $record): string => ($r = $record->prochaineRelance())
                            && $r->prochaine_action_le->isPast() ? 'danger' : 'gray'),
                    TextEntry::make('incidents')
                        ->label('Incidents')
                        ->state(fn (Company $record): int => $record->incidents()->count())
                        ->badge()
                        ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                    TextEntry::make('besoins_ouverts')
                        ->label('Besoins ouverts')
                        ->state(fn (Company $record): int => $record->needs()->ouverts()->count())
                        ->badge()
                        ->color('info'),
                    TextEntry::make('nb_contrats')
                        ->label('Contrats')
                        ->state(fn (Company $record): int => $record->contracts()->count())
                        ->badge()
                        ->color('gray'),
                ]),

            Section::make('Besoins de recrutement')
                ->schema([
                    TextEntry::make('aucun_besoin')
                        ->hiddenLabel()
                        ->state('Aucun besoin enregistré pour cette entreprise.')
                        ->visible(fn (Company $record): bool => $record->needs()->doesntExist()),
                    RepeatableEntry::make('besoins')
                        ->hiddenLabel()
                        ->state(fn (Company $record): array => $record->needs()->with('formation')->latest()->get()
                            ->map(fn (Need $n): array => [
                                'poste' => $n->intitule_poste,
                                'formation' => $n->formation?->libelle ?? '—',
                                'statut' => $n->statut,
                                'restants' => $n->postesRestants().' / '.(int) $n->nb_postes,
                            ])->all())
                        ->visible(fn (Company $record): bool => $record->needs()->exists())
                        ->columns(4)
                        ->schema([
                            TextEntry::make('poste')->label('Poste')->weight('bold'),
                            TextEntry::make('formation')->label('Formation'),
                            TextEntry::make('statut')->label('Statut')->badge(),
                            TextEntry::make('restants')->label('Postes restants'),
                        ]),
                ]),

            Section::make('Candidats proposés')
                ->schema([
                    TextEntry::make('aucun_candidat')
                        ->hiddenLabel()
                        ->state('Aucun candidat proposé.')
                        ->visible(fn (Company $record): bool => $record->matchings()->doesntExist()),
                    RepeatableEntry::make('candidats')
                        ->hiddenLabel()
                        ->state(fn (Company $record): array => $record->matchings()->with(['candidate', 'need'])->latest('matchings.created_at')->get()
                            ->map(fn (Matching $m): array => [
                                'candidat' => $m->candidate?->nom_complet ?? '—',
                                'poste' => $m->need?->intitule_poste ?? '—',
                                'statut' => $m->statut,
                            ])->all())
                        ->visible(fn (Company $record): bool => $record->matchings()->exists())
                        ->columns(3)
                        ->schema([
                            TextEntry::make('candidat')->label('Candidat')->weight('bold'),
                            TextEntry::make('poste')->label('Besoin'),
                            TextEntry::make('statut')->label('Statut')->badge(),
                        ]),
                ]),

            Section::make('Contrats')
                ->schema([
                    TextEntry::make('aucun_contrat')
                        ->hiddenLabel()
                        ->state('Aucun contrat.')
                        ->visible(fn (Company $record): bool => $record->contracts()->doesntExist()),
                    RepeatableEntry::make('contrats')
                        ->hiddenLabel()
                        ->state(fn (Company $record): array => $record->contracts()->with(['candidate', 'opcoFile'])->latest()->get()
                            ->map(fn (Contract $c): array => [
                                'apprenti' => $c->candidate?->nom_complet ?? '—',
                                'statut' => $c->statut_contrat,
                                'periode' => $c->date_debut
                                    ? $c->date_debut->format('d/m/Y').' → '.($c->date_fin?->format('d/m/Y') ?? '…')
                                    : '—',
                                'opco' => $c->opcoFile?->statut?->getLabel() ?? '—',
                            ])->all())
                        ->visible(fn (Company $record): bool => $record->contracts()->exists())
                        ->columns(4)
                        ->schema([
                            TextEntry::make('apprenti')->label('Apprenti')->weight('bold'),
                            TextEntry::make('statut')->label('Statut')->badge(),
                            TextEntry::make('periode')->label('Période'),
                            TextEntry::make('opco')->label('OPCO'),
                        ]),
                ]),
        ]);
    }
}
