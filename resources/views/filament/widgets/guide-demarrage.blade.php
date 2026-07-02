<x-filament::section>
    <x-slot name="heading">Bienvenue 👋 — le cycle de vie d'un apprenant</x-slot>
    <x-slot name="description">
        Chaque carte correspond à un module du menu. Suivez-les dans l'ordre.
    </x-slot>

    <style>
        .fi-guide-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(190px, 1fr)); gap:.6rem; }
        .fi-guide-card {
            position:relative; display:flex; flex-direction:column; gap:.45rem;
            padding:1rem .9rem; border-radius:1rem; text-decoration:none; color:inherit;
            border:1px solid rgb(var(--gray-200, 229 231 235));
            background:rgb(var(--gray-50, 249 250 251));
            transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }
        .dark .fi-guide-card { border-color:rgba(255,255,255,.10); background:rgba(255,255,255,.03); }
        .fi-guide-card--link:hover {
            transform:translateY(-3px);
            box-shadow:0 12px 22px -12px rgba(0,0,0,.35);
            border-color:rgb(var(--primary-500, 16 185 129));
        }
        .fi-guide-card--locked { opacity:.5; }
        .fi-guide-num {
            position:absolute; top:.7rem; right:.75rem; width:1.4rem; height:1.4rem; border-radius:9999px;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:.72rem; font-weight:700; color:rgb(var(--primary-600, 5 150 105));
            background:rgb(var(--primary-500, 16 185 129) / .14);
        }
        .fi-guide-icon {
            flex:none; width:2.5rem; height:2.5rem; border-radius:.75rem;
            display:inline-flex; align-items:center; justify-content:center;
            color:#fff; background:linear-gradient(135deg, rgb(var(--primary-500, 16 185 129)), rgb(var(--primary-600, 5 150 105)));
            box-shadow:0 4px 10px -3px rgb(var(--primary-500, 16 185 129) / .5);
        }
        .fi-guide-title { font-weight:600; font-size:.95rem; }
        .fi-guide-desc { font-size:.78rem; line-height:1.35; color:rgb(var(--gray-500, 107 114 128)); }
        .dark .fi-guide-desc { color:rgb(var(--gray-400, 156 163 175)); }
        .fi-guide-lock { display:inline-flex; align-items:center; gap:.25rem; margin-top:.1rem; font-size:.68rem; color:rgb(var(--gray-400, 156 163 175)); }
    </style>

    <div class="fi-guide-grid">
        @foreach ($this->steps() as $step)
            @php $tag = $step['url'] ? 'a' : 'div'; @endphp
            <{{ $tag }}
                @if ($step['url']) href="{{ $step['url'] }}" @endif
                class="fi-guide-card {{ $step['url'] ? 'fi-guide-card--link' : 'fi-guide-card--locked' }}"
            >
                <span class="fi-guide-num">{{ $step['num'] }}</span>
                <span class="fi-guide-icon">@svg($step['icon'], ['style' => 'width:1.35rem;height:1.35rem;'])</span>
                <span class="fi-guide-title">{{ $step['title'] }}</span>
                <span class="fi-guide-desc">{{ $step['role'] }}</span>
                @if (! $step['url'])
                    <span class="fi-guide-lock">@svg('heroicon-m-lock-closed', ['style' => 'width:.8rem;height:.8rem;']) accès restreint</span>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>

    @if ($this->nouvelApprenantUrl())
        <div style="margin-top:1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <x-filament::button tag="a" :href="$this->nouvelApprenantUrl()" icon="heroicon-m-plus" size="lg">
                Ajouter un apprenant
            </x-filament::button>
            <span class="fi-guide-desc">
                Commencez ici : créez la fiche du candidat, puis déroulez les étapes.
            </span>
        </div>
    @endif
</x-filament::section>
