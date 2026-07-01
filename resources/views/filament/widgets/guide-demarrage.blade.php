<x-filament::section>
    <x-slot name="heading">Bienvenue 👋 — le cycle de vie d'un apprenant</x-slot>
    <x-slot name="description">
        Chaque étape correspond à un module du menu. Suivez-les dans l'ordre, de gauche à droite.
    </x-slot>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.75rem;">
        @foreach ($this->steps() as $step)
            @php $tag = $step['url'] ? 'a' : 'div'; @endphp
            <{{ $tag }}
                @if ($step['url']) href="{{ $step['url'] }}" @endif
                style="display:flex;flex-direction:column;gap:.4rem;padding:.9rem;border:1px solid rgba(128,128,128,.25);border-radius:.75rem;text-decoration:none;color:inherit;{{ $step['url'] ? '' : 'opacity:.55;' }}"
            >
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <span style="flex:none;display:inline-flex;align-items:center;justify-content:center;width:1.6rem;height:1.6rem;border-radius:9999px;background:rgb(16,185,129);color:#fff;font-size:.8rem;font-weight:700;">{{ $step['num'] }}</span>
                    <span style="font-weight:600;">{{ $step['title'] }}</span>
                    @if (! $step['url'])
                        <span style="font-size:.7rem;color:rgba(128,128,128,.9);">(accès restreint)</span>
                    @endif
                </div>
                <span style="font-size:.8rem;color:rgba(128,128,128,.95);line-height:1.3;">{{ $step['role'] }}</span>
            </{{ $tag }}>
        @endforeach
    </div>

    @if ($this->nouvelApprenantUrl())
        <div style="margin-top:1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <x-filament::button tag="a" :href="$this->nouvelApprenantUrl()" icon="heroicon-m-plus" size="lg">
                Ajouter un apprenant
            </x-filament::button>
            <span style="font-size:.8rem;color:rgba(128,128,128,.95);">
                Commencez ici : créez la fiche du candidat, puis déroulez les étapes.
            </span>
        </div>
    @endif
</x-filament::section>
