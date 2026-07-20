{{-- Barre « colonnes personnalisées » (façon tableaux personnalisés) :
     « Configurer » (renommer / supprimer des colonnes) + « Ajouter une colonne ».
     Requiert que la page expose ajouterColonneAction / renommerColonnesAction /
     supprimerColonneAction. Rendu réservé aux profils qui peuvent gérer. --}}
@if (\App\Support\CustomFields::peutGerer())
    <div style="display:flex;flex-wrap:wrap;gap:.6rem;margin:0 0 .75rem;">
        {{-- Configurer : regroupe le renommage et la suppression de colonnes. --}}
        <x-filament::dropdown placement="bottom-start">
            <x-slot name="trigger">
                <x-filament::button color="gray" icon="heroicon-o-cog-6-tooth">
                    Configurer
                </x-filament::button>
            </x-slot>

            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item
                    icon="heroicon-o-pencil-square"
                    wire:click="mountAction('renommerColonnes')"
                >
                    Renommer les colonnes
                </x-filament::dropdown.list.item>

                <x-filament::dropdown.list.item
                    icon="heroicon-o-trash"
                    color="danger"
                    wire:click="mountAction('supprimerColonne')"
                >
                    Supprimer une colonne
                </x-filament::dropdown.list.item>
            </x-filament::dropdown.list>
        </x-filament::dropdown>

        {{ $this->ajouterColonneAction }}
    </div>
@endif
