<?php

namespace App\Support;

use App\Enums\CustomFieldType;
use App\Models\Candidate;
use App\Models\CustomColumnSetting;
use App\Models\CustomFieldDefinition;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Colonnes personnalisées d'un CFA (couche « façon Monday ») :
 *  - sur une entité métier existante (ex. Candidats), via un bouton + modal ;
 *  - sur un tableau personnalisé entièrement créé par le CFA (Phase 3).
 *
 * Les définitions vivent dans custom_field_definitions ; les valeurs dans une
 * colonne JSONB (`custom_fields` pour une entité, `data` pour une ligne de
 * tableau), adressées via « {préfixe}.{clé} ». Cloisonné par CFA via le global
 * scope de {@see CustomFieldDefinition}.
 */
class CustomFields
{
    /** Colonnes d'une entité métier (hors tableaux personnalisés). */
    public static function definitions(string $entity): Collection
    {
        return CustomFieldDefinition::query()
            ->where('entity', $entity)
            ->whereNull('custom_table_id')
            ->orderBy('sort')->orderBy('id')
            ->get();
    }

    /** Colonnes d'un tableau personnalisé. */
    public static function definitionsTableau(int $customTableId): Collection
    {
        return CustomFieldDefinition::query()
            ->where('custom_table_id', $customTableId)
            ->orderBy('sort')->orderBy('id')
            ->get();
    }

    /** Rôles autorisés à gérer les colonnes / tableaux personnalisés. */
    public static function peutGerer(): bool
    {
        return auth()->user()?->hasAnyRole(['Administrateur', 'Direction']) ?? false;
    }

    /* ============================================================
     |  Bouton + modal de gestion des colonnes (entité métier)
     * ============================================================ */

    public static function gererAction(string $entity, string $labelEntite, ?string $name = null): Action
    {
        // Le nom de l'action DOIT correspondre à la méthode « {nom}Action() » de la
        // page qui l'expose, sinon Filament ne la résout pas au clic (le modal ne
        // s'ouvre pas). D'où le paramètre $name laissé au choix de l'appelant.
        return Action::make($name ?? 'colonnesPersonnalisees_'.$entity)
            ->label('Colonnes personnalisées')
            ->icon('heroicon-o-view-columns')
            ->color('gray')
            ->visible(fn (): bool => self::peutGerer())
            ->modalHeading("Colonnes personnalisées — {$labelEntite}")
            ->modalDescription('Ajoutez vos propres colonnes à cette table : elles apparaîtront dans les fiches et le tableau, pour votre CFA uniquement.')
            ->modalSubmitActionLabel('Enregistrer les colonnes')
            ->modalWidth('3xl')
            ->fillForm(fn (): array => ['colonnes' => self::lignesDepuis(self::definitions($entity))])
            ->schema([self::repeaterColonnes()])
            ->action(function (array $data, $livewire) use ($entity): void {
                self::synchroniser($entity, $data['colonnes'] ?? []);
                Notification::make()->success()->title('Colonnes mises à jour')->send();

                if (is_object($livewire) && method_exists($livewire, 'resetTable')) {
                    $livewire->resetTable();
                }
            });
    }

    /**
     * Bouton + modal de gestion des colonnes d'un TABLEAU personnalisé (ajouter,
     * renommer, réordonner, retirer) — utilisable directement sur le board.
     */
    public static function gererActionTableau(int $customTableId, ?string $name = null): Action
    {
        return Action::make($name ?? 'gererColonnesTableau')
            ->label('Ajouter une colonne')
            ->icon('heroicon-o-view-columns')
            ->color('gray')
            ->modalHeading('Colonnes du tableau')
            ->modalDescription('Ajoutez, renommez, réordonnez ou retirez les colonnes de ce tableau.')
            ->modalSubmitActionLabel('Enregistrer les colonnes')
            ->modalWidth('3xl')
            ->fillForm(fn (): array => ['colonnes' => self::lignesDepuis(self::definitionsTableau($customTableId))])
            ->schema([self::repeaterColonnes()])
            ->action(function (array $data, $livewire) use ($customTableId): void {
                self::synchroniserTableau($customTableId, $data['colonnes'] ?? []);
                Notification::make()->success()->title('Colonnes mises à jour')->send();

                if (is_object($livewire) && method_exists($livewire, 'resetTable')) {
                    $livewire->resetTable();
                }
            });
    }

