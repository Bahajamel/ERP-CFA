<?php

namespace App\Support;

use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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

    public static function gererAction(string $entity, string $labelEntite): Action
    {
        return Action::make('colonnesPersonnalisees_'.$entity)
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
                    ->visible(fn (Get $get): bool => $get('type') === CustomFieldType::Select->value)
                    ->required(fn (Get $get): bool => $get('type') === CustomFieldType::Select->value)
                    ->columnSpanFull(),
                Toggle::make('visible_table')
                    ->label('Afficher dans le tableau')
                    ->default(true),
            ]);
    }

    /** Transforme des définitions en lignes de repeater (pour préremplir un modal). */
    public static function lignesDepuis(Collection $definitions): array
    {
        return $definitions->map(fn (CustomFieldDefinition $d): array => [
            'id' => $d->id,
            'label' => $d->label,
            'type' => $d->type->value,
            'options' => $d->config['options'] ?? [],
            'visible_table' => $d->visible_table,
        ])->all();
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
            $config = $type === CustomFieldType::Select->value
                ? ['options' => collect($ligne['options'] ?? [])->map(fn ($o) => trim((string) $o))->filter()->unique()->values()->all()]
                : null;

            $attributs = [
                'label' => $label,
                'type' => $type,
                'config' => $config,
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

        return match ($def->type) {
            CustomFieldType::Text => TextInput::make($chemin)->label($def->label)->maxLength(255),
            CustomFieldType::Textarea => Textarea::make($chemin)->label($def->label)->rows(3),
            CustomFieldType::Number => TextInput::make($chemin)->label($def->label)->numeric(),
            CustomFieldType::Date => DatePicker::make($chemin)->label($def->label)->displayFormat('d/m/Y')->native(false),
            CustomFieldType::Boolean => Toggle::make($chemin)->label($def->label),
            CustomFieldType::Select => Select::make($chemin)->label($def->label)->options(self::optionsListe($def))->native(false),
        };
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
}
