<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Enums\ContractStatut;
use App\Models\Candidate;
use App\Models\CompanyContact;
use App\Models\Contract;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                            ->required(),
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
                            ->required(),
                        DatePicker::make('date_fin')
                            ->label('Date de fin')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->afterOrEqual('date_debut'),
                        TextInput::make('lieu_formation')
                            ->label('Lieu de formation')
                            ->placeholder('ex : CFA de Lyon, 15 rue Garibaldi')
                            ->required()
                            ->columnSpanFull(),
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
