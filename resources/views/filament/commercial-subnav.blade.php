@php
    use App\Support\CommercialNavigation as CommercialNav;
@endphp

@if (CommercialNav::doitAfficher())
    {{-- Barre contextuelle « Commercial » : 3 accès rapides (Candidats, Entreprises,
         Offres). Injectée via render hook (CONTENT_START) au-dessus du titre, sur
         toutes les pages du groupe Commercial. Onglet actif déterminé par la route. --}}
    <nav class="cfa-subnav" aria-label="Navigation Commercial">
        <ul class="cfa-subnav-list">
            @foreach (CommercialNav::tabs() as $onglet)
                @php $actif = CommercialNav::estActif($onglet['resource']); @endphp
                <li class="cfa-subnav-item">
                    <a
                        href="{{ CommercialNav::url($onglet['resource']) }}"
                        wire:navigate
                        @class(['cfa-subnav-link', 'actif' => $actif])
                        @if ($actif) aria-current="page" @endif
                    >
                        @svg($onglet['icon'], 'cfa-subnav-ico')
                        <span class="cfa-subnav-label">{{ $onglet['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <style>
        /* Barre fine, fond blanc, pleine largeur du contenu, trait actif en bas,
           sans ombre ni carte flottante. Reprend la primaire du thème (indigo)
           pour l'onglet actif. Rendue en flux normal (alignée au titre, comme le
           bouton « Retour ») pour ne casser ni le layout ni le responsive. */
        .cfa-subnav {
            background: #fff;
            border-bottom: 1px solid #eef0f4;
            margin: 0 0 1.25rem;
        }
        .cfa-subnav-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: stretch;
            gap: .25rem;
            min-height: 4rem;              /* ~64px */
            overflow-x: auto;              /* mobile : scroll horizontal si besoin */
            scrollbar-width: none;
        }
        .cfa-subnav-list::-webkit-scrollbar { display: none; }
        .cfa-subnav-item { display: flex; }
        .cfa-subnav-link {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            flex: 0 0 auto;                /* pas de retour à la ligne des onglets */
            padding: 0 1rem;
            min-height: 4rem;              /* zone cliquable généreuse */
            color: #475569;                /* texte gris foncé (inactif) */
            font-weight: 500;
            font-size: .95rem;
            white-space: nowrap;           /* libellés jamais coupés */
            text-decoration: none;
            border-bottom: 3px solid transparent;   /* trait actif placé en bas */
            transition: color .12s ease, border-color .12s ease;
        }
        .cfa-subnav-ico {
            width: 1.25rem;
            height: 1.25rem;
            color: #94a3b8;                /* icône grise (inactif) */
            flex: 0 0 auto;
        }
        .cfa-subnav-link:hover { color: #4f46e5; }
        .cfa-subnav-link:hover .cfa-subnav-ico { color: #4f46e5; }
        /* Onglet actif : couleur primaire + graisse + trait inférieur (jamais la
           couleur seule → l'état reste perceptible sans distinction chromatique). */
        .cfa-subnav-link.actif {
            color: #4f46e5;
            font-weight: 700;
            border-bottom-color: #4f46e5;
        }
        .cfa-subnav-link.actif .cfa-subnav-ico { color: #4f46e5; }
        /* Focus clavier clairement visible. */
        .cfa-subnav-link:focus-visible {
            outline: 2px solid #4f46e5;
            outline-offset: -4px;
            border-radius: .375rem;
        }

        /* Mode sombre (thème par défaut de l'app) : surface sombre cohérente. */
        .dark .cfa-subnav { background: #0f172a; border-bottom-color: rgba(148, 163, 184, .18); }
        .dark .cfa-subnav-link { color: #cbd5e1; }
        .dark .cfa-subnav-ico { color: #94a3b8; }
        .dark .cfa-subnav-link:hover,
        .dark .cfa-subnav-link.actif { color: #a5b4fc; }
        .dark .cfa-subnav-link:hover .cfa-subnav-ico,
        .dark .cfa-subnav-link.actif .cfa-subnav-ico { color: #a5b4fc; }
        .dark .cfa-subnav-link.actif { border-bottom-color: #6366f1; }
        .dark .cfa-subnav-link:focus-visible { outline-color: #a5b4fc; }

        /* Tablette : espacements légèrement réduits. */
        @media (max-width: 1024px) {
            .cfa-subnav-link { padding: 0 .75rem; }
        }
    </style>
@endif
