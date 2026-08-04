<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Filament\Actions\EspaceEntrepriseAction;
use App\Mail\AccesEspaceEntreprise;
use App\Models\Company;
use App\Models\Formation;
use App\Models\Organisation;
use App\Portail\PortailEntrepriseService;
use App\Support\CustomFields;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class CompaniesTable
{
    /** Colonnes natives renommables par CFA (clé de colonne => libellé d'origine). */
    public const COLONNES_PERSONNALISABLES = [
        'identite' => 'Entreprise',
        'contact' => 'Contact principal',
        'secteur' => 'Secteur',
        'opco.nom' => 'OPCO',
        'besoins_ouverts' => 'Besoins ouverts',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query, $livewire): void {
                $query->with(['opco', 'contacts', 'needs', 'interactions']);
                self::appliquerScopeRapide($query, self::scopeDe($livewire));
            })
            ->columns(CustomFields::appliquerReglages([
                ViewColumn::make('identite')
                    ->label('Entreprise')
                    ->view('filament.companies.col-identite')
                    ->searchable(['raison_sociale', 'nom_commercial'])
                    ->sortable(['raison_sociale']),
                TextColumn::make('contact')
                    ->label('Contact principal')
                    ->state(fn (Company $record): ?string => $record->contactPrincipal->first()?->nom
                        ?? $record->contacts->first()?->nom)
                    ->description(fn (Company $record): ?string => $record->contactPrincipal->first()?->telephone
                        ?? $record->contacts->first()?->telephone)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('secteur')
                    ->label('Secteur')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('opco.nom')
                    ->label('OPCO')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('besoins_ouverts')
                    ->label('Besoins ouverts')
                    ->badge()
                    ->color(fn (Company $record): string => $record->besoinsOuvertsCount() > 0 ? 'warning' : 'gray')
                    ->state(fn (Company $record): string => $record->besoinsOuvertsCount().' poste'
                        .($record->besoinsOuvertsCount() > 1 ? 's' : '')),
                // Colonnes personnalisées du CFA (masquables), s'il en a défini.
                ...CustomFields::tableColumns('company'),
            ], 'company'))
            // Clic sur une ligne = ouvre le panneau « Focus entreprise » (et non la
            // fiche : elle reste accessible via « Aperçu » ou le menu « Plus »).
            ->recordAction('focus')
            ->recordUrl(null)
            ->filters([
                SelectFilter::make('secteur')
                    ->label('Secteur')
                    ->options(fn (): array => Company::query()
                        ->whereNotNull('secteur')
                        ->distinct()
                        ->orderBy('secteur')
                        ->pluck('secteur', 'secteur')
                        ->all()),
                SelectFilter::make('opco_id')
                    ->label('OPCO')
                    ->relationship('opco', 'nom'),
                SelectFilter::make('formation_recherchee')
                    ->label('Formation recherchée')
                    ->options(fn (): array => Formation::query()->orderBy('libelle')->pluck('libelle', 'id')->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $q, $formationId): Builder => $q->whereHas(
                            'needs',
                            fn (Builder $n): Builder => $n->ouverts()->where('formation_id', $formationId),
                        ),
                    )),
                TrashedFilter::make(),
            ])
            // Listes déroulantes toujours visibles en barre au-dessus du tableau
            // (au lieu du menu déroulant « Filtres »), comme le workspace attendu.
            // Filtres instantanés (sans bouton « Appliquer ») pour une barre compacte.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns([
                'sm' => 2,
                'lg' => 4,
            ])
            ->recordActions([
                Action::make('focus')
                    ->label('Aperçu')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(fn (Company $record, $livewire) => $livewire->focusId = $record->getKey()),
                ActionGroup::make([
                    // Lien personnel de l'entreprise vers son espace (portail sans
                    // mot de passe) : QR + lien + envoi par e-mail au contact.
                    EspaceEntrepriseAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('access_companies') ?? false),
                    ViewAction::make(),
                    EditAction::make(),
                ])
                    ->label('Plus')
                    ->icon('heroicon-o-ellipsis-horizontal')
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Envoi groupé du lien d'accès à l'espace entreprise : un e-mail
                    // par entreprise sélectionnée ayant un contact avec adresse.
                    BulkAction::make('envoyerEspaceEntreprise')
                        ->label('Envoyer l\'accès à l\'espace')
                        ->icon('heroicon-o-building-office-2')
                        ->color('info')
                        ->visible(fn (): bool => auth()->user()?->can('access_companies') ?? false)
                        ->requiresConfirmation()
                        ->modalHeading('Envoyer l\'accès à l\'espace entreprise')
                        ->modalDescription('Chaque entreprise sélectionnée disposant d\'un contact avec e-mail recevra '
                            .'son lien personnel (alternants, assiduité, documents, factures). Les autres sont ignorées.')
                        ->modalSubmitActionLabel('Envoyer les e-mails')
                        ->action(function (Collection $records): void {
                            $service = app(PortailEntrepriseService::class);
                            $envoyes = 0;
                            $ignores = 0;

                            foreach ($records as $company) {
                                $email = $service->emailDestinataire($company);

                                if (blank($email)) {
                                    $ignores++;

                                    continue;
                                }

                                $cfa = $company->organisation ?? Organisation::defaut();

                                Mail::to($email)->send(new AccesEspaceEntreprise(
                                    company: $company,
                                    lien: $service->lienPour($company),
                                    nomCfa: $cfa?->designation() ?? 'CFA',
                                ));

                                $envoyes++;
                            }

                            Notification::make()->success()
                                ->title($envoyes.' e-mail(s) d\'accès envoyé(s)')
                                ->body($ignores > 0
                                    ? $ignores.' entreprise(s) ignorée(s) (aucun contact avec e-mail).'
                                    : 'Toutes les entreprises sélectionnées ont reçu leur lien.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('raison_sociale')
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('Aucune entreprise enregistrée')
            ->emptyStateDescription('Ajoutez une entreprise partenaire ou lancez une prospection La Bonne Alternance depuis les besoins pour alimenter votre CRM.');
    }

    /** Scope rapide courant lu sur la page (null hors ListCompanies). */
    private static function scopeDe($livewire): ?string
    {
        return (is_object($livewire) && property_exists($livewire, 'quickScope'))
            ? $livewire->quickScope
            : null;
    }

    /**
     * Filtre rapide « orienté action » sur les entreprises. Réutilisé par les
     * compteurs des blocs (ListCompanies) pour rester cohérent.
     *
     *  - a_relancer : aucune interaction depuis plus de 7 jours (sans activité récente) ;
     *  - besoins_ouverts : au moins un besoin ouvert (à pourvoir) ;
     *  - sans_besoin : partenaire sans aucun besoin ouvert (à solliciter).
     */
    public static function appliquerScopeRapide(Builder $query, ?string $scope): void
    {
        match ($scope) {
            'a_relancer' => $query->whereDoesntHave(
                'interactions',
                fn (Builder $q) => $q->where('date_interaction', '>=', now()->subDays(7)),
            ),
            'besoins_ouverts' => $query->whereHas('needs', fn (Builder $q) => $q->ouverts()),
            'sans_besoin' => $query->whereDoesntHave('needs', fn (Builder $q) => $q->ouverts()),
            default => null,
        };
    }
}
