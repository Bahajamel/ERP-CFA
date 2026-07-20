<?php

namespace App\Http\Controllers;

use App\Enums\CustomFieldType;
use App\Models\CustomFieldDefinition;
use App\Models\CustomRecord;
use App\Models\CustomTable;
use App\Support\CustomFields;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Formulaire public de candidature RATTACHÉ À UN TABLEAU personnalisé (sans accès
 * à l'ERP) : un jeton imprévisible ouvre un formulaire bâti dynamiquement sur les
 * colonnes du tableau ; à l'envoi, une LIGNE est créée dans ce tableau.
 *
 * Chaque tableau a donc son propre lien de candidature. Cloisonné : la ligne naît
 * dans le CFA propriétaire du tableau. Défense en profondeur : jeton unique, table
 * active seulement, honeypot, throttle (route), validation par type, strip_tags.
 */
class PublicCustomTableController extends Controller
{
    public function show(string $token): View
    {
        $table = $this->resoudre($token);

        return view('public.tableau-candidature', [
            'table' => $table,
            'colonnes' => $this->colonnesPubliques($table),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        // Honeypot : un bot remplit ce champ caché → on ignore silencieusement.
        if (filled($request->input('website'))) {
            return redirect()->route('tableau.candidature.merci');
        }

        $table = $this->resoudre($token);
        $colonnes = $this->colonnesPubliques($table);

        $data = $this->valider($request, $colonnes);

        CustomRecord::create([
            // Hors contexte CFA (formulaire public) : on rattache explicitement la
            // ligne au CFA propriétaire du tableau.
            'organisation_id' => $table->organisation_id,
            'custom_table_id' => $table->id,
            'data' => $data,
        ]);

        $this->notifier($table);

        return redirect()->route('tableau.candidature.merci');
    }

    /**
     * Tableau correspondant au jeton, ACTIF et dont le formulaire public est
     * ACTIVÉ (public → sans global scope CFA).
     */
    private function resoudre(string $token): CustomTable
    {
        return CustomTable::withoutGlobalScopes()
            ->where('public_token', $token)
            ->where('is_active', true)
            ->where('public_enabled', true)
            ->firstOrFail();
    }

    /**
     * Prévient le créateur du tableau qu'une nouvelle entrée est arrivée via le
     * lien public (notification en base, visible dans la cloche de l'ERP).
     */
    private function notifier(CustomTable $table): void
    {
        $destinataire = $table->creePar;

        if ($destinataire === null) {
            return;
        }

        Notification::make()
            ->title('Nouvelle entrée : '.$table->name)
            ->body('Une personne vient de remplir le formulaire public de ce tableau.')
            ->icon('heroicon-o-inbox-arrow-down')
            ->success()
            ->sendToDatabase($destinataire);
    }

    /**
     * Colonnes exposées publiquement : on masque le type « Utilisateur assigné »
     * (attribution interne, hors formulaire public).
     *
     * @return Collection<int, CustomFieldDefinition>
     */
    private function colonnesPubliques(CustomTable $table): Collection
    {
        return $table->colonnes
            ->reject(fn (CustomFieldDefinition $def): bool => $def->type === CustomFieldType::Utilisateur)
            ->values();
    }

    /**
     * Valide la saisie selon le TYPE de chaque colonne (+ obligatoire), puis
     * reconstruit le JSONB { clé: valeur } assaini (strip_tags sur le texte).
     *
     * @param  Collection<int, CustomFieldDefinition>  $colonnes
     * @return array<string, mixed>
     */
    private function valider(Request $request, Collection $colonnes): array
    {
        $rules = [];
        $attributs = [];

        foreach ($colonnes as $def) {
            $champ = "champs.{$def->key}";
            $rules[$champ] = array_merge(
                [$def->is_required ? 'required' : 'nullable'],
                match ($def->type) {
                    CustomFieldType::Number, CustomFieldType::Montant, CustomFieldType::Pourcentage => ['numeric'],
                    CustomFieldType::Date => ['date'],
                    CustomFieldType::Heure => ['date_format:H:i'],
                    CustomFieldType::Boolean => ['boolean'],
                    CustomFieldType::Textarea => ['string', 'max:5000'],
                    CustomFieldType::Select, CustomFieldType::Statut => ['string', Rule::in($this->options($def))],
                    CustomFieldType::MultiSelect => ['array'],
                    default => ['string', 'max:255'],
                },
                // Mêmes règles que dans l'ERP (longueur max, bornes, format).
                CustomFields::reglesValidation($def),
            );

            // Multi-sélection : chaque valeur doit appartenir aux options.
            if ($def->type === CustomFieldType::MultiSelect) {
                $rules["{$champ}.*"] = ['string', Rule::in($this->options($def))];
            }

            $attributs[$champ] = $def->label;
        }

        $valide = validator($request->all(), $rules, [], $attributs)->validate();

        $data = [];
        foreach ($colonnes as $def) {
            $valeur = data_get($valide, "champs.{$def->key}");

            if ($def->type === CustomFieldType::Boolean) {
                $data[$def->key] = (bool) $valeur;
            } elseif (is_array($valeur)) {
                // Multi-sélection : liste de valeurs assainies.
                $propres = collect($valeur)->map(fn ($v): string => trim(strip_tags((string) $v)))->filter()->values()->all();
                if ($propres !== []) {
                    $data[$def->key] = $propres;
                }
            } elseif (is_string($valeur)) {
                $propre = trim(strip_tags($valeur));
                if ($propre !== '') {
                    $data[$def->key] = $propre;
                }
            } elseif ($valeur !== null) {
                $data[$def->key] = $valeur;
            }
        }

        return $data;
    }

    /** @return list<string> */
    private function options(CustomFieldDefinition $def): array
    {
        return collect($def->config['options'] ?? [])
            ->map(fn ($o): string => trim((string) $o))
            ->filter()
            ->values()
            ->all();
    }
}
