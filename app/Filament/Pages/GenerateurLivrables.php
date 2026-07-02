<?php

namespace App\Filament\Pages;

use App\Filament\Pages\ParametresCfa;
use App\Jobs\GenererLivrablesJob;
use App\Livret\LivretRsClient;
use App\Models\CfaProfile;
use App\Models\Contract;
use App\Models\Document;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Hub LivretRS : un seul écran pour lancer la génération des livrables d'un
 * apprenti (thème, format, RNCP), voir l'état du service et les livrables
 * récemment produits — sans naviguer entre plusieurs sections.
 *
 * @property-read Schema $form
 */
class GenerateurLivrables extends Page implements HasSchemas, HasTable
{
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected string $view = 'filament.pages.generateur-livrables';

    protected static string|\UnitEnum|null $navigationGroup = 'Documents';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Générateur de livrables';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $title = 'Générateur de livrables (LivretRS)';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_documents');
    }

    public function mount(): void
    {
        $profile = CfaProfile::current();

        $this->form->fill([
            'theme_code' => $profile->theme_defaut ?: 'institutionnel',
            'format' => $profile->format_defaut ?: 'pdf',
            'verifier_rncp' => (bool) $profile->verifier_rncp,
        ]);
    }

    public function serviceConfigure(): bool
    {
        return app(LivretRsClient::class)->estConfigure();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('parametresCfa')
                ->label('Paramètres CFA')
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->color('gray')
                ->url(ParametresCfa::getUrl()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Lancer une génération')
                    ->description('Choisissez l\'apprenti puis les options. Les livrables seront générés en tâche de fond et classés dans sa GED.')
                    ->columns(3)
                    ->schema([
                        Select::make('contract_id')
                            ->label('Apprenti (contrat)')
                            ->options(fn () => Contract::query()
                                ->whereNotNull('candidate_id')
                                ->with('candidate')
                                ->get()
                                ->mapWithKeys(fn (Contract $c) => [
                                    $c->id => ($c->candidate?->nom_complet ?? 'Apprenti').' — Contrat #'.$c->id,
                                ])
                                ->all())
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Select::make('theme_code')
                            ->label('Thème')
                            ->options([
                                'institutionnel' => 'Institutionnel',
                                'premium' => 'Premium graphique',
                                'sobre' => 'Sobre',
                            ])
                            ->required(),
                        Select::make('format')
                            ->label('Format')
                            ->options([
                                'pdf' => 'PDF uniquement',
                                'pdf_docx' => 'PDF + DOCX (éditable)',
                            ])
                            ->required(),
                        Toggle::make('verifier_rncp')
                            ->label('Vérifier le RNCP en ligne'),
                    ]),
            ]);
    }

    public function generer(): void
    {
        if (! $this->serviceConfigure()) {
            Notification::make()
                ->title('Service non configuré')
                ->body('Renseignez LIVRETRS_URL, puis vérifiez les Paramètres CFA.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        GenererLivrablesJob::dispatch((int) $data['contract_id'], auth()->id(), [
            'theme_code' => $data['theme_code'] ?? null,
            'format' => $data['format'] ?? null,
            'verifier_rncp' => (bool) ($data['verifier_rncp'] ?? false),
        ]);

        Notification::make()
            ->title('Génération lancée')
            ->body('Les livrables sont en cours de production. Vous serez notifié dès qu\'ils sont dans la GED.')
            ->info()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Livrables récemment générés')
            ->query(
                Document::query()
                    ->where('source', 'livretrs')
                    ->with('documentable')
                    ->withCount('missions')
            )
            ->columns([
                TextColumn::make('nom_fichier')
                    ->label('Livrable')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('apprenti')
                    ->label('Apprenti')
                    ->state(fn (Document $record) => $record->documentable?->nom_complet ?? '—'),
                TextColumn::make('missions_count')
                    ->label('Missions')
                    ->badge()
                    ->color('info'),
                TextColumn::make('created_at')
                    ->label('Généré le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
