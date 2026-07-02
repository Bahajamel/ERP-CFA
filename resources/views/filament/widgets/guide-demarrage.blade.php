<x-filament::section>
    <x-slot name="heading">Bienvenue 👋 — le cycle de vie d'un apprenant</x-slot>
    <x-slot name="description">
        Chaque étape correspond à un module du menu. Suivez le parcours, de gauche à droite.
    </x-slot>

    @php
        // Une couleur par étape : dégradé de teintes qui progresse le long du parcours.
        $palette = [
            ['#6366f1', '#4f46e5'], // indigo
            ['#3b82f6', '#2563eb'], // bleu
            ['#06b6d4', '#0891b2'], // cyan
            ['#14b8a6', '#0d9488'], // teal
            ['#22c55e', '#16a34a'], // vert
            ['#f59e0b', '#d97706'], // ambre
            ['#f43f5e', '#e11d48'], // rose
        ];
    @endphp

    <style>
        .fi-guide-row { display:flex; flex-wrap:wrap; gap:.55rem; }
        .fi-guide-step {
            flex:1 1 180px; min-width:180px; position:relative;
            display:flex; flex-direction:column; gap:.4rem;
            padding:.9rem .8rem; border-radius:.9rem; text-decoration:none; color:inherit;
            background:rgb(var(--gray-50, 249 250 251));
            border:1px solid rgb(var(--gray-200, 229 231 235)); border-top-width:3px;
            transition:transform .15s ease, box-shadow .15s ease;
        }
        .dark .fi-guide-step { background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.10); }
        .fi-guide-step--link:hover { transform:translateY(-3px); box-shadow:0 12px 22px -12px rgba(0,0,0,.4); }
        .fi-guide-step--locked { opacity:.5; }
        .fi-guide-ico {
            flex:none; width:2.3rem; height:2.3rem; border-radius:.7rem;
            display:inline-flex; align-items:center; justify-content:center; color:#fff;
            box-shadow:0 4px 10px -3px rgba(0,0,0,.35);
        }
        .fi-guide-num {
            position:absolute; top:.6rem; right:.65rem; width:1.3rem; height:1.3rem; border-radius:9999px;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:.68rem; font-weight:700; color:#fff;
        }
        .fi-guide-t { font-weight:600; font-size:.9rem; }
        .fi-guide-d { font-size:.72rem; line-height:1.3; color:rgb(var(--gray-500, 107 114 128)); }
        .dark .fi-guide-d { color:rgb(var(--gray-400, 156 163 175)); }
        .fi-guide-lock { display:inline-flex; align-items:center; gap:.25rem; margin-top:.1rem; font-size:.66rem; color:rgb(var(--gray-400, 156 163 175)); }
    </style>

    <div class="fi-guide-row">
        @foreach ($this->steps() as $step)
            @php
                [$c0, $c1] = $palette[$loop->index % count($palette)];
                $tag = $step['url'] ? 'a' : 'div';
            @endphp
            <{{ $tag }}
                @if ($step['url']) href="{{ $step['url'] }}" @endif
                class="fi-guide-step {{ $step['url'] ? 'fi-guide-step--link' : 'fi-guide-step--locked' }}"
                style="border-top-color:{{ $c0 }};"
            >
                <span class="fi-guide-num" style="background:{{ $c1 }};">{{ $step['num'] }}</span>
                <span class="fi-guide-ico" style="background:linear-gradient(135deg,{{ $c0 }},{{ $c1 }});">
                    @svg($step['icon'], ['style' => 'width:1.3rem;height:1.3rem;'])
                </span>
                <span class="fi-guide-t">{{ $step['title'] }}</span>
                <span class="fi-guide-d">{{ $step['role'] }}</span>
                @if (! $step['url'])
                    <span class="fi-guide-lock">@svg('heroicon-m-lock-closed', ['style' => 'width:.75rem;height:.75rem;']) accès restreint</span>
                @endif
            </{{ $tag }}>
        @endforeach
    </div>

    @if ($this->nouvelApprenantUrl())
        <div style="margin-top:1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <x-filament::button tag="a" :href="$this->nouvelApprenantUrl()" icon="heroicon-m-plus" size="lg">
                Ajouter un apprenant
            </x-filament::button>
            <span class="fi-guide-d">
                Commencez ici : créez la fiche du candidat, puis déroulez les étapes.
            </span>
        </div>
    @endif
</x-filament::section>
