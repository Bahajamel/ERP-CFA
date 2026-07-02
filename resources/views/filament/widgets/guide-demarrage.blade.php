<x-filament::section>
    <x-slot name="heading">Bienvenue 👋 — le cycle de vie d'un apprenant</x-slot>
    <x-slot name="description">
        Chaque étape correspond à un module du menu. Suivez le fil, de gauche à droite.
    </x-slot>

    <style>
        .fi-guide-flow { display:flex; flex-wrap:nowrap; align-items:stretch; gap:.35rem; overflow-x:auto; padding:.25rem .1rem 1rem; }
        .fi-guide-flow::-webkit-scrollbar { height:6px; }
        .fi-guide-flow::-webkit-scrollbar-thumb { background:rgb(var(--gray-300, 209 213 219)); border-radius:9999px; }
        .fi-guide-step {
            position:relative; flex:0 0 190px; display:flex; flex-direction:column; gap:.55rem;
            padding:1rem .9rem; border-radius:1rem; text-decoration:none; color:inherit;
            border:1px solid rgb(var(--gray-200, 229 231 235));
            background:rgb(var(--gray-50, 249 250 251));
            transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }
        .dark .fi-guide-step { border-color:rgba(255,255,255,.10); background:rgba(255,255,255,.03); }
        .fi-guide-step--link:hover {
            transform:translateY(-3px);
            box-shadow:0 12px 22px -12px rgba(0,0,0,.35);
            border-color:rgb(var(--primary-500, 16 185 129));
        }
        .fi-guide-step--locked { opacity:.5; }
        .fi-guide-num {
            position:absolute; top:.6rem; right:.7rem; width:1.35rem; height:1.35rem; border-radius:9999px;
            display:inline-flex; align-items:center; justify-content:center;
            font-size:.7rem; font-weight:700; color:rgb(var(--primary-600, 5 150 105));
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
        .fi-guide-lock { display:inline-flex; align-items:center; gap:.25rem; margin-top:.15rem; font-size:.68rem; color:rgb(var(--gray-400, 156 163 175)); }
        .fi-guide-arrow { flex:none; align-self:center; color:rgb(var(--gray-300, 209 213 219)); }
        .dark .fi-guide-arrow { color:rgba(255,255,255,.18); }
    </style>

    <div class="fi-guide-flow">
        @foreach ($this->steps() as $step)
            @php $tag = $step['url'] ? 'a' : 'div'; @endphp
            <{{ $tag }}
                @if ($step['url']) href="{{ $step['url'] }}" @endif
                class="fi-guide-step {{ $step['url'] ? 'fi-guide-step--link' : 'fi-guide-step--locked' }}"
            >
                <span class="fi-guide-num">{{ $step['num'] }}</span>
                <span class="fi-guide-icon">@svg($step['icon'], ['style' => 'width:1.35rem;height:1.35rem;'])</span>
                <span class="fi-guide-title">{{ $step['title'] }}</span>
                <span class="fi-guide-desc">{{ $step['role'] }}</span>
                @if (! $step['url'])
                    <span class="fi-guide-lock">@svg('heroicon-m-lock-closed', ['style' => 'width:.8rem;height:.8rem;']) accès restreint</span>
                @endif
            </{{ $tag }}>

            @unless ($loop->last)
                <span class="fi-guide-arrow">@svg('heroicon-m-chevron-right', ['style' => 'width:1.1rem;height:1.1rem;'])</span>
            @endunless
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
