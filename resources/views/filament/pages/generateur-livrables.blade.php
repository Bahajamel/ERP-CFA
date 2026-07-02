<x-filament-panels::page>
    @unless ($this->serviceConfigure())
        <div style="padding:0.9rem 1rem;border-radius:0.5rem;background:rgba(217,119,6,0.1);color:#92400e;">
            ⚠️ Le service de génération n'est pas configuré (<code>LIVRETRS_URL</code>). Lancez le service
            LivretRS puis renseignez l'URL. En attendant, l'import manuel d'un pack ZIP reste disponible
            depuis un contrat.
        </div>
    @endunless

    <form wire:submit="generer" class="fi-form">
        {{ $this->form }}

        <div class="mt-4 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-sparkles" :disabled="! $this->serviceConfigure()">
                Générer les livrables
            </x-filament::button>
        </div>
    </form>

    {{ $this->table }}
</x-filament-panels::page>
