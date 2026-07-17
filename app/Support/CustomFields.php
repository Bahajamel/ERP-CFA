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
 * Colonnes personnalisées d'un CFA (couche « façon Monday ») : le CFA choisit,
 * depuis un bouton « Colonnes personnalisées » ouvrant un modal, les colonnes
 * qu'il ajoute à une table métier existante (Candidats…). Elles apparaissent
 * ensuite automatiquement dans les formulaires et tableaux de l'entité.
 *
 * Valeurs stockées dans la colonne JSONB `custom_fields` de l'entité, adressées
 * via `custom_fields.{clé}`. Cloisonnement par CFA assuré par le global scope de
 * {@see CustomFieldDefinition} — aucune fuite entre organisations.
 */
class CustomFields
{
    /** @return Collection<int, CustomFieldDefinition> */
    public static function definitions(string $entity): Collection
    {
        return CustomFieldDefinition::query()
            ->where('entity', $entity)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /** Rôles autorisés à gérer les colonnes personnalisées. */
    public static function peutGerer(): bool
    {
        return auth()->user()?->hasAnyRole(['Administrateur', 'Direction']) ?? false;
    }

    /**
     * Bouton « Colonnes personnalisées » (à placer dans l'en-tête d'une liste) :
     * ouvre un modal listant les colonnes de l'entité, où on les ajoute / édite /
     * réordonne / supprime. Réservé aux Administrateurs et à la Direction.
     */
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
            ->fillForm(fn (): array => [
                'colonnes' => self::definitions($entity)->map(fn (CustomFieldDefinition $d): array => [
                    'id' => $d->id,
                    'label' => $d->label,
                    'type' => $d->type->value,
                    'options' => $d->config['options'] ?? [],
                    'visible_table' => $d->visible_table,
                ])->all(),
            ])
            ->schema([
                Repeater::make('colonnes')
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
                            ->placeholder('ex : Référence interne, Priorité…')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->label('Type')
                            ->options(CustomFieldType::options())
                            ->default(CustomFieldType::Text->value)
                            ->required()
                            ->live(),
                        TagsInput::make('options')
                            ->label('Options de la liste')
                            ->placeholder('Ajouter une option…')
                            ->visible(fn (Get $get): bool => $get('type') === CustomFieldType::Select->value)
                            ->required(fn (Get $get): bool => $get('type') === CustomFieldType::Select->value)
                            ->columnSpanFull(),
                        Toggle::make('visible_table')
                            ->label('Afficher dans le tableau')
                            ->default(true),
                    ]),
            ])
            ->action(function (array $data, $livewire): void {
                self::synchroniser($entity, $data['colonnes'] ?? []);

                Notification::make()->success()
                    ->title('Colonnes mises à jour')
                    ->body('Vos colonnes personnalisées ont été enregistrées.')
                    ->send();

                if (is_object($livewire) && method_exists($livewire, 'resetTable')) {
                    $livewire->resetTable();
                }
            });
    }

    /**
     * Réconcilie les définitions d'une entité avec les lignes du modal :
     * création des nouvelles, mise à jour des existantes (avec l'ordre), et
     * suppression de celles retirées. La clé technique reste stable une fois créée.
     */
    public static function synchroniser(string $entity, array $lignes): void
    {
        $existantes = self::definitions($entity)->keyBy('id');
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
                $def = CustomFieldDefinition::create($attributs + [
                    'entity' => $entity,
                    'key' => self::cleUnique($entity, $label),
                ]);
                $gardees[] = $def->id;
            }
        }

        // Colonnes retirées du modal → supprimées (leurs valeurs orphelines
        // restent dans custom_fields mais ne sont plus affichées).
        CustomFieldDefinition::query()
            ->where('entity', $entity)
            ->whereNotIn('id', $gardees ?: [0])
            ->delete();
    }

    /** Clé technique unique (par CFA et entité) dérivée du libellé. */
    private static function cleUnique(string $entity, string $label): string
    {
        $base = Str::slug($label, '_') ?: 'colonne';
        $cle = $base;
        $i = 2;

        while (CustomFieldDefinition::query()->where('entity', $entity)->where('key', $cle)->exists()) {
            $cle = $base.'_'.$i;
            $i++;
        }

        return $cle;
    }

    /**
     * Section « Champs personnalisés » à ajouter au formulaire d'une entité.
     * Tableau vide si le CFA n'a défini aucune colonne (aucun encombrement).
     *
     * @return array<int, Section>
     */
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
                ->columns(2)
                ->columnSpanFull()
                ->schema($definitions->map(fn (CustomFieldDefinition $d) => self::champ($d))->all()),
        ];
    }

    /** Colonnes personnalisées (masquables) à ajouter au tableau d'une entité. */
    public static function tableColumns(string $entity): array
    {
        return self::definitions($entity)
            ->map(function (CustomFieldDefinition $def) {
                $chemin = "custom_fields.{$def->key}";

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
            })
            ->all();
    }

    /** Construit le composant de formulaire correspondant au type d'une définition. */
    private static function champ(CustomFieldDefinition $def)
    {
        $chemin = "custom_fields.{$def->key}";

        return match ($def->type) {
            CustomFieldType::Text => TextInput::make($chemin)->label($def->label)->maxLength(255),
            CustomFieldType::Textarea => Textarea::make($chemin)->label($def->label)->rows(3),
            CustomFieldType::Number => TextInput::make($chemin)->label($def->label)->numeric(),
            CustomFieldType::Date => DatePicker::make($chemin)->label($def->label)->displayFormat('d/m/Y')->native(false),
            CustomFieldType::Boolean => Toggle::make($chemin)->label($def->label),
            CustomFieldType::Select => Select::make($chemin)->label($def->label)->options(self::optionsListe($def))->native(false),
        };
    }

    /** @return array<string, string> options d'une liste déroulante personnalisée. */
    private static function optionsListe(CustomFieldDefinition $def): array
    {
        return collect($def->config['options'] ?? [])
            ->map(fn ($o): string => trim((string) $o))
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $o): array => [$o => $o])
            ->all();
    }
}
