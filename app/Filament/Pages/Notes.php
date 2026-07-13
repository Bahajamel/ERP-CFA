<?php

namespace App\Filament\Pages;

use App\Enums\DocumentStatut;
use App\Enums\DocumentType;
use App\Enums\EvaluationType;
use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Models\Candidate;
use App\Models\Document;
use App\Models\Evaluation;
use App\Models\Promotion;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Cahier de notes : on choisit une classe (formation + année), puis une matière
 * de son programme, et on voit les notes de tous les inscrits pour cette
 * matière (moyenne par apprenant). Saisie d'une épreuve pour toute la classe.
 */
class Notes extends Page
{
    protected string $view = 'filament.pages.notes';

    protected static string|\UnitEnum|null $navigationGroup = 'Formation & Scolarité';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Notes';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $title = 'Cahier de notes';

    public ?int $promotionId = null;

    public ?string $matiere = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->can('access_attendance');
    }

    public function mount(): void
    {
        $this->promotionId ??= ((int) request('promotion')) ?: (array_key_first($this->classes()) ?: null);
    }

    /** Changer de classe réinitialise la matière sélectionnée. */
    public function updatedPromotionId(): void
    {
        $this->matiere = null;
    }

    public function choisirMatiere(string $matiere): void
    {
        $this->matiere = $matiere;
    }

    /** Les classes (cohortes) sélectionnables, par formation + année. */
    public function classes(): array
    {
        return Promotion::query()
            ->with('formation')
            ->whereNotNull('formation_id')
            ->get()
            ->sortBy([
                fn (Promotion $a, Promotion $b): int => strcmp($a->formation?->libelle ?? '', $b->formation?->libelle ?? ''),
                fn (Promotion $a, Promotion $b): int => strcmp($a->libelle ?? '', $b->libelle ?? ''),
            ])
            ->mapWithKeys(fn (Promotion $p): array => [$p->id => $p->nom_complet])
            ->all();
    }

    /**
     * Les matières de la classe : le programme de sa formation, complété des
     * matières déjà notées (au cas où une note existe hors catalogue).
     */
    public function matieres(): Collection
    {
        if (blank($this->promotionId)) {
            return collect();
        }

        $classe = Promotion::with('formation')->find($this->promotionId);
        $programme = collect($classe?->formation?->programme() ?? []);

        $notees = Evaluation::where('promotion_id', $this->promotionId)
            ->select('matiere')
            ->selectRaw('COUNT(*) as nb')
            ->groupBy('matiere')
            ->pluck('nb', 'matiere');

        return $programme
            ->merge($notees->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $m): array => ['nom' => $m, 'nb' => (int) ($notees[$m] ?? 0)]);
    }

    /** Les inscrits de la classe avec leurs notes pour la matière choisie. */
    private function lignes(): Collection
    {
        if (blank($this->promotionId) || blank($this->matiere)) {
            return collect();
        }

        return Candidate::whereHas('promotions', fn ($q) => $q->whereKey($this->promotionId))
            ->orderBy('nom')->orderBy('prenom')
            ->get()
            ->map(function (Candidate $c): array {
                $notes = Evaluation::where('candidate_id', $c->id)
                    ->where('promotion_id', $this->promotionId)
                    ->where('matiere', $this->matiere)
                    ->orderBy('date')
                    ->get();

                $poids = $notes->sum(fn (Evaluation $e) => (float) $e->coefficient);
                $somme = $notes->sum(fn (Evaluation $e) => $e->noteSur20() * (float) $e->coefficient);
                $copies = $this->examensApprenant($c);

                return [
                    'apprenant' => $c,
                    'notes' => $notes,
                    'moyenne' => $poids > 0 ? round($somme / $poids, 2) : null,
                    'copies' => $copies,                        // pour la colonne « Examen (preuve) »
                    'copiesParType' => $copies->keyBy('type_value'), // note cliquable UNIQUEMENT vers la copie de SON type
                ];
            });
    }

    /**
     * Les copies d'examen déposées par un apprenant pour la matière courante,
     * une par type d'épreuve (Contrôle, Examen…). Chaque entrée : id, type, url, nom.
     *
     * @return Collection<int, array{id:int, type_value:?string, type:string, nom:?string, url:string}>
     */
    private function examensApprenant(Candidate $c): Collection
    {
        if (blank($this->promotionId) || blank($this->matiere)) {
            return collect();
        }

        return $c->documents()
            ->where('type', DocumentType::Examen->value)
            ->with('media')
            ->latest()
            ->get()
            ->filter(function (Document $d): bool {
                $media = $d->getFirstMedia('fichier');

                return $media?->getCustomProperty('matiere') === $this->matiere
                    && (int) $media?->getCustomProperty('promotion_id') === (int) $this->promotionId;
            })
            ->map(function (Document $d): array {
                $media = $d->getFirstMedia('fichier');
                $tv = $media?->getCustomProperty('type');

                return [
                    'id' => $d->id,
                    'type_value' => $tv,
                    'type' => $tv ? (EvaluationType::tryFrom($tv)?->getLabel() ?? 'Copie') : 'Copie',
                    'nom' => $d->nom_fichier,
                    'url' => $d->getFirstMediaUrl('fichier'),
                ];
            })
            ->unique('type_value')
            ->values();
    }

    protected function getViewData(): array
    {
        $classe = $this->promotionId ? Promotion::with('formation')->find($this->promotionId) : null;

        return [
            'classes' => $this->classes(),
            'classe' => $classe,
            'matieres' => $this->matieres(),
            'lignes' => $this->lignes(),
            'urlEdition' => fn (Evaluation $e): string => EvaluationResource::getUrl('edit', ['record' => $e]),
        ];
    }

    /**
     * Importer la copie d'examen d'UN apprenant, en preuve de sa note pour la
     * matière courante. Rattachée à l'apprenant (GED) avec matière + classe en
     * propriétés du média. Remplace la copie précédente s'il y en avait une.
     */
    public function importerExamenApprenantAction(): Action
    {
        return Action::make('importerExamenApprenant')
            ->modalHeading(fn (array $arguments): string => 'Importer la copie d\'examen — '
                .(Candidate::find($arguments['candidate'] ?? null)?->nom_complet ?? ''))
            ->modalDescription(fn (): string => 'Matière : '.$this->matiere.'. La copie est archivée comme preuve de la note.')
            ->schema([
                Select::make('type_examen')
                    ->label('Type d\'épreuve')
                    ->options(EvaluationType::class)
                    ->default(EvaluationType::Examen->value)
                    ->required(),
                FileUpload::make('fichier')
                    ->label('Copie de l\'examen (PDF ou image)')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('nom')
                    ->disk('public')
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $candidate = Candidate::find($arguments['candidate'] ?? null);

                if ($candidate === null || blank($this->matiere)) {
                    return;
                }

                // Une copie par type : on remplace celle du même type si elle existe.
                $type = $data['type_examen'] ?? EvaluationType::Examen->value;
                $existante = $this->examensApprenant($candidate)->firstWhere('type_value', $type);

                if ($existante) {
                    Document::find($existante['id'])?->delete();
                }

                $doc = $candidate->documents()->create([
                    'type' => DocumentType::Examen->value,
                    'statut' => DocumentStatut::Recu->value,
                    'nom_fichier' => $data['nom'] ?? 'examen.pdf',
                    'version' => 1,
                    'uploaded_by' => Auth::id(),
                ]);

                $doc->addMediaFromDisk($data['fichier'], 'public')
                    ->withCustomProperties([
                        'matiere' => $this->matiere,
                        'promotion_id' => $this->promotionId,
                        'type' => $data['type_examen'] ?? EvaluationType::Examen->value,
                    ])
                    ->toMediaCollection('fichier');

                Notification::make()->success()
                    ->title('Copie importée')
                    ->body($candidate->nom_complet.' — preuve enregistrée pour « '.$this->matiere.' ».')
                    ->send();
            })
            ->modalSubmitActionLabel('Importer');
    }

    /** Retirer la copie d'examen d'un apprenant pour un type d'épreuve donné. */
    public function retirerExamenApprenantAction(): Action
    {
        return Action::make('retirerExamenApprenant')
            ->requiresConfirmation()
            ->modalHeading('Retirer cette copie ?')
            ->modalDescription('La copie sera archivée (retirée des preuves de cet apprenant pour cette matière).')
            ->action(function (array $arguments): void {
                $candidate = Candidate::find($arguments['candidate'] ?? null);

                if ($candidate !== null) {
                    $copie = $this->examensApprenant($candidate)->firstWhere('type_value', $arguments['type'] ?? null);

                    if ($copie) {
                        Document::find($copie['id'])?->delete();
                    }
                }

                Notification::make()->success()->title('Copie retirée')->send();
            });
    }

    /** Saisir une nouvelle épreuve (une note par apprenant) pour la matière courante. */
    public function nouvelleEpreuveAction(): Action
    {
        return Action::make('nouvelleEpreuve')
            ->label('Nouvelle épreuve')
            ->icon('heroicon-o-plus')
            ->visible(fn (): bool => filled($this->promotionId) && filled($this->matiere))
            ->modalHeading(fn (): string => 'Nouvelle épreuve — '.$this->matiere)
            ->schema([
                Section::make()
                    ->columns(3)
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options(EvaluationType::class)
                            ->default(EvaluationType::Devoir->value)
                            ->required(),
                        TextInput::make('bareme')->label('Barème (sur)')->numeric()->minValue(1)->default(20)->required(),
                        TextInput::make('coefficient')->label('Coefficient')->numeric()->minValue(0.5)->step(0.5)->default(1)->required(),
                        DatePicker::make('date')->label('Date')->default(now())->displayFormat('d/m/Y'),
                    ]),
                Section::make('Notes des apprenants')
                    ->description('Laissez vide un apprenant non noté (il sera ignoré).')
                    ->schema(fn (Get $get): array => $this->champsNotes((float) ($get('bareme') ?: 20))),
            ])
            ->action(function (array $data): void {
                $creees = 0;

                foreach ($this->apprenantsClasse() as $c) {
                    $valeur = $data['note_'.$c->id] ?? null;

                    if ($valeur === null || $valeur === '') {
                        continue;
                    }

                    Evaluation::create([
                        'candidate_id' => $c->id,
                        'promotion_id' => $this->promotionId,
                        'matiere' => $this->matiere,
                        'type' => $data['type'],
                        'note' => $valeur,
                        'bareme' => $data['bareme'] ?? 20,
                        'coefficient' => $data['coefficient'] ?? 1,
                        'date' => $data['date'] ?? now(),
                        'author_id' => Auth::id(),
                    ]);
                    $creees++;
                }

                Notification::make()
                    ->success()
                    ->title($creees.' note'.($creees > 1 ? 's' : '').' enregistrée'.($creees > 1 ? 's' : ''))
                    ->send();
            })
            ->modalSubmitActionLabel('Enregistrer')
            ->modalWidth('3xl');
    }

    /** Un champ note par apprenant de la classe. */
    private function champsNotes(float $bareme): array
    {
        $champs = $this->apprenantsClasse()
            ->map(fn (Candidate $c) => TextInput::make('note_'.$c->id)
                ->label($c->nom_complet)
                ->numeric()->minValue(0)->maxValue($bareme)->step(0.25)
                ->suffix('/ '.rtrim(rtrim(number_format($bareme, 2, ',', ''), '0'), ',')))
            ->all();

        return [
            Grid::make(['default' => 1, 'md' => 2])
                ->extraAttributes(['style' => 'max-height: 45vh; overflow-y: auto; gap: .75rem; padding: .5rem;'])
                ->schema($champs),
        ];
    }

    private function apprenantsClasse(): Collection
    {
        return Candidate::whereHas('promotions', fn ($q) => $q->whereKey($this->promotionId))
            ->orderBy('nom')->orderBy('prenom')
            ->get();
    }
}
