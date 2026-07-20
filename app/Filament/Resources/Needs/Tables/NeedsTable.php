<?php

namespace App\Filament\Resources\Needs\Tables;

use App\Enums\InteractionType;
use App\Enums\NeedStatut;
use App\Mail\EmailPersonnalise;
use App\Models\EmailTemplate;
use App\Models\Need;
use App\StateMachine\InvalidTransitionException;
use App\Support\CustomFields;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class NeedsTable
{
    /** Colonnes natives renommables par CFA (clé de colonne => libellé d'origine). */
    public const COLONNES_PERSONNALISABLES = [
        'identite' => 'Offre',
        'formation.libelle' => 'Formation',
        'localisation' => 'Lieu',
        'matchings_count' => 'Candidats proposés',
        'date_demarrage' => 'Démarrage',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire): void {
                $query->with(['company', 'formation'])->withCount('matchings');
                self::appliquerScopeRapide($query, self::scopeDe($livewire));
            })
            ->columns(CustomFields::appliquerReglages([
                ViewColumn::make('identite')
                    ->label('Offre')
                    ->view('filament.needs.col-identite')
                    ->searchable(['intitule_poste'])
                    ->sortable(['intitule_poste']),
                TextColumn::make('formation.libelle')
                    ->label('Formation')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('localisation')
                    ->label('Lieu')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('matchings_count')
                    ->label('Candidats proposés')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'gray' : 'info')
                    ->formatStateUsing(fn (int $state): string => $state.' candidat'.($state > 1 ? 's' : ''))
                    ->alignCenter()
                    ->sortable()
                    ->tooltip('Nombre de candidats proposés sur cette offre (plusieurs candidats possibles par offre)'),
                ViewColumn::make('recrutement')
                    ->label('Recrutement')
                    ->view('filament.needs.recrutement'),
                TextColumn::make('date_demarrage')
                    ->label('Démarrage')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('date_cloture')
                    ->label('Clôturé le')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Colonnes personnalisées du CFA (masquables), s'il en a défini.
                ...CustomFields::tableColumns('need'),
            ], 'need'))
            // Clic sur une ligne = ouvre le panneau « Focus offre » (la page de
            // modification reste accessible via « Aperçu » → « Ouvrir/modifier »
            // ou le menu d'actions).
            ->recordAction('focus')
            ->recordUrl(null)
            ->filters([
                Filter::make('ouverts')
                    ->label('Besoins ouverts uniquement')
                    ->query(fn (Builder $query): Builder => $query->ouverts()),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(NeedStatut::class),
                SelectFilter::make('company_id')
                    ->label('Entreprise')
                    ->relationship('company', 'raison_sociale')
                    ->searchable()
                    ->preload(),
            ])
            // Listes déroulantes toujours visibles en barre au-dessus du tableau.
            // Filtres instantanés (sans bouton « Appliquer ») pour une barre compacte.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 3,
            ])
            ->recordActions([
                // Sélectionne l'offre dans le panneau « Focus offre » (clic sur la
                // ligne = même action, sans navigation).
                Action::make('focus')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Need $record, $livewire) => $livewire->focusId = $record->getKey()),
                // Le statut suit désormais l'activité des candidats (cf.
                // Need::synchroniserDepuisMatchings) : plus de sélecteur de statut.
                // Reste le seul cas qu'aucune automatisation ne peut deviner —
                // l'entreprise retire son offre.
                Action::make('annuler')
                    ->label('Annuler l\'offre')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Need $record): bool => in_array(
                        NeedStatut::Annule,
                        $record->currentState()->transitions(),
                        true,
                    ))
                    ->requiresConfirmation()
                    ->modalHeading(fn (Need $record): string => "Annuler l'offre « {$record->intitule_poste} » ?")
                    ->modalDescription('L\'offre sortira des offres ouvertes et les candidats encore en lice seront à repositionner. Cette action ne se défait pas.')
                    ->schema([
                        Textarea::make('comment')
                            ->label('Motif de l\'annulation')
                            ->placeholder('ex : l\'entreprise a gelé son recrutement')
                            ->required(),
                    ])
                    ->action(function (Need $record, array $data): void {
                        try {
                            $record->transitionTo(NeedStatut::Annule, $data['comment']);
                            Notification::make()->success()->title('Offre annulée')->send();
                        } catch (InvalidTransitionException $e) {
                            Notification::make()->danger()->title('Annulation refusée')->body($e->getMessage())->send();
                        }
                    }),
                // Formulaire « Proposer des candidats » : modale riche (composant Livewire dédié)
                // — sélection multiple, score, canal, relance, message, tâche de relance.
                Action::make('proposerCandidats')
                    ->label('Proposer des candidats')
                    ->icon('heroicon-o-sparkles')
                    ->color('success')
                    ->modalHeading('Proposer des candidats')
                    ->modalDescription('Sélectionnez les profils à proposer à l\'entreprise et préparez le suivi commercial.')
                    ->modalWidth('7xl')
                    ->modalContent(fn (Need $record): View => view('filament.matching.proposer-host', ['need' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer'),
                // Envoyer un e-mail à l'entreprise qui a publié l'offre, à partir d'un
                // « mail type » (variables pré-remplies) ; l'envoi est journalisé.
                self::envoyerEmailAction(),
                // Pas de ViewAction : « Aperçu » ci-dessus remplit déjà ce rôle
                // (panneau Focus offre), sans quitter la liste.
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-briefcase')
            ->emptyStateHeading('Aucune offre proposée')
            ->emptyStateDescription('Enregistrez la première offre d\'une entreprise (poste à pourvoir) : le matching pourra ensuite proposer des candidats compatibles.');
    }

    /**
     * « Envoyer un e-mail » : écrit à l'entreprise qui a publié l'offre à partir
     * d'un « mail type ». Le modèle choisi pré-remplit l'objet et le message
     * (variables résolues depuis l'offre) ; le commercial ajuste puis envoie.
     * L'envoi est réel et journalisé comme interaction sur l'entreprise.
     */
    private static function envoyerEmailAction(): Action
    {
        return Action::make('envoyerEmail')
            ->label('Envoyer un e-mail')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->visible(fn (): bool => Auth::user()?->can('access_companies') ?? false)
            ->modalHeading(fn (Need $record): string => 'E-mail à '.($record->company?->raison_sociale ?? 'l\'entreprise'))
            ->modalDescription('Choisissez un modèle : l\'objet et le message se pré-remplissent avec les infos de l\'offre. Ajustez, puis envoyez.')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Envoyer')
            ->fillForm(fn (Need $record): array => ['destinataire' => self::emailContact($record)])
            ->schema([
                Select::make('template_id')
                    ->label('Modèle d\'e-mail')
                    ->options(fn (): array => EmailTemplate::query()->actif()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->native(false)
                    ->live()
                    ->helperText('Sélectionnez un modèle pour pré-remplir l\'objet et le message.')
                    ->afterStateUpdated(function ($state, Set $set, Need $record): void {
                        $modele = EmailTemplate::query()->whereKey($state)->first();

                        if ($modele === null) {
                            return;
                        }

                        $vars = self::variablesOffre($record);
                        $set('objet', EmailTemplate::remplacer($modele->subject, $vars));
                        $set('corps', EmailTemplate::remplacer($modele->body, $vars));
                    }),
                TextInput::make('destinataire')
                    ->label('Destinataire')
                    ->email()
                    ->required()
                    ->helperText('Contact de l\'entreprise (modifiable).'),
                TextInput::make('objet')
                    ->label('Objet')
                    ->required()
                    ->maxLength(255),
                Textarea::make('corps')
                    ->label('Message')
                    ->rows(10)
                    ->required(),
            ])
            ->action(function (Need $record, array $data): void {
                Mail::to($data['destinataire'])->send(new EmailPersonnalise(
                    $data['objet'],
                    $data['corps'],
                    Auth::user()?->name,
                ));

                // Journalise l'échange dans l'historique de l'entreprise.
                $record->company?->interactions()->create([
                    'type' => InteractionType::Email->value,
                    'date_interaction' => now(),
                    'resume' => 'E-mail : '.$data['objet'],
                    'user_id' => Auth::id(),
                ]);

                Notification::make()->success()
                    ->title('E-mail envoyé')
                    ->body('Le message a été envoyé et ajouté à l\'historique de l\'entreprise.')
                    ->send();
            });
    }

    /** E-mail de contact par défaut de l'offre (contact du besoin, sinon contact principal). */
    private static function emailContact(Need $record): ?string
    {
        return $record->contact?->email
            ?? $record->company?->contactPrincipal->first()?->email
            ?? $record->company?->contacts->first()?->email;
    }

    /**
     * Valeurs des variables d'un « mail type » pour cette offre.
     *
     * @return array<string, string>
     */
    private static function variablesOffre(Need $record): array
    {
        $contact = $record->contact
            ?? $record->company?->contactPrincipal->first()
            ?? $record->company?->contacts->first();

        return [
            'entreprise' => (string) ($record->company?->raison_sociale ?? ''),
            'contact' => (string) ($contact?->nom_complet ?? ''),
            'offre' => (string) ($record->intitule_poste ?? ''),
            'formation' => (string) ($record->formation?->libelle ?? ''),
            'lieu' => (string) ($record->localisation ?? ''),
            'date_demarrage' => $record->date_demarrage?->format('d/m/Y') ?? '',
            'commercial' => (string) (Auth::user()?->name ?? ''),
        ];
    }

    /** Scope rapide courant lu sur la page (null hors ListNeeds). */
    private static function scopeDe($livewire): ?string
    {
        return (is_object($livewire) && property_exists($livewire, 'quickScope'))
            ? $livewire->quickScope
            : null;
    }

    /**
     * Applique un filtre rapide « orienté action » à la requête du tableau.
     * Réutilisé par les compteurs des blocs (ListNeeds) pour rester cohérent.
     *
     *  - a_pourvoir : offres ouvertes (postes encore en recrutement) ;
     *  - sans_candidat : offres ouvertes sans aucun candidat proposé ;
     *  - en_matching : offres ouvertes ayant au moins un candidat proposé.
     */
    public static function appliquerScopeRapide(Builder $query, ?string $scope): void
    {
        match ($scope) {
            'a_pourvoir' => $query->ouverts(),
            'sans_candidat' => $query->ouverts()->whereDoesntHave('matchings'),
            'en_matching' => $query->ouverts()->whereHas('matchings'),
            // Les trois filtres ci-dessus ne montrent que des offres ouvertes :
            // sans celui-ci, rien ne permettait de retrouver les offres terminées.
            // Regroupe les vraies fins (pourvue, annulée) plutôt que le seul
            // statut « Archivé », qui ne survient jamais (cf. NeedStatut::Archive).
            'cloturees' => $query->whereIn(
                'statut',
                array_map(fn (NeedStatut $s): string => $s->value, Need::STATUTS_CLOS),
            ),
            default => null,
        };
    }
}
