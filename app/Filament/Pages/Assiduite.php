<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AssiduiteParPromotionChart;
use App\Models\Candidate;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

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

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Assiduité';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'Assiduité des apprentis';

    /** @var array<string, array{renseignees: int, presents: int, absences_injustifiees: int, taux: int|null}> */
    private array $cache = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_attendance');
    }

    /**
     * Deep-link depuis le graphique « Assiduité par promotion » : ?classe=<id>
     * pré-applique le filtre de classe pour arriver directement sur l'assiduité
     * de la promotion cliquée.
     */
    public function mount(?string $classe = null): void
    {
        if (filled($classe)) {
            $this->tableFilters['promotion_id']['value'] = $classe;
        }
    }

    /** @return array<int, class-string> */
    protected function getHeaderWidgets(): array
    {
        return [
            AssiduiteParPromotionChart::class,
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
            ->query(Candidate::query()->whereNotNull('promotion_id')->with('promotion'))
            ->columns([
                TextColumn::make('nom_complet')
                    ->label('Apprenti')
                    ->getStateUsing(fn (Candidate $record): string => $record->nom_complet)
                    ->searchable(['nom', 'prenom'])
                    ->sortable(['nom']),
                TextColumn::make('promotion.libelle')
                    ->label('Classe')
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
                SelectFilter::make('promotion_id')
                    ->label('Classe')
                    ->relationship('promotion', 'libelle')
                    ->searchable()
                    ->preload(),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('du')->label('Du')->displayFormat('d/m/Y'),
                        DatePicker::make('au')->label('Au')->displayFormat('d/m/Y'),
                    ])
                    // Filtre de calcul uniquement : n'exclut pas d'apprenti de la liste.
                    ->query(fn (Builder $query): Builder => $query),
            ])
            // Filtres appliqués immédiatement (pas de bouton « Appliquer ») :
            // nécessaire pour que le deep-link ?classe=<id> soit pris en compte.
            ->deferFilters(false)
            ->defaultSort('nom');
    }
}