    /** Repeater réutilisable décrivant des colonnes (nom + type + options + visibilité). */
    public static function repeaterColonnes(): Repeater
    {
        return Repeater::make('colonnes')
            ->hiddenLabel()
            ->addActionLabel('Ajouter une colonne')
            ->reorderable()
            ->cloneable()
            ->itemLabel(fn (array $state): string => filled($state['label'] ?? null) ? $state['label'] : 'Nouvelle colonne')
            ->columns(2)
            ->schema([
                Hidden::make('id'),
                TextInput::make('label')
                    ->label('Nom de la colonne')
                    ->placeholder('ex : Référence, Priorité…')
                    ->required()->maxLength(255),
                Select::make('type')
                    ->label('Type')
                    ->options(CustomFieldType::options())
                    ->default(CustomFieldType::Text->value)
                    ->required()->live(),
                TagsInput::make('options')
                    ->label('Options de la liste')
                    ->placeholder('Ajouter une option…')
                    // Les types « Liste déroulante » et « Statut » ont des options.
                    ->visible(fn (Get $get): bool => in_array($get('type'), [CustomFieldType::Select->value, CustomFieldType::Statut->value], true))
                    ->required(fn (Get $get): bool => in_array($get('type'), [CustomFieldType::Select->value, CustomFieldType::Statut->value], true))
                    ->columnSpanFull(),
                // Valeur par défaut (proposée à la saisie d'une nouvelle ligne).
                TextInput::make('default_value')
                    ->label('Valeur par défaut')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => ! in_array($get('type'), [CustomFieldType::Boolean->value, CustomFieldType::Utilisateur->value], true)),
                Toggle::make('default_bool')
                    ->label('Coché par défaut')
                    ->visible(fn (Get $get): bool => $get('type') === CustomFieldType::Boolean->value),
                // Validation simple par type.
                TextInput::make('val_max_length')
                    ->label('Longueur maximale')
                    ->numeric()->minValue(1)
                    ->visible(fn (Get $get): bool => in_array($get('type'), [CustomFieldType::Text->value, CustomFieldType::Textarea->value], true)),
                TextInput::make('val_min')
                    ->label('Valeur minimale')
                    ->numeric()
                    ->visible(fn (Get $get): bool => $get('type') === CustomFieldType::Number->value),
                TextInput::make('val_max')
                    ->label('Valeur maximale')
                    ->numeric()
                    ->visible(fn (Get $get): bool => $get('type') === CustomFieldType::Number->value),
                Toggle::make('is_required')
                    ->label('Obligatoire'),
                Toggle::make('visible_table')
                    ->label('Afficher dans le tableau')
                    ->default(true),
            ]);
    }

    /** Transforme des définitions en lignes de repeater (pour préremplir un modal). */
    public static function lignesDepuis(Collection $definitions): array
    {
        return $definitions->map(function (CustomFieldDefinition $d): array {
            $validation = (array) ($d->validation_rules ?? []);
            $estBool = $d->type === CustomFieldType::Boolean;

            return [
                'id' => $d->id,
                'label' => $d->label,
                'type' => $d->type->value,
                'options' => $d->config['options'] ?? [],
                'default_value' => $estBool ? null : ($d->default_value['value'] ?? null),
                'default_bool' => $estBool ? (bool) ($d->default_value['value'] ?? false) : false,
                'val_max_length' => $validation['max_length'] ?? null,
                'val_min' => $validation['min'] ?? null,
                'val_max' => $validation['max'] ?? null,
                'is_required' => $d->is_required,
                'visible_table' => $d->visible_table,
            ];
        })->all();
    }

    /* ============================================================
     |  Synchronisation des colonnes
     * ============================================================ */

    /** Colonnes d'une entité métier (custom_table_id null). */
    public static function synchroniser(string $entity, array $lignes): void
    {
        self::reconcilier(
            existantes: self::definitions($entity),
            lignes: $lignes,
            match: ['entity' => $entity, 'custom_table_id' => null],
        );
    }

    /** Colonnes d'un tableau personnalisé. */
    public static function synchroniserTableau(int $customTableId, array $lignes): void
    {
        self::reconcilier(
            existantes: self::definitionsTableau($customTableId),
            lignes: $lignes,
            match: ['entity' => 'record', 'custom_table_id' => $customTableId],
        );
    }

    /**
     * Réconcilie un jeu de définitions avec les lignes soumises : crée les
     * nouvelles, met à jour les existantes (avec l'ordre), supprime les retirées.
     */
    private static function reconcilier(Collection $existantes, array $lignes, array $match): void
    {
        $existantes = $existantes->keyBy('id');
        $gardees = [];

        foreach (array_values($lignes) as $index => $ligne) {
            $label = trim((string) ($ligne['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $type = $ligne['type'] ?? CustomFieldType::Text->value;
            $aOptions = in_array($type, [CustomFieldType::Select->value, CustomFieldType::Statut->value], true);
            $config = $aOptions
                ? ['options' => collect($ligne['options'] ?? [])->map(fn ($o) => trim((string) $o))->filter()->unique()->values()->all()]
                : null;

            // Valeur par défaut : booléen coché, sinon texte non vide (ou aucune).
            $defaut = $type === CustomFieldType::Boolean->value
                ? ((bool) ($ligne['default_bool'] ?? false) ? true : null)
                : (($v = trim((string) ($ligne['default_value'] ?? ''))) !== '' ? $v : null);

            // Validation simple (longueur max pour le texte ; min/max pour le nombre).
            $validation = array_filter([
                'max_length' => filled($ligne['val_max_length'] ?? null) ? (int) $ligne['val_max_length'] : null,
                'min' => filled($ligne['val_min'] ?? null) ? 0 + $ligne['val_min'] : null,
                'max' => filled($ligne['val_max'] ?? null) ? 0 + $ligne['val_max'] : null,
            ], fn ($x): bool => $x !== null);

            $attributs = [
                'label' => $label,
                'type' => $type,
                'config' => $config,
                'default_value' => $defaut !== null ? ['value' => $defaut] : null,
                'validation_rules' => $validation !== [] ? $validation : null,
                'is_required' => (bool) ($ligne['is_required'] ?? false),
                'visible_table' => (bool) ($ligne['visible_table'] ?? true),
                'sort' => $index,
            ];

            $id = $ligne['id'] ?? null;

            if ($id !== null && $existantes->has($id)) {
                $existantes[$id]->update($attributs);
                $gardees[] = $id;
            } else {
                $def = CustomFieldDefinition::create($attributs + $match + [
                    'key' => self::cleUnique($match, $label),
                ]);
                $gardees[] = $def->id;
            }
        }

        $requete = CustomFieldDefinition::query()->whereNotIn('id', $gardees ?: [0]);

        isset($match['custom_table_id']) && $match['custom_table_id'] !== null
            ? $requete->where('custom_table_id', $match['custom_table_id'])
            : $requete->where('entity', $match['entity'])->whereNull('custom_table_id');

        $requete->delete();
    }

    /** Clé technique unique dans son périmètre (CFA + entité/tableau). */
    private static function cleUnique(array $match, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'colonne';
        $cle = $base;
        $i = 2;

        $portee = fn () => CustomFieldDefinition::query()
            ->when(($match['custom_table_id'] ?? null) !== null,
                fn ($q) => $q->where('custom_table_id', $match['custom_table_id']),
                fn ($q) => $q->where('entity', $match['entity'])->whereNull('custom_table_id'));

        while ($portee()->where('key', $cle)->exists()) {
            $cle = $base.'_'.$i;
            $i++;
        }

        return $cle;
    }

    /* ============================================================
     |  Suppression d'une colonne personnalisée (façon Monday)
     * ============================================================ */

    /** Entité métier => modèle Eloquent porteur du JSONB « custom_fields ». */
    private const MODELES = [
        'candidate' => Candidate::class,
    ];

    /**
     * Bouton + modal « Supprimer une colonne » : ne concerne QUE les colonnes
     * personnalisées du CFA (les colonnes natives se masquent via le menu
     * « Colonnes »). Toujours proposé (pour rester découvrable) ; quand il n'y a
     * aucune colonne personnalisée, le modal l'explique et n'a pas de bouton
     * « Supprimer ».
     */
    public static function supprimerColonneAction(string $entity, string $labelEntite, ?string $name = null): Action
    {
        $vide = fn (): bool => self::definitions($entity)->isEmpty();

        // Nom aligné sur la méthode « {nom}Action() » de la page (cf. gererAction).
        return Action::make($name ?? 'supprimerColonneCustom_'.$entity)
            ->label('Supprimer une colonne')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (): bool => self::peutGerer())
            ->modalHeading("Supprimer une colonne — {$labelEntite}")
            ->modalDescription(fn (): string => $vide()
                ? 'Aucune colonne personnalisée à supprimer pour le moment. Les colonnes de base ne sont pas '
                    .'supprimables : masquez-les via le menu « Colonnes ». Ajoutez d’abord une colonne pour pouvoir la supprimer.'
                : 'Choisissez une colonne personnalisée à supprimer. La colonne ET ses valeurs sur toutes les lignes '
                    .'sont effacées définitivement. Les colonnes de base ne sont pas supprimables (masquez-les via le menu « Colonnes »).')
            ->modalWidth('lg')
            // Pas de bouton « Supprimer » quand il n'y a rien à supprimer.
            ->modalSubmitAction(fn () => $vide() ? false : null)
            ->modalCancelActionLabel(fn (): string => $vide() ? 'Fermer' : 'Annuler')
            ->modalSubmitActionLabel('Supprimer la colonne')
            ->schema(fn (): array => $vide() ? [] : [
                Select::make('definition_id')
                    ->label('Colonne à supprimer')
                    ->options(fn (): array => self::definitions($entity)->pluck('label', 'id')->all())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data, $livewire) use ($entity): void {
                if (blank($data['definition_id'] ?? null)) {
                    return;
                }

                self::supprimerColonne($entity, (int) $data['definition_id']);
                Notification::make()->success()->title('Colonne supprimée')->send();

                if (is_object($livewire) && method_exists($livewire, 'resetTable')) {
                    $livewire->resetTable();
                }
            });
    }

    /**
     * Supprime une colonne personnalisée d'une entité : la définition, puis la
     * valeur orpheline dans le JSONB « custom_fields » de chaque ligne (sinon une
     * colonne recréée avec la même clé récupérerait d'anciennes valeurs).
     */
    public static function supprimerColonne(string $entity, int $definitionId): void
    {
        if (! self::peutGerer()) {
            return;
        }

        $definition = CustomFieldDefinition::query()
            ->where('entity', $entity)
            ->whereNull('custom_table_id')
            ->find($definitionId);

        if ($definition === null) {
            return;
        }

        $cle = $definition->key;
        $definition->delete();

        $modele = self::MODELES[$entity] ?? null;
        if ($modele === null) {
            return;
        }

        $modele::query()
            ->whereNotNull('custom_fields')
            ->chunkById(200, function ($lignes) use ($cle): void {
                foreach ($lignes as $ligne) {
                    $valeurs = $ligne->custom_fields ?? [];

                    if (array_key_exists($cle, $valeurs)) {
                        unset($valeurs[$cle]);
                        $ligne->custom_fields = $valeurs;
                        $ligne->saveQuietly();
                    }
                }
            });
    }

    /* ============================================================
     |  Construction des champs / colonnes (formulaires & tableaux)
     * ============================================================ */

    /** Section « Champs personnalisés » d'une entité métier (préfixe custom_fields). */
    public static function formSchema(string $entity): array
    {
        $definitions = self::definitions($entity);

        if ($definitions->isEmpty()) {
            return [];
        }

        return [
            Section::make('Champs personnalisés')
                ->icon('heroicon-o-adjustments-horizontal')
                ->description('Colonnes propres à votre CFA.')
                ->columns(2)->columnSpanFull()
                ->schema(self::champs($definitions, 'custom_fields')),
        ];
    }

    /** Colonnes de tableau d'une entité métier (masquables). */
    public static function tableColumns(string $entity): array
    {
        return self::colonnes(self::definitions($entity), 'custom_fields');
    }

    /* ============================================================
     |  Surcharge des colonnes NATIVES (renommage + largeur par CFA)
     * ============================================================ */

    /** Surcharges des colonnes natives d'une entité, indexées par clé de colonne. */
    public static function reglagesColonnes(string $entity): Collection
    {
        return CustomColumnSetting::query()
            ->where('entity', $entity)
            ->get()
            ->keyBy('column_key');
    }

    /**
     * Applique aux colonnes Filament les surcharges du CFA (libellé renommé,
     * largeur imposée), pose un « data-col-key » sur chaque en-tête (cible du
     * redimensionnement et du glisser-déposer souris), puis réordonne les colonnes
     * selon la position mémorisée par CFA. Les colonnes sans surcharge/position
     * restent intactes et conservent leur ordre d'origine (après les ordonnées).
     *
     * @param  array<int, mixed>  $columns
     * @return array<int, mixed>
     */
    public static function appliquerReglages(array $columns, string $entity): array
    {
        $reglages = self::reglagesColonnes($entity);

        foreach ($columns as $col) {
            if (! $col instanceof Column) {
                continue;
            }

            $key = $col->getName();
            $col->extraHeaderAttributes(['data-col-key' => $key], merge: true);

            $reglage = $reglages->get($key);
            if ($reglage === null) {
                continue;
            }

            if (filled($reglage->label)) {
                $col->label($reglage->label);
            }
            if ($reglage->width) {
                $col->width("{$reglage->width}px");
            }
        }

        return self::ordonner($columns, $reglages);
    }

    /**
     * Réordonne les colonnes selon la position mémorisée (glisser-déposer). Les
     * colonnes positionnées passent en tête, dans l'ordre choisi ; les autres
     * suivent, dans leur ordre d'origine (tri stable).
     *
     * @param  array<int, mixed>  $columns
     * @return array<int, mixed>
     */
    private static function ordonner(array $columns, Collection $reglages): array
    {
        $positionnees = [];
        $libres = [];

        foreach ($columns as $index => $col) {
            $position = $col instanceof Column ? $reglages->get($col->getName())?->position : null;

            if ($position !== null) {
                $positionnees[] = ['position' => $position, 'index' => $index, 'col' => $col];
            } else {
                $libres[] = $col;
            }
        }

        usort($positionnees, fn (array $a, array $b): int => [$a['position'], $a['index']] <=> [$b['position'], $b['index']]);

        return array_merge(array_map(fn (array $x) => $x['col'], $positionnees), $libres);
    }

    /**
     * Enregistre l'ordre des colonnes (positions 0..n) pour la liste de clés
     * fournie — alimenté par le glisser-déposer des en-têtes. Par CFA.
     *
     * @param  list<string>  $cles
     */
    public static function definirOrdre(string $entity, array $cles): void
    {
        if (! self::peutGerer()) {
            return;
        }

        foreach (array_values($cles) as $position => $cle) {
            $cle = (string) $cle;

            if ($cle === '') {
                continue;
            }

            self::majReglage($entity, $cle, fn (CustomColumnSetting $s) => $s->position = $position);
        }
    }

    /**
     * Enregistre (ou efface, si null) la largeur d'une colonne native.
     * Appelé par le glisser-déposer souris et le double-clic « réinitialiser ».
     */
    public static function definirLargeur(string $entity, string $key, ?int $px): void
    {
        if (! self::peutGerer()) {
            return;
        }

        $px = $px !== null ? max(80, min(720, $px)) : null;

        self::majReglage($entity, $key, fn (CustomColumnSetting $s) => $s->width = $px);
    }

    /**
     * Bouton + modal « Renommer les colonnes » : renomme les colonnes de base par
     * CFA (la largeur, elle, s'ajuste à la souris pour ne pas écraser un réglage).
     *
     * @param  array<string, string>  $colonnesBase  clé de colonne => libellé d'origine
     */
    public static function personnaliserAction(string $entity, string $labelEntite, array $colonnesBase, ?string $name = null): Action
    {
        // Nom aligné sur la méthode « {nom}Action() » de la page (cf. gererAction).
        return Action::make($name ?? 'personnaliserColonnes_'.$entity)
            ->label('Renommer les colonnes')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (): bool => self::peutGerer())
            ->modalHeading("Personnaliser les colonnes — {$labelEntite}")
            ->modalDescription('Renommez les colonnes de base, pour votre CFA uniquement. Laissez vide pour garder '
                .'le libellé d’origine. La largeur s’ajuste à la souris en tirant le bord d’une colonne '
                .'(double-clic sur le bord pour réinitialiser).')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Enregistrer')
            ->fillForm(fn (): array => self::reglagesForm($entity, $colonnesBase))
            ->schema(self::schemaPersonnalisation($colonnesBase))
            ->action(function (array $data, $livewire) use ($entity, $colonnesBase): void {
                self::enregistrerReglages($entity, $colonnesBase, $data);
                Notification::make()->success()->title('Colonnes personnalisées')->send();

                if (is_object($livewire) && method_exists($livewire, 'resetTable')) {
                    $livewire->resetTable();
                }
            });
    }

    /** Clé de champ de formulaire sûre (sans point) pour une clé de colonne. */
    private static function cleForm(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** Schéma du modal : un champ « nom affiché » par colonne de base (2 colonnes). */
    private static function schemaPersonnalisation(array $colonnesBase): array
    {
        $champs = [];

        foreach ($colonnesBase as $key => $defaut) {
            $champs[] = TextInput::make('renoms.'.self::cleForm($key))
                ->label($defaut)
                ->placeholder($defaut)
                ->maxLength(255);
        }

        return [Grid::make(['default' => 1, 'sm' => 2])->schema($champs)];
    }

    /** Pré-remplissage du modal depuis les libellés déjà surchargés. */
    private static function reglagesForm(string $entity, array $colonnesBase): array
    {
        $reglages = self::reglagesColonnes($entity);
        $renoms = [];

        foreach (array_keys($colonnesBase) as $key) {
            $renoms[self::cleForm($key)] = $reglages->get($key)?->label;
        }

        return ['renoms' => $renoms];
    }

    /** Applique le modal : surcharge de libellé (la largeur n'est pas touchée ici). */
    private static function enregistrerReglages(string $entity, array $colonnesBase, array $data): void
    {
        foreach (array_keys($colonnesBase) as $key) {
            $label = trim((string) ($data['renoms'][self::cleForm($key)] ?? ''));

            self::majReglage($entity, $key, fn (CustomColumnSetting $s) => $s->label = $label !== '' ? $label : null);
        }
    }

    /**
     * Récupère (ou crée) la surcharge d'une colonne, applique la mutation, puis
     * supprime la ligne si elle ne porte plus aucune surcharge (base propre) :
     * ni libellé, ni largeur, ni position.
     */
    private static function majReglage(string $entity, string $key, callable $muter): void
    {
        $reglage = CustomColumnSetting::query()
            ->where('entity', $entity)
            ->where('column_key', $key)
            ->first()
            ?? new CustomColumnSetting(['entity' => $entity, 'column_key' => $key]);

        $muter($reglage);

        // position 0 est significative : on teste explicitement le null.
        if (blank($reglage->label) && blank($reglage->width) && $reglage->position === null) {
            if ($reglage->exists) {
                $reglage->delete();
            }

            return;
        }

        $reglage->save();
    }

    /** Champs de formulaire pour un jeu de définitions, préfixés (custom_fields|data). */
    public static function champs(Collection $definitions, string $prefixe): array
    {
        return $definitions->map(fn (CustomFieldDefinition $d) => self::champ($d, $prefixe))->all();
    }

    /** Colonnes de tableau pour un jeu de définitions, préfixées. */
    public static function colonnes(Collection $definitions, string $prefixe): array
    {
        return $definitions->map(function (CustomFieldDefinition $def) use ($prefixe) {
            $chemin = "{$prefixe}.{$def->key}";

            $colonne = match ($def->type) {
                CustomFieldType::Boolean => IconColumn::make($chemin)->label($def->label)->boolean(),
                CustomFieldType::Date => TextColumn::make($chemin)->label($def->label)->date('d/m/Y'),
                CustomFieldType::Select => TextColumn::make($chemin)->label($def->label)->badge()->color('gray'),
                CustomFieldType::Statut => TextColumn::make($chemin)->label($def->label)->badge()
                    ->color(fn (?string $state): string => self::couleurStatut($def, $state)),
                CustomFieldType::Utilisateur => TextColumn::make($chemin)->label($def->label)
                    ->formatStateUsing(fn ($state): ?string => filled($state) ? (User::find($state)?->name ?? '—') : null),
                CustomFieldType::Number => TextColumn::make($chemin)->label($def->label)->numeric(),
                default => TextColumn::make($chemin)->label($def->label),
            };

            return $colonne
                ->toggleable(isToggledHiddenByDefault: ! $def->visible_table)
                ->placeholder('—');
        })->all();
    }

    private static function champ(CustomFieldDefinition $def, string $prefixe)
    {
        $chemin = "{$prefixe}.{$def->key}";

        $champ = match ($def->type) {
            CustomFieldType::Text => TextInput::make($chemin)->label($def->label)->maxLength(255),
            CustomFieldType::Textarea => Textarea::make($chemin)->label($def->label)->rows(3),
            CustomFieldType::Number => TextInput::make($chemin)->label($def->label)->numeric(),
            CustomFieldType::Date => DatePicker::make($chemin)->label($def->label)->displayFormat('d/m/Y')->native(false),
            CustomFieldType::Boolean => Toggle::make($chemin)->label($def->label),
            CustomFieldType::Select => self::selectCreable($chemin, $def),
            CustomFieldType::Statut => self::selectCreable($chemin, $def),
            CustomFieldType::Utilisateur => Select::make($chemin)->label($def->label)
                ->options(self::optionsUtilisateurs())->searchable()->native(false),
        };

        // Obligatoire (sauf Oui/Non, où « requis » n'a pas de sens), validation par
        // type, et valeur par défaut proposée à la saisie.
        if ($def->is_required && $def->type !== CustomFieldType::Boolean) {
            $champ->required();
        }

        $regles = self::reglesValidation($def);
        if ($regles !== []) {
            $champ->rules($regles);
        }

        if ($def->default_value !== null && array_key_exists('value', (array) $def->default_value)) {
            $champ->default($def->default_value['value']);
        }

        return $champ;
    }

    /**
     * Règles de validation Laravel d'une colonne (au-delà de « required »), bâties
     * depuis ses paramètres : longueur max pour le texte, bornes min/max pour le
     * nombre. Réutilisées par le formulaire ERP et par le formulaire public.
     *
     * @return list<string>
     */
    public static function reglesValidation(CustomFieldDefinition $def): array
    {
        $v = (array) ($def->validation_rules ?? []);
        $rules = [];

        if (in_array($def->type, [CustomFieldType::Text, CustomFieldType::Textarea], true) && filled($v['max_length'] ?? null)) {
            $rules[] = 'max:'.(int) $v['max_length'];
        }

        if ($def->type === CustomFieldType::Number) {
            if (filled($v['min'] ?? null)) {
                $rules[] = 'min:'.$v['min'];
            }
            if (filled($v['max'] ?? null)) {
                $rules[] = 'max:'.$v['max'];
            }
        }

        return $rules;
    }

    /**
     * Filtres de tableau pour les colonnes « à valeurs » (Liste / Statut) : un
     * SelectFilter par colonne, requêté sur le JSONB via la syntaxe fléchée de
     * Laravel (portable Postgres/SQLite). Utilisé par le board des tables custom.
     *
     * @return array<int, SelectFilter>
     */
    public static function filtres(Collection $definitions, string $prefixe): array
    {
        return $definitions
            ->filter(fn (CustomFieldDefinition $d): bool => in_array($d->type, [CustomFieldType::Select, CustomFieldType::Statut], true))
            ->map(function (CustomFieldDefinition $def) use ($prefixe): SelectFilter {
                return SelectFilter::make($def->key)
                    ->label($def->label)
                    ->options(self::optionsListe($def))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where("{$prefixe}->{$def->key}", $data['value'])
                        : $query);
            })
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    private static function optionsListe(CustomFieldDefinition $def): array
    {
        return collect($def->config['options'] ?? [])
            ->map(fn ($o): string => trim((string) $o))
            ->filter()->unique()
            ->mapWithKeys(fn (string $o): array => [$o => $o])
            ->all();
    }

    /**
     * Champ « Liste / Statut » recherchable ET créable : celui qui remplit la
     * ligne peut choisir une option existante OU en ajouter une nouvelle à la
     * volée (façon Monday). La nouvelle option est persistée dans la définition de
     * la colonne — elle devient disponible pour tout le monde.
     */
    private static function selectCreable(string $chemin, CustomFieldDefinition $def): Select
    {
        return Select::make($chemin)
            ->label($def->label)
            ->options(self::optionsListe($def))
            ->searchable()
            ->native(false)
            // Assure un libellé même pour une valeur tout juste créée (non encore
            // rechargée dans les options statiques).
            ->getOptionLabelUsing(fn ($value): ?string => filled($value) ? (string) $value : null)
            ->createOptionForm([
                TextInput::make('valeur')
                    ->label('Nouvelle option')
                    ->placeholder('Saisissez la valeur à ajouter…')
                    ->required()
                    ->maxLength(255),
            ])
            ->createOptionUsing(fn (array $data): string => self::ajouterOption($def, (string) ($data['valeur'] ?? '')));
    }

    /**
     * Ajoute une option à une colonne « Liste / Statut » (sans doublon ni vide) et
     * la persiste. Renvoie la valeur ajoutée (ou vide si invalide) — c'est ce que
     * le select sélectionne après création.
     */
    public static function ajouterOption(CustomFieldDefinition $def, string $valeur): string
    {
        $valeur = trim($valeur);

        if ($valeur === '') {
            return '';
        }

        $options = collect($def->config['options'] ?? [])
            ->push($valeur)
            ->map(fn ($o): string => trim((string) $o))
            ->filter()->unique()->values()->all();

        $def->update(['config' => ['options' => $options]]);

        return $valeur;
    }

    /**
     * Couleur Filament d'une valeur de statut : attribuée de façon déterministe
     * selon la position de l'option dans la liste (palette cyclique), pour un
     * rendu « façon Monday » sans configuration de couleur par option.
     */
    private static function couleurStatut(CustomFieldDefinition $def, ?string $state): string
    {
        if (blank($state)) {
            return 'gray';
        }

        $palette = ['primary', 'success', 'warning', 'danger', 'info', 'gray'];
        $options = array_values(array_keys(self::optionsListe($def)));
        $index = array_search($state, $options, true);

        return $index === false ? 'gray' : $palette[$index % count($palette)];
    }

    /**
     * Utilisateurs assignables : le personnel du CFA courant (relation many-to-many
     * organisations), pour cloisonner le type « Utilisateur ».
     *
     * @return array<int, string>
     */
    private static function optionsUtilisateurs(): array
    {
        $tenantId = Filament::getTenant()?->getKey();

        return User::query()
            ->when($tenantId !== null, fn ($q) => $q->whereHas('organisations', fn ($o) => $o->whereKey($tenantId)))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
