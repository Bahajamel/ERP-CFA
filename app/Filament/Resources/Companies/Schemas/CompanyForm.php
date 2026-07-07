<?php

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\CompanyStatut;
use App\Support\AdresseBan;
use App\Support\EntrepriseAnnuaire;
use App\Support\OpcoDetector;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CompanyForm
{
    /**
     * Détecte l'OPCO depuis un SIRET (France Compétences), pré-remplit le
     * champ et notifie l'utilisateur. Partagé entre la recherche d'entreprise
     * et la saisie directe du SIRET ; n'écrase jamais un choix manuel en cas
     * d'échec et ne bloque jamais la création.
     */
    private static function detecterEtNotifierOpco(?string $siret, Set $set, Get $get): void
    {
        if (! OpcoDetector::siretValide($siret)) {
            return;
        }

        $resultat = app(OpcoDetector::class)->detecter($siret);

        if ($resultat['statut'] === 'ok') {
            $set('opco_id', $resultat['opco']->id);
            Notification::make()
                ->success()
                ->title('OPCO détecté automatiquement')
                ->body("« {$resultat['nom']} » a été identifié à partir du SIRET (France Compétences).")
                ->send();

            return;
        }

        if ($resultat['statut'] === 'indisponible') {
            Notification::make()
                ->warning()
                ->title('Détection OPCO indisponible')
                ->body('La détection automatique de l\'OPCO est temporairement indisponible. Vous pouvez le sélectionner manuellement.')
                ->send();

            return;
        }

        if (blank($get('opco_id'))) {
            Notification::make()
                ->info()
                ->title('Aucun OPCO détecté')
                ->body('Aucun OPCO trouvé pour ce SIRET. Vous pouvez le sélectionner manuellement.')
                ->send();
        }
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            // Deux colonnes équilibrées (pas de trous entre cartes).
            ->columns(2)
            ->components([
                Group::make([
                    Section::make('Entreprise')
                        ->icon('heroicon-o-building-office-2')
                        ->columns(2)
                        ->schema([
                            Select::make('entreprise_recherche')
                                ->label('Rechercher une entreprise')
                                ->placeholder('Tapez la raison sociale ou le SIRET…')
                                ->searchable()
                                ->live()
                                ->dehydrated(false)
                                ->getSearchResultsUsing(fn (string $search): array => app(EntrepriseAnnuaire::class)->options($search))
                                ->getOptionLabelUsing(fn ($value): ?string => EntrepriseAnnuaire::decode($value)['label'] ?? null)
                                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                    $fiche = EntrepriseAnnuaire::decode($state);

                                    if ($fiche === null) {
                                        return;
                                    }

                                    $set('raison_sociale', $fiche['raison_sociale']);
                                    $set('siret', $fiche['siret']);
                                    $set('secteur', $fiche['secteur'] ?? null);
                                    $set('adresse', $fiche['adresse']);
                                    $set('code_postal', $fiche['code_postal']);
                                    $set('ville', $fiche['ville']);
                                    $set('pays', $fiche['pays'] ?? 'France');
                                    $set('latitude', $fiche['latitude']);
                                    $set('longitude', $fiche['longitude']);

                                    // Enchaîne la détection de l'OPCO depuis le SIRET.
                                    self::detecterEtNotifierOpco($fiche['siret'], $set, $get);
                                })
                                ->helperText('Annuaire officiel des Entreprises (État) : SIRET, adresse et OPCO remplis automatiquement. La saisie manuelle reste possible.')
                                ->columnSpanFull(),
                            TextInput::make('raison_sociale')
                                ->label('Raison sociale')
                                ->placeholder('ex : Boulangerie Martin SARL')
                                ->required(),
                            TextInput::make('nom_commercial')
                                ->label('Nom commercial')
                                ->placeholder('ex : Chez Martin'),
                            TextInput::make('siret')
                                ->label('SIRET')
                                ->placeholder('ex : 123 456 789 00012')
                                ->helperText('14 chiffres — l\'OPCO est détecté automatiquement (France Compétences).')
                                ->required()
                                ->live(onBlur: true)
                                // Normalisation : le SIRET est stocké sans espaces.
                                ->dehydrateStateUsing(fn (?string $state): string => OpcoDetector::normaliserSiret($state))
                                ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                    if (! OpcoDetector::siretValide($value)) {
                                        $fail('Le SIRET doit comporter exactement 14 chiffres.');
                                    }
                                })
                                ->unique(ignoreRecord: true)
                                // Détection automatique de l'OPCO dès qu'un SIRET valide est saisi.
                                ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => self::detecterEtNotifierOpco($state, $set, $get)),
                            TextInput::make('secteur')
                                ->label("Secteur d'activité")
                                ->placeholder('ex : Restauration, BTP, Informatique'),
                            Select::make('opco_id')
                                ->label('OPCO')
                                ->relationship('opco', 'nom')
                                ->searchable()
                                ->preload(),
                            // Le statut n'est pas demandé à la création : une nouvelle
                            // entreprise démarre en « Prospect » (défaut SQL). Le statut
                            // (dont Active / Inactive) se gère ensuite en édition.
                            Select::make('statut')
                                ->label('Statut')
                                ->options(CompanyStatut::class)
                                ->default(CompanyStatut::Prospect->value)
                                ->required()
                                ->visibleOn('edit'),
                        ]),
                ])->columnSpan(1),
                Group::make([
                    Section::make('Adresse')
                        ->icon('heroicon-o-map-pin')
                        ->description('Recherchez une adresse pour remplir automatiquement les champs et géolocaliser l\'entreprise, ou saisissez-la à la main.')
                        ->columns(2)
                        ->schema([
                            Select::make('adresse_recherche')
                                ->label('Rechercher une adresse')
                                ->placeholder('Tapez une adresse…')
                                ->searchable()
                                ->live()
                                ->dehydrated(false)
                                ->getSearchResultsUsing(fn (string $search): array => app(AdresseBan::class)->options($search))
                                ->getOptionLabelUsing(fn ($value): ?string => AdresseBan::decode($value)['label'] ?? null)
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    $data = AdresseBan::decode($state);

                                    if ($data === null) {
                                        return;
                                    }

                                    $set('adresse', $data['adresse']);
                                    $set('code_postal', $data['code_postal']);
                                    $set('ville', $data['ville']);
                                    $set('pays', $data['pays'] ?? 'France');
                                    $set('latitude', $data['latitude']);
                                    $set('longitude', $data['longitude']);
                                })
                                ->helperText('Autocomplétion Base Adresse Nationale (France). La saisie manuelle reste possible.')
                                ->columnSpanFull(),
                            TextInput::make('adresse')
                                ->label('Adresse (voie)')
                                ->placeholder('ex : 5 avenue de la République')
                                ->columnSpanFull(),
                            TextInput::make('code_postal')
                                ->label('Code postal')
                                ->placeholder('ex : 69003'),
                            TextInput::make('ville')
                                ->label('Ville')
                                ->placeholder('ex : Lyon'),
                            TextInput::make('pays')
                                ->label('Pays')
                                ->default('France'),
                            // Coordonnées GPS : renseignées silencieusement par la
                            // recherche d'adresse (pas de saisie manuelle demandée).
                            Hidden::make('latitude'),
                            Hidden::make('longitude'),
                        ]),
                ])->columnSpan(1),
            ]);
    }
}
