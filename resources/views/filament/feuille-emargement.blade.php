<div>
    <style>
        .fe-liste { display: flex; flex-direction: column; gap: .35rem; margin-bottom: .25rem; }
        .fe-item {
            display: flex; align-items: center; justify-content: space-between; gap: .75rem;
            padding: .45rem .65rem; border: 1px solid rgb(229 231 235); border-radius: .5rem;
            font-size: .8rem; color: rgb(31 41 55); text-decoration: none; background: rgb(249 250 251);
        }
        .fe-item:hover { border-color: rgb(165 180 252); }
        .dark .fe-item { border-color: rgb(55 65 81); background: rgb(31 41 55); color: rgb(229 231 235); }
        .fe-version {
            font-size: .65rem; font-weight: 700; padding: .1rem .45rem; border-radius: 999px;
            background: rgb(224 231 255); color: rgb(67 56 202); flex: none;
        }
        .dark .fe-version { background: rgb(49 46 129 / .5); color: rgb(165 180 252); }
        .fe-meta { font-size: .7rem; color: rgb(156 163 175); flex: none; }
        .fe-vide { font-size: .78rem; color: rgb(156 163 175); font-style: italic; margin-bottom: .25rem; display: block; }
    </style>

    @if ($feuilles->isEmpty())
        <span class="fe-vide">Aucune feuille d'émargement déposée pour cette séance.</span>
    @else
        <div class="fe-liste">
            @foreach ($feuilles as $feuille)
                <a class="fe-item" href="{{ \App\Support\SecureMedia::pour($feuille, 'fichier') }}" target="_blank" rel="noopener">
                    <span class="fe-version">v{{ $feuille->version }}</span>
                    <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        📄 {{ $feuille->nom_fichier }}
                    </span>
                    <span class="fe-meta">
                        {{ $feuille->created_at->format('d/m/Y H:i') }}
                        @if ($feuille->uploadedBy) · {{ $feuille->uploadedBy->name }} @endif
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
