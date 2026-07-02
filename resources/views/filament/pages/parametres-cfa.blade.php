<x-filament-panels::page>
    <form wire:submit="save" class="fi-form">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Enregistrer
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
