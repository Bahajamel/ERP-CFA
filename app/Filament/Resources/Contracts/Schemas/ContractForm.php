<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Enums\ContractStatut;
use App\Models\Candidate;
use App\Models\CompanyContact;
use App\Models\Contract;
use App\Support\RemunerationApprenti;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Parties au contrat')
                    ->columns(2)
                    ->schema([
                        Select::make('candidate_id')
                            ->label('Apprenti (candidat)')
                            ->relationship('candidate', 'nom')
                            ->getOptionLabelFromRecordUsing(fn (Candidate $record): string => $record->nom_complet)
                            ->searchable(['nom', 'prenom'])
                            ->preload()
                            ->required()
                            // L'âge de l'apprenti pilote le barème légal de rémunération.
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::preRemplirSalaire($set, $get)),
                        Select::make('company_id')
                            ->label('Entreprise')
                            ->relationship('company', 'raison_sociale')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            // Changer d'entreprise réinitialise le tuteur : il doit
                            // toujours appartenir à l'entreprise du contrat.
                            ->afterStateUpdated(fn (Set $set) => $set('tuteur_id', null)),
                        Select::make('formation_id')
                            ->label('Formation')
                            ->relationship('formation', 'libelle')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('tuteur_id')
                            ->label('Tuteur')
                            // Seuls les tuteurs (is_tuteur) de l'entreprise sélectionnée.
                            // Le tuteur déjà rattaché au contrat reste toujours proposé
                            // (édition d'un contrat existant) même s'il n'est pas flaggé.
                            ->relationship(
                                'tuteur',
                                'nom',
                                fn (Builder $query, Get $get): Builder => $query
                                    ->where('company_id', $get('company_id'))
                                    ->where(fn (Builder $q): Builder => $q
                                        ->where('is_tuteur', true)
                                        ->orWhere('id', $get('tuteur_id'))),
                            )
                            ->getOptionLabelFromRecordUsing(fn (CompanyContact $record): string => $record->nom_complet)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('company_id')))
                            ->placeholder(fn (Get $get): string => blank($get('company_id'))
                                ? 'Sélectionnez d\'abord une entreprise'
                                : 'Choisir un tuteur')
                            ->helperText('Seuls les tuteurs rattachés à l\'entreprise sélectionnée sont proposés. '
                                .'Si la liste est vide, ajoutez un tuteur dans la fiche entreprise.')
                            // Garde backend : impossible d'enregistrer un tuteur qui
                            // n'appartient pas à l'entreprise du contrat, même en
                            // contournant l'interface. (La contrainte forte est le
                            // rattachement à l'entreprise ; le filtre is_tuteur ne
                            // s'applique qu'au choix dans la liste.)
                            ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                                if (blank($value)) {
                                    return;
                                }

                                $memeEntreprise = CompanyContact::query()
                                    ->whereKey($value)
                                    ->where('company_id', $get('company_id'))
                                    ->exists();

                                if (! $memeEntreprise) {
                                    $fail('Ce tuteur n\'appartient pas à l\'entreprise sélectionnée.');
                                }
                            }),
                    ]),
                Section::make('Détails du contrat')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code_rncp')
                            ->label('Code RNCP')
                            ->placeholder('ex : RNCP34556')
                            ->required(),
                        TextInput::make('rythme')
                            ->label("Rythme d'alternance")
                            ->placeholder('ex : 2 j CFA / 3 j entreprise')
                            ->required(),
                        DatePicker::make('date_debut')
                            ->label('Date de début')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::preRemplirSalaire($set, $get)),
                        DatePicker::make('date_fin')
                            ->label('Date de fin')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->afterOrEqual('date_debut')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, Get $get) => self::preRemplirSalaire($set, $get)),
                        TextInput::make('lieu_formation')
                            ->label('Lieu de formation')
                            ->placeholder('ex : CFA de Lyon, 15 rue Garibaldi')
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Rémunération')
                    ->description('Minimum légal calculé automatiquement depuis l\'âge de l\'apprenti et les dates '
                        .'du contrat (grille apprentissage, % du SMIC). À partir de 21 ans, le minimum conventionnel '
                        .'de branche peut être plus favorable.')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('bareme_legal')
                            ->label('Barème légal applicable')
                            ->content(fn (Get $get): HtmlString => self::baremeLegalHtml($get))
                            ->columnSpanFull(),
                        TextInput::make('salaire_mensuel_brut')
                            ->label('Salaire mensuel brut')
                            ->suffix('€ / mois')
                            ->numeric()
                            ->minValue(0)
                            ->step('0.01')
                            ->live(onBlur: true)
                            ->placeholder('ex : 802.82')
                            ->helperText('Pré-rempli avec le minimum légal dès que l\'apprenti et les dates sont '
                                .'renseignés ; ajustable à la hausse (accord ou convention plus favorable).')
                            // Garde légale : un salaire sous le plancher (grille % du SMIC)
                            // ne peut pas être enregistré — le message explique le calcul.
                            ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                                if (blank($value) || ! is_numeric($value)) {
                                    return;
                                }

                                $min = self::minimumLegal($get);

                                if ($min !== null && (float) $value + 0.005 < $min['montant']) {
                                    $fail(sprintf(
                                        'Salaire sous le minimum légal de l\'apprenti : %s € (%d %% du SMIC — %d ans, année %d du contrat).',
                                        number_format($min['montant'], 2, ',', ' '),
                                        $min['taux'],
                                        $min['age'],
                                        $min['annee'],
                                    ));
                                }
                            }),
                    ]),
                Section::make('Statut du contrat')
                    ->description('Faire évoluer le statut applique les règles métier : garde de signature, '
                        .'ouverture automatique du dossier OPCO à la signature, etc.')
                    ->visibleOn('edit')
                    ->schema([
                        Select::make('statut_contrat')
                            ->label('Statut')
                            ->options(fn (?Contract $record): array => $record ? self::statutOptions($record) : [])
                            ->required(),
                    ]),
                Section::make('CERFA (contrat d\'apprentissage)')
                    ->description('Contrat d\'apprentissage entre le CFA et l\'entreprise (CERFA FA13). Déposez le document (PDF).')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cerfa')
                            ->label('Document CERFA')
                            ->collection('cerfa')
                            ->acceptedFileTypes(['application/pdf'])
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Périodes du barème légal pour l'état courant du formulaire. */
    private static function baremeLegal(Get $get): array
    {
        $candidate = filled($get('candidate_id'))
            ? Candidate::query()->find($get('candidate_id'))
            : null;

        return RemunerationApprenti::periodes(
            $candidate?->date_naissance,
            $get('date_debut'),
            $get('date_fin'),
        );
    }

    /** Plancher légal applicable aujourd'hui (ou en début de contrat). */
    private static function minimumLegal(Get $get): ?array
    {
        $candidate = filled($get('candidate_id'))
            ? Candidate::query()->find($get('candidate_id'))
            : null;

        return RemunerationApprenti::minimum(
            $candidate?->date_naissance,
            $get('date_debut'),
            $get('date_fin'),
        );
    }

    /**
     * Pré-remplit le salaire avec le minimum légal dès que l'apprenti et
     * les dates sont connus — sans jamais écraser une saisie existante.
     */
    private static function preRemplirSalaire(Set $set, Get $get): void
    {
        if (blank($get('salaire_mensuel_brut')) && ($min = self::minimumLegal($get)) !== null) {
            $set('salaire_mensuel_brut', number_format($min['montant'], 2, '.', ''));
        }
    }

    /**
     * Barème légal rendu dans le formulaire : une ligne par période de
     * rémunération (année d'exécution × tranche d'âge), avec le détail du
     * calcul. Contenu entièrement généré (aucune donnée saisie injectée).
     */
    private static function baremeLegalHtml(Get $get): HtmlString
    {
        $periodes = self::baremeLegal($get);

        if ($periodes === []) {
            return new HtmlString(
                '<span style="font-size:.875rem;opacity:.7">Sélectionnez l\'apprenti (avec sa date de naissance) '
                .'et les dates du contrat : le minimum légal se calcule automatiquement.</span>'
            );
        }

        $lignes = collect($periodes)->map(fn (array $p): string => sprintf(
            '<li>Du <b>%s</b> au <b>%s</b> · année %d · %d ans → <b>%d %% du SMIC = %s € / mois</b></li>',
            $p['du']->format('d/m/Y'),
            $p['au']->format('d/m/Y'),
            $p['annee'],
            $p['age'],
            $p['taux'],
            number_format($p['montant'], 2, ',', ' '),
        ))->implode('');

        $premier = $periodes[0];

        return new HtmlString(
            '<ul style="margin:0;padding-left:1.1rem;display:grid;gap:.3rem;font-size:.875rem">'.$lignes.'</ul>'
            .'<p style="margin:.5rem 0 0;font-size:.75rem;opacity:.65">Grille légale (art. D6222-26 du Code du travail) · '
            .'SMIC mensuel brut de référence : '.number_format($premier['smic'], 2, ',', ' ').' € · '
            .'Le montant suit automatiquement les revalorisations du SMIC et les changements de tranche d\'âge.</p>'
        );
    }

    /**
     * Statuts sélectionnables en édition : le statut actuel (affiché) plus les
     * transitions réellement atteignables selon la machine à états.
     *
     * @return array<string, string>
     */
    private static function statutOptions(Contract $record): array
    {
        return collect([$record->statut_contrat, ...$record->allowedTransitions()])
            ->unique()
            ->mapWithKeys(fn (ContractStatut $s): array => [$s->value => $s->getLabel()])
            ->all();
    }
}
