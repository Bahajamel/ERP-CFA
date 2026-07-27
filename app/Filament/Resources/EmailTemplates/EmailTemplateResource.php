<?php

namespace App\Filament\Resources\EmailTemplates;

use App\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Models\EmailTemplate;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Modèles d'e-mail (« mails types ») du CFA : réutilisés pour écrire à une
 * entreprise depuis une offre. Réservé aux profils commerciaux ; isolé par CFA.
 */
class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Modèles d\'e-mail';

    protected static ?string $modelLabel = 'modèle d\'e-mail';

    protected static ?string $pluralModelLabel = 'modèles d\'e-mail';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Auth::user()?->can('access_companies') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        // Aide : liste des variables disponibles (résolues à l'envoi depuis l'offre).
        $aide = collect(EmailTemplate::variablesOffre())
            ->map(fn (string $desc, string $cle): string => '{{'.$cle.'}} = '.$desc)
            ->implode(' · ');

        return $schema->components([
            Section::make('Modèle d\'e-mail')
                ->icon('heroicon-o-envelope')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nom du modèle')
                        ->placeholder('ex : Suivi recrutement, Relance partenariat…')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label('Actif')
                        ->default(true)
                        ->helperText('Décochez pour retirer ce modèle de la liste d\'envoi.'),
                    TextInput::make('subject')
                        ->label('Objet')
                        ->placeholder('ex : Point sur le recrutement — {{offre}}')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('body')
                        ->label('Corps du message')
                        ->rows(10)
                        ->required()
                        ->columnSpanFull()
                        ->helperText('Variables disponibles : '.$aide),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Modèle')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('subject')
                    ->label('Objet')
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextColumn::make('creePar.name')
                    ->label('Créé par')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateIcon('heroicon-o-envelope')
            ->emptyStateHeading('Aucun modèle d\'e-mail')
            ->emptyStateDescription('Créez un « mail type » : vous pourrez l\'envoyer à une entreprise depuis une offre, variables pré-remplies.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/create'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
