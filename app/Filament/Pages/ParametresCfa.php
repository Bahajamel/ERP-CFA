<?php

namespace App\Filament\Pages;

use App\Models\CfaProfile;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Paramètres du CFA (singleton) — étapes 1 à 3 & préférences de génération de
 * LivretRS : identité, référents, logo, signature, cachet et défauts de
 * génération. Ces données alimentent les livrables (brandés et signés).
 *
 * @property-read Schema $form
 */
class ParametresCfa extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected string $view = 'filament.pages.parametres-cfa';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Paramètres CFA';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $title = 'Paramètres du CFA';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasAnyRole(['Administrateur', 'Direction']);
    }

    public function mount(): void
    {
        // On ne préremplit pas les uploads (logo/signature/cachet) : ils servent
        // à REMPLACER l'existant ; l'état actuel est affiché en dessous.
        $this->form->fill(CfaProfile::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        $profile = CfaProfile::current();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Identité du CFA')
                    ->description('Recherchée automatiquement à l\'étape suivante, ou saisie ici.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nom')->label('Nom du CFA')->required()->columnSpanFull(),
                        TextInput::make('raison_sociale')->label('Raison sociale'),
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

                Section::make('Logo, signature et cachet')
                    ->description('Appliqués aux livrables générés. Laissez vide pour conserver l\'existant.')
                    ->columns(3)
                    ->schema([
                        FileUpload::make('logo')->label('Logo')->image()
                            ->disk('public')->directory('cfa-tmp')
                            ->helperText(self::etatPiece($profile, 'logo')),
                        FileUpload::make('signature')->label('Signature du représentant')->image()
                            ->disk('public')->directory('cfa-tmp')
                            ->helperText(self::etatPiece($profile, 'signature')),
                        FileUpload::make('cachet')->label('Cachet / tampon')->image()
                            ->disk('public')->directory('cfa-tmp')
                            ->helperText(self::etatPiece($profile, 'cachet')),
                    ]),

                Section::make('Préférences de génération')
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

    public function save(): void
    {
        $data = $this->form->getState();
        $profile = CfaProfile::current();

        // Pièces graphiques : remplacent l'existant seulement si un fichier est fourni.
        foreach (['logo', 'signature', 'cachet'] as $collection) {
            $fichier = $data[$collection] ?? null;
            unset($data[$collection]);

            $chemin = is_array($fichier) ? reset($fichier) : $fichier;
            if (! $chemin) {
                continue;
            }

            $profile->clearMediaCollection($collection);
            $profile->addMediaFromDisk($chemin, 'public')->toMediaCollection($collection);
            Storage::disk('public')->delete($chemin);
        }

        $profile->update($data);

        Notification::make()->title('Paramètres du CFA enregistrés')->success()->send();
    }

    /** Libellé d'état d'une pièce (présente/absente) pour l'aide du champ. */
    private static function etatPiece(CfaProfile $profile, string $collection): string
    {
        return $profile->getFirstMedia($collection)
            ? '✓ Une pièce est déjà enregistrée.'
            : 'Aucune pièce enregistrée.';
    }
}
