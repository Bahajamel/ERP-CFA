<?php

namespace App\Filament\Pages;

use App\Filament\Actions\FicheApprenant;
use App\Filament\Widgets\AssiduiteRepartitionChart;
use App\Models\Candidate;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Consultation de l'assiduité (P1-14-4) : taux de présence par apprenti, avec
 * filtres par classe et par période. Le taux est calculé sur les séances de la
 * période choisie.
 */
class Assiduite extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.assiduite';

    protected static string|\UnitEnum|null $navigationGroup = 'Formation & Scolarité';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Assiduité';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'Assiduité des apprentis';

    /** @var array<string, array{renseignees: int, presents: int, absences_injustifiees: int, taux: int|null}> */
    private array $cache = [];

    /**
     * Classe affichée, pilotée par le graphique du haut : le tableau ne liste
     * que les apprentis de CETTE classe. Une seule sélection commande tout
     * l'écran (pas de second filtre « Classe » qui pourrait le contredire).
     */
    public ?int $classeId = null;

    public function mount(): void
    {
        // Même classe par défaut que le graphique, pour que les deux
        // s'accordent dès l'ouverture.
        $this->classeId = AssiduiteRepartitionChart::classeParDefaut()?->id;
    }

    /** Le graphique a changé de classe : le tableau suit. */
    #[On(AssiduiteRepartitionChart::EVENEMENT_CLASSE)]
    public function changerClasse(?int $classeId = null): void
    {
        $this->classeId = $classeId;
        $this->cache = [];
        $this->resetTable();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_attendance');
    }

    /** @return array<int, class-string> */
    protected function getHeaderWidgets(): array
    {
        return [
            // Une classe à la fois (choisie via Formation → Classe), jamais un
            // agrégat de toutes les formations. La comparaison entre classes
            // reste disponible sur le tableau de bord.
            AssiduiteRepartitionChart::class,
        ];
    }

    /** Assiduité d'un apprenti sur la période filtrée, mémoïsée par ligne. */
    public function assiduiteDe(Candidate $candidate): array
    {
        $periode = $this->getTableFilterState('periode') ?? [];
        $du = $periode['du'] ?? null;
        $au = $periode['au'] ?? null;
        $cle = "{$candidate->id}:{$du}:{$au}";

        return $this->cache[$cle] ??= $candidate->assiduite($du, $au);
    }

    public function table(Table $table): Table
    {
        return $table
            // Uniquement les apprentis de la classe choisie au-dessus.
            ->query(fn (): Builder => Candidate::query()
                ->whereHas('promotions', fn (Builder $q) => $q->when(
                    $this->classeId !== null,
                    fn (Builder $p) => $p->whereKey($this->classeId),
                ))
                ->with('promotions'))
            ->columns([
                TextColumn::make('nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn (Candidate $record): string => $record->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom'])
                    ->weight('bold')
                    ->color('primary')
                    ->tooltip('Voir la fiche apprenant')
                    ->action(FicheApprenant::action()),
                TextColumn::make('promotions.libelle')
                    ->label('Classes')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('seances')
                    ->label('Séances')
                    ->state(fn (Candidate $record): int => $this->assiduiteDe($record)['renseignees'])
                    ->alignCenter(),
                TextColumn::make('absences_injustifiees')
                    ->label('Abs. injustifiées')
                    ->state(fn (Candidate $record): int => $this->assiduiteDe($record)['absences_injustifiees'])
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->alignCenter(),
                TextColumn::make('taux')
                    ->label('Assiduité')
                    ->state(fn (Candidate $record): string => ($t = $this->assiduiteDe($record)['taux']) === null ? '—' : "{$t} %")
                    ->badge()
                    ->color(fn (Candidate $record): string => match (true) {
                        $this->assiduiteDe($record)['taux'] === null => 'gray',
                        $this->assiduiteDe($record)['taux'] >= 90 => 'success',
                        $this->assiduiteDe($record)['taux'] >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->alignCenter(),
            ])
            ->filters([
                // Pas de filtre « Classe » ici : elle se choisit une seule fois,
                // dans le graphique du dessus, qui commande aussi ce tableau.
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('du')->label('Du')->displayFormat('d/m/Y'),
                        DatePicker::make('au')->label('Au')->displayFormat('d/m/Y'),
                    ])
                    // Filtre de calcul uniquement : n'exclut pas d'apprenti de la liste.
                    ->query(fn (Builder $query): Builder => $query),
            ])
            ->defaultSort('nom');
    }
}
