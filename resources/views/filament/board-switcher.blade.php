@php
    use App\Support\BoardNavigation;
@endphp

@if (BoardNavigation::doitAfficher())
    @php $boards = BoardNavigation::boards(); @endphp

    {{-- Sélecteur de tables « façon Monday » : bascule d'un board à l'autre
         (Base Candidats ↔ tableaux personnalisés). Injecté via render hook
         (CONTENT_START), au-dessus du titre. Onglet actif = table courante. --}}
    <nav class="mb-boards" aria-label="Sélecteur de tables">
        <ul class="mb-boards-list">
            @foreach ($boards as $board)
                <li class="mb-boards-item">
                    <a
                        href="{{ $board['url'] }}"
                        wire:navigate
                        @class(['mb-boards-tab', 'actif' => $board['actif']])
                        @if ($board['actif']) aria-current="page" @endif
                    >
                        @svg($board['icon'], 'mb-boards-ico')
                        <span class="mb-boards-label">{{ $board['label'] }}</span>
                    </a>
                </li>
            @endforeach

            @if (BoardNavigation::peutCreer())
                <li class="mb-boards-item">
                    <a href="{{ BoardNavigation::urlCreation() }}" wire:navigate class="mb-boards-tab mb-boards-new" title="Créer un tableau">
                        @svg('heroicon-o-plus', 'mb-boards-ico')
                        <span class="mb-boards-label">Nouveau tableau</span>
                    </a>
                </li>
            @endif
        </ul>
    </nav>

    <style>
        /* Barre d'onglets fine, fond blanc, pleine largeur, trait actif en bas.
           Onglet actif = primaire (indigo) + graisse + trait (jamais la couleur
           seule). Scroll horizontal sur mobile, libellés jamais coupés. */
        .mb-boards { background: #fff; border-bottom: 1px solid #eef0f4; margin: 0 0 1.25rem; }
        .mb-boards-list {
            list-style: none; margin: 0; padding: 0;
            display: flex; align-items: stretch; gap: .25rem;
            min-height: 3.25rem; overflow-x: auto; scrollbar-width: none;
        }
        .mb-boards-list::-webkit-scrollbar { display: none; }
        .mb-boards-item { display: flex; }
        .mb-boards-tab {
            display: inline-flex; align-items: center; gap: .5rem; flex: 0 0 auto;
            padding: 0 1rem; min-height: 3.25rem;
            color: #475569; font-weight: 500; font-size: .95rem;
            white-space: nowrap; text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: color .12s ease, border-color .12s ease;
        }
        .mb-boards-ico { width: 1.1rem; height: 1.1rem; color: #94a3b8; flex: 0 0 auto; }
        .mb-boards-tab:hover { color: #4f46e5; }
        .mb-boards-tab:hover .mb-boards-ico { color: #4f46e5; }
        .mb-boards-tab.actif { color: #4f46e5; font-weight: 700; border-bottom-color: #4f46e5; }
        .mb-boards-tab.actif .mb-boards-ico { color: #4f46e5; }
        /* « Nouveau tableau » : discret, style pointillé pour le distinguer. */
        .mb-boards-new { color: #64748b; }
        .mb-boards-new .mb-boards-label { border-bottom: 1px dashed currentColor; }
        .mb-boards-tab:focus-visible { outline: 2px solid #4f46e5; outline-offset: -4px; border-radius: .375rem; }

        /* Mode sombre (thème par défaut de l'app). */
        .dark .mb-boards { background: #0f172a; border-bottom-color: rgba(148, 163, 184, .18); }
        .dark .mb-boards-tab { color: #cbd5e1; }
        .dark .mb-boards-ico { color: #94a3b8; }
        .dark .mb-boards-tab:hover, .dark .mb-boards-tab.actif { color: #a5b4fc; }
        .dark .mb-boards-tab:hover .mb-boards-ico, .dark .mb-boards-tab.actif .mb-boards-ico { color: #a5b4fc; }
        .dark .mb-boards-tab.actif { border-bottom-color: #6366f1; }

        @media (max-width: 1024px) { .mb-boards-tab { padding: 0 .75rem; } }
    </style>
@endif
