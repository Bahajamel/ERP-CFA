<?php

namespace App\Filament\Resources\CustomTables\Pages;

use App\Filament\Resources\CustomTables\CustomTableResource;
use App\Support\BoardNavigation;
use App\Support\CustomFields;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomTable extends CreateRecord
{
    protected static string $resource = CustomTableResource::class;

    protected function getRedirectUrl(): string
    {
        // Après création : on ouvre le BOARD (les lignes) pour saisir directement.
        return $this->getResource()::getUrl('board', ['record' => $this->getRecord()]);
    }

    /**
     * Rattache la table au module d'où vient la création (?context=candidate…),
     * si le formulaire ne l'a pas déjà fixé — c'est ce qui permet « plusieurs
     * tableaux par module ». Contexte inconnu ignoré (table autonome).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['context'] ?? null)) {
            $contexte = request('context');

            if (is_string($contexte) && array_key_exists($contexte, BoardNavigation::contextes())) {
                $data['context'] = $contexte;
            }
        }

        return $data;
    }

    /** Synchronise les colonnes définies dans le repeater vers le nouveau tableau. */
    protected function afterCreate(): void
    {
        CustomFields::synchroniserTableau($this->getRecord()->id, $this->data['colonnes'] ?? []);
    }
}
