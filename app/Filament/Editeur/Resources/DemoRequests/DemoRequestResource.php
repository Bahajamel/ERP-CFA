<?php

namespace App\Filament\Editeur\Resources\DemoRequests;

use App\Enums\DemoRequestStatut;
use App\Filament\Editeur\Resources\DemoRequests\Pages\EditDemoRequest;
use App\Filament\Editeur\Resources\DemoRequests\Pages\ListDemoRequests;
use App\Filament\Editeur\Resources\DemoRequests\Schemas\DemoRequestForm;
use App\Filament\Editeur\Resources\DemoRequests\Tables\DemoRequestsTable;
use App\Models\DemoRequest;
use App\Models\Organisation;
use App\Models\User;
use App\Provisioning\ProvisionnerCfaEssai;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/**
 * Demandes de démonstration reçues depuis le site vitrine public.
 *
 * Réservé au panneau éditeur : ce sont des prospects de la plateforme, pas des
 * données d'un CFA client. Aucune création manuelle — elles arrivent par le
 * formulaire public ; ici on les qualifie et on suit leur avancement.
 */
class DemoRequestResource extends Resource
{
    protected static ?string $model = DemoRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Demandes de démo';

    protected static ?string $modelLabel = 'demande de démonstration';

    protected static ?string $pluralModelLabel = 'demandes de démonstration';

    /** Les demandes sont déposées par le public, jamais saisies ici. */
    public static function canCreate(): bool
    {
        return false;
    }

    /** Pastille de navigation : nombre de demandes encore à traiter. */
    public static function getNavigationBadge(): ?string
    {
        $ouvertes = DemoRequest::query()->aTraiter()->count();

        return $ouvertes > 0 ? (string) $ouvertes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return DemoRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DemoRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDemoRequests::route('/'),
            'edit' => EditDemoRequest::route('/{record}/edit'),
        ];
    }

    /**
     * Action « Convertir en essai gratuit » : ouvre un CFA pour ce prospect
     * (organisation + compte administrateur + échéance d'essai), puis affiche
     * une seule fois les identifiants à transmettre. Réutilisée dans la liste
     * et sur la page de traitement.
     *
     * Masquée si la demande est déjà convertie.
     */
    public static function convertirAction(): Action
    {
        return Action::make('convertirEnEssai')
            ->label('Convertir en essai gratuit')
            ->icon('heroicon-o-rocket-launch')
            ->color('success')
            ->visible(fn (DemoRequest $record): bool => $record->status !== DemoRequestStatut::Converti)
            ->modalHeading('Ouvrir un essai gratuit')
            ->modalDescription('Un CFA et son compte administrateur seront créés. Les identifiants s\'afficheront une seule fois — à transmettre au prospect.')
            ->modalSubmitActionLabel('Créer l\'essai')
            ->fillForm(fn (DemoRequest $record): array => [
                'nom_cfa' => $record->organization_name,
                'email_admin' => $record->email,
                'nom_admin' => $record->nomComplet(),
                'jours_essai' => ProvisionnerCfaEssai::JOURS_ESSAI_DEFAUT,
            ])
            ->schema([
                TextInput::make('nom_cfa')
                    ->label('Nom du CFA')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email_admin')
                    ->label('E-mail de l\'administrateur')
                    ->email()
                    ->required()
                    ->helperText('Compte administrateur du nouvel espace. Si l\'adresse existe déjà, ce compte sera rattaché au CFA.'),
                TextInput::make('nom_admin')
                    ->label('Nom de l\'administrateur')
                    ->maxLength(255),
                TextInput::make('jours_essai')
                    ->label('Durée de l\'essai (jours)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->required(),
            ])
            ->action(function (DemoRequest $record, array $data): void {
                $resultat = app(ProvisionnerCfaEssai::class)->creer(
                    nom: $data['nom_cfa'],
                    emailAdmin: $data['email_admin'],
                    nomAdmin: $data['nom_admin'] ?? null,
                    joursEssai: (int) $data['jours_essai'],
                );

                $record->update(['status' => DemoRequestStatut::Converti]);

                self::notifierIdentifiants($resultat);
            });
    }

    /**
     * Affiche les accès du nouveau CFA une seule fois (notification de session,
     * jamais stockée : le mot de passe temporaire n'est pas conservé).
     *
     * @param  array{organisation: Organisation, admin: User, mot_de_passe: ?string, compte_existant: bool}  $resultat
     */
    private static function notifierIdentifiants(array $resultat): void
    {
        $organisation = $resultat['organisation'];
        $url = url('/admin/'.$organisation->slug);
        $fin = $organisation->date_fin_essai?->format('d/m/Y');

        $lignes = [
            '<strong>CFA créé :</strong> '.e($organisation->nom),
            '<strong>Adresse :</strong> '.e($url),
            '<strong>Identifiant :</strong> '.e($resultat['admin']->email),
        ];

        if ($resultat['compte_existant']) {
            $lignes[] = 'Compte <strong>déjà existant</strong> : mot de passe inchangé.';
        } else {
            $lignes[] = '<strong>Mot de passe temporaire :</strong> '.e($resultat['mot_de_passe']);
        }

        $lignes[] = '<strong>Essai jusqu\'au :</strong> '.e($fin);

        Notification::make()
            ->title('Essai gratuit ouvert')
            ->body(new HtmlString(implode('<br>', $lignes)))
            ->success()
            ->persistent()
            ->send();
    }
}
