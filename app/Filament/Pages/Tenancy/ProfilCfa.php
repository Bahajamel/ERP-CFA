<?php

namespace App\Filament\Pages\Tenancy;

use App\Livret\LivretRsClient;
use App\Livret\LivretRsException;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Fiche du CFA courant — son identité complète, éditable depuis le panneau CFA
 * (menu du sélecteur).
 *
 * Ces données ne sont pas des « réglages de génération » : ce sont l'identité du
 * CFA. Elles impriment les CERFA, les conventions, les bulletins et les
 * livrables. Elles vivaient dans une page « Paramètres CFA » classée sous la
 * génération de livrables, adossée à un singleton partagé par tous les CFA —
 * d'où un second CFA qui déposait ses CERFA avec le SIRET et la signature du
 * premier. Un CFA = une fiche (migration 2026_07_25_000005).
 *
 * Restent du ressort de l'éditeur (panneau /editeur) : l'identifiant d'URL
 * (slug) et l'état actif/suspendu — un CFA ne peut ni se réactiver lui-même ni
 * casser ses propres adresses.
 */
class ProfilCfa extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Fiche du CFA';
    }

    /**
     * Réservée à la direction et à l'administrateur, comme l'était la page
     * « Paramètres CFA » qu'elle remplace : ces champs impriment le SIRET et le
     * représentant légal sur des documents déposés à l'OPCO et à l'État. Sans
     * cette garde, tout utilisateur rattaché au CFA pourrait les modifier —
     * EditTenantProfile autorise par défaut via la policy `update` du tenant,
     * qui n'existe pas ici.
     */
    public static function canView(Model $tenant): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasAnyRole(['Administrateur', 'Direction']);
    }

    /** Recherche intelligente : pré-remplit depuis les sources officielles. */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('enrichir')
                ->label('Rechercher & enrichir')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->visible(fn (): bool => app(LivretRsClient::class)->estConfigure())
                ->modalHeading('Rechercher le CFA')
                ->modalDescription('Sources officielles gratuites (annuaire des entreprises + liste publique DGEFP). Sélectionnez le bon établissement pour pré-remplir la fiche.')
                ->modalSubmitActionLabel('Pré-remplir')
                ->schema([
                    Select::make('candidat')
                        ->label('Nom du CFA')
                        ->searchable()
                        ->getSearchResultsUsing(function (string $search): array {
                            try {
                                return collect(app(LivretRsClient::class)->rechercherCfa($search))
                                    ->mapWithKeys(fn (array $c) => [
                                        json_encode($c) => trim(($c['nom'] ?? 'CFA').' — '.($c['ville'] ?? '').' (SIREN '.($c['siren'] ?? '—').')'),
                                    ])
                                    ->all();
                            } catch (LivretRsException) {
                                return [];
                            }
                        })
                        ->getOptionLabelUsing(function ($value): string {
                            $c = json_decode((string) $value, true) ?: [];

                            return trim(($c['nom'] ?? 'CFA').' — '.($c['ville'] ?? ''));
                        })
                        ->helperText('Tapez le nom, puis choisissez dans la liste.')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $c = json_decode((string) $data['candidat'], true) ?: [];

                    $maj = array_filter([
                        'nom' => $c['nom'] ?? null,
                        'siren' => $c['siren'] ?? null,
                        'siret' => $c['siret'] ?? null,
                        'naf' => $c['naf'] ?? null,
                        'nda' => $c['nda'] ?? null,
                        'adresse' => $c['adresse'] ?? null,
                        'code_postal' => $c['code_postal'] ?? null,
                        'ville' => $c['ville'] ?? null,
                        'telephone' => $c['telephone'] ?? null,
                        'email' => $c['email'] ?? null,
                    ], fn ($v) => filled($v));

                    $this->form->fill(array_merge($this->form->getRawState(), $maj));

                    Notification::make()
                        ->title('Champs pré-remplis — vérifiez puis enregistrez.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité du CFA')
                    ->description('Figure sur vos CERFA, conventions, bulletins et livrables.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nom')
                            ->label('Nom du CFA')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('Apparaît dans le sélecteur de CFA et, à défaut de raison sociale, sur vos documents.'),
                        TextInput::make('raison_sociale')->label('Raison sociale')
                            ->helperText('Prioritaire sur le nom pour la désignation légale.'),
                        TextInput::make('nda')->label('NDA (déclaration d\'activité)')->placeholder('ex : 11 75 12345 75'),
                        TextInput::make('siren')->label('SIREN')->placeholder('ex : 123456789'),
                        TextInput::make('siret')->label('SIRET')->placeholder('ex : 12345678900012'),
                        TextInput::make('naf')->label('Code APE / NAF')->placeholder('ex : 8559A'),
                        TextInput::make('numero_uai')->label('Numéro UAI')->placeholder('ex : 0751234A'),
                        TextInput::make('adresse')->label('Adresse')->columnSpanFull(),
                        TextInput::make('code_postal')->label('Code postal'),
                        TextInput::make('ville')->label('Ville'),
                        TextInput::make('telephone')->label('Téléphone'),
                        TextInput::make('email')->label('Email')->email(),
                        TextInput::make('website')->label('Site web')->placeholder('https://...')->columnSpanFull(),
                    ]),

                Section::make('Référents')
                    ->description('Obligatoires pour plusieurs livrables (Qualiopi / missions L6231-2).')
                    ->columns(3)
                    ->schema([
                        TextInput::make('representant_nom')->label('Représentant légal — nom'),
                        TextInput::make('representant_prenom')->label('Prénom'),
                        TextInput::make('representant_fonction')->label('Fonction')->placeholder('ex : Directeur'),
                        TextInput::make('referent_pedagogique_nom')->label('Référent pédagogique — nom'),
                        TextInput::make('referent_pedagogique_prenom')->label('Prénom'),
                        Placeholder::make('sep1')->label('')->content(''),
                        TextInput::make('referent_handicap_nom')->label('Référent handicap — nom'),
                        TextInput::make('referent_handicap_prenom')->label('Prénom'),
                        Placeholder::make('sep2')->label('')->content(''),
                        TextInput::make('referent_mobilite_nom')->label('Référent mobilité — nom'),
                        TextInput::make('referent_mobilite_prenom')->label('Prénom'),
                        Placeholder::make('sep3')->label('')->content(''),
                        TextInput::make('dpo_nom')->label('DPO (RGPD) — nom'),
                        TextInput::make('dpo_prenom')->label('Prénom'),
                    ]),

                Section::make('Identité visuelle')
                    ->description('Votre logo, votre couleur et vos éléments de signature — appliqués à votre espace et à vos documents.')
                    ->columns(3)
                    ->schema([
                        ColorPicker::make('couleur_primaire')
                            ->label('Couleur principale')
                            ->helperText('Teinte de votre espace : boutons, liens et accents. Laissez vide pour le thème par défaut.')
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->label('Logo')
                            ->collection('logo')
                            ->image()
                            ->openable()
                            ->downloadable(),
                        SpatieMediaLibraryFileUpload::make('signature')
                            ->label('Signature du représentant')
                            ->collection('signature')
                            ->image()
                            ->openable()
                            ->downloadable(),
                        SpatieMediaLibraryFileUpload::make('cachet')
                            ->label('Cachet / tampon')
                            ->collection('cachet')
                            ->image()
                            ->openable()
                            ->downloadable(),
                    ]),

                Section::make('Préférences de génération')
                    ->description('Valeurs proposées par défaut à la génération des livrables.')
                    ->columns(3)
                    ->schema([
                        Select::make('theme_defaut')->label('Thème par défaut')
                            ->options([
                                'institutionnel' => 'Institutionnel',
                                'premium' => 'Premium graphique',
                                'sobre' => 'Sobre',
                            ])
                            ->default('institutionnel')->required(),
                        Select::make('format_defaut')->label('Format par défaut')
                            ->options([
                                'pdf' => 'PDF uniquement',
                                'pdf_docx' => 'PDF + DOCX (éditable)',
                            ])
                            ->default('pdf')->required(),
                        Toggle::make('verifier_rncp')->label('Vérifier les codes RNCP en ligne')
                            ->helperText('France Compétences'),
                    ]),
            ]);
    }
}
