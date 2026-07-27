@php
    // Palette de dégradés tournants pour les cartes de matières (rythme visuel).
    $degrades = [
        ['#6366f1', '#8b5cf6'], ['#0ea5e9', '#6366f1'], ['#10b981', '#0ea5e9'],
        ['#f59e0b', '#f97316'], ['#ec4899', '#8b5cf6'], ['#14b8a6', '#10b981'],
    ];
@endphp

<div class="fc">
    <style>
        .fc { --fc-ink:#0f172a; --fc-sub:#64748b; --fc-card:#ffffff; --fc-line:#e2e8f0; --fc-soft:#f8fafc; }
        .dark .fc { --fc-ink:#f1f5f9; --fc-sub:#94a3b8; --fc-card:#0f172a; --fc-line:rgba(255,255,255,.08); --fc-soft:rgba(255,255,255,.03); }

        /* ---------- Hero ---------- */
        .fc-hero {
            position: relative; overflow: hidden; border-radius: 1.25rem; padding: 1.75rem 1.75rem 1.5rem;
            background: linear-gradient(120deg,#4f46e5 0%,#7c3aed 55%,#9333ea 100%);
            box-shadow: 0 20px 40px -20px rgba(79,70,229,.6);
        }
        .fc-hero::after {
            content:""; position:absolute; inset:0; opacity:.5; pointer-events:none;
            background:
                radial-gradient(120px 120px at 88% 12%, rgba(255,255,255,.28), transparent 70%),
                radial-gradient(180px 180px at 12% 108%, rgba(255,255,255,.16), transparent 70%);
        }
        .fc-hero-top { position:relative; display:flex; gap:1rem; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; }
        .fc-emblem {
            width:3.25rem; height:3.25rem; flex:none; border-radius:.9rem; display:flex; align-items:center; justify-content:center;
            background:rgba(255,255,255,.16); backdrop-filter:blur(6px); box-shadow: inset 0 0 0 1px rgba(255,255,255,.25);
        }
        .fc-emblem svg { width:1.75rem; height:1.75rem; color:#fff; }
        .fc-title { display:flex; gap:1rem; align-items:flex-start; }
        .fc-eyebrow { font-size:.68rem; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:rgba(255,255,255,.72); }
        .fc-name { margin-top:.15rem; font-size:1.55rem; line-height:1.15; font-weight:800; color:#fff; max-width:34ch; }
        .fc-tags { position:relative; display:flex; gap:.4rem; flex-wrap:wrap; }
        .fc-chip {
            display:inline-flex; align-items:center; gap:.35rem; padding:.3rem .7rem; border-radius:999px;
            font-size:.72rem; font-weight:700; color:#fff; background:rgba(255,255,255,.16);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.22);
        }
        .fc-chip--ok  { background:rgba(16,185,129,.9); box-shadow:none; }
        .fc-chip--off { background:rgba(244,63,94,.9); box-shadow:none; }
        .fc-chip i { width:.5rem; height:.5rem; border-radius:999px; background:#fff; display:inline-block; }

        /* ---------- Stats ---------- */
        .fc-stats { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.75rem; margin-top:1rem; }
        @media (max-width:820px){ .fc-stats{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
        .fc-stat {
            display:flex; align-items:center; gap:.75rem; padding:.85rem 1rem; border-radius:.9rem;
            background:var(--fc-card); border:1px solid var(--fc-line);
        }
        .fc-stat-ic { width:2.4rem; height:2.4rem; flex:none; border-radius:.7rem; display:flex; align-items:center; justify-content:center; }
        .fc-stat-ic svg { width:1.25rem; height:1.25rem; }
        .fc-stat-ic--a { background:rgba(99,102,241,.12); color:#6366f1; }
        .fc-stat-ic--b { background:rgba(14,165,233,.12); color:#0ea5e9; }
        .fc-stat-ic--c { background:rgba(16,185,129,.12); color:#10b981; }
        .fc-stat-ic--d { background:rgba(245,158,11,.14); color:#f59e0b; }
        .fc-stat-v { font-size:1.15rem; font-weight:800; color:var(--fc-ink); line-height:1; }
        .fc-stat-l { font-size:.72rem; color:var(--fc-sub); margin-top:.2rem; }

        /* ---------- Programme ---------- */
        .fc-section { margin-top:1.75rem; }
        .fc-sec-head { display:flex; align-items:center; gap:.6rem; margin-bottom:.9rem; }
        .fc-sec-bar { width:.3rem; height:1.35rem; border-radius:999px; background:linear-gradient(#6366f1,#9333ea); }
        .fc-sec-title { font-size:1.05rem; font-weight:800; color:var(--fc-ink); }
        .fc-sec-count { font-size:.75rem; font-weight:700; color:var(--fc-sub); background:var(--fc-soft); border:1px solid var(--fc-line); padding:.15rem .55rem; border-radius:999px; }

        .fc-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.8rem; }
        @media (max-width:900px){ .fc-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:560px){ .fc-grid{ grid-template-columns:1fr; } }
        @media (min-width:1280px){ .fc-grid{ grid-template-columns:repeat(4,minmax(0,1fr)); } }
        @media (min-width:1700px){ .fc-grid{ grid-template-columns:repeat(5,minmax(0,1fr)); } }
        .fc-mat {
            position:relative; overflow:hidden; display:flex; align-items:center; gap:.85rem;
            padding:.9rem 1rem; border-radius:.9rem; background:var(--fc-card); border:1px solid var(--fc-line);
            transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .fc-mat::before { content:""; position:absolute; left:0; top:0; bottom:0; width:.28rem; background:linear-gradient(var(--c1),var(--c2)); }
        .fc-mat:hover { transform:translateY(-2px); box-shadow:0 12px 24px -14px rgba(15,23,42,.35); border-color:transparent; }
        .fc-num {
            width:2.5rem; height:2.5rem; flex:none; border-radius:.7rem; display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:.95rem; color:#fff; background:linear-gradient(135deg,var(--c1),var(--c2));
            box-shadow:0 6px 14px -6px var(--c2);
        }
        .fc-mat-name { font-size:.9rem; font-weight:700; color:var(--fc-ink); line-height:1.25; }

        .fc-empty {
            display:flex; flex-direction:column; align-items:center; gap:.4rem; text-align:center;
            padding:2.25rem 1rem; border:1.5px dashed var(--fc-line); border-radius:1rem; background:var(--fc-soft);
        }
        .fc-empty svg { width:2rem; height:2rem; color:var(--fc-sub); }
        .fc-empty p { font-size:.85rem; color:var(--fc-sub); }
    </style>

    {{-- ============ HERO ============ --}}
    <div class="fc-hero">
        <div class="fc-hero-top">
            <div class="fc-title">
                <div class="fc-emblem">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.7 2.805a.75.75 0 0 1 .6 0A60.65 60.65 0 0 1 22.83 8.72a.75.75 0 0 1-.231 1.337 49.949 49.949 0 0 0-9.902 3.912l-.003.002c-.114.06-.23.119-.346.18a.75.75 0 0 1-.7 0A50.88 50.88 0 0 0 7.5 12.173v-.224c0-.131.067-.248.172-.311a54.615 54.615 0 0 1 4.653-2.52.75.75 0 0 0-.65-1.352 56.123 56.123 0 0 0-4.78 2.589 1.858 1.858 0 0 0-.859 1.228 49.803 49.803 0 0 0-4.634-1.527.75.75 0 0 1-.231-1.337A60.653 60.653 0 0 1 11.7 2.805Z" /><path d="M13.06 15.473a48.45 48.45 0 0 1 7.666-3.282c.134 1.414.22 2.843.255 4.284a.75.75 0 0 1-.46.71 47.87 47.87 0 0 0-8.105 4.342.75.75 0 0 1-.832 0 47.87 47.87 0 0 0-8.104-4.342.75.75 0 0 1-.461-.71c.035-1.442.121-2.87.255-4.286.921.304 1.83.634 2.726.99v1.27a1.5 1.5 0 0 0-.14 2.508c-.09.38-.222.753-.397 1.11.452.213.901.434 1.346.66a6.727 6.727 0 0 0 .551-1.607 1.5 1.5 0 0 0 .14-2.67v-.645a48.549 48.549 0 0 1 3.44 1.667 2.25 2.25 0 0 0 2.13 0Z" /><path d="M4.462 19.462c.42-.419.753-.89 1-1.395.453.214.902.435 1.347.662a6.742 6.742 0 0 1-1.286 1.794.75.75 0 0 1-1.06-1.06Z" /></svg>
                </div>
                <div>
                    <div class="fc-eyebrow">Catalogue · Formation</div>
                    <h1 class="fc-name">{{ $formation->libelle }}</h1>
                </div>
            </div>
            <div class="fc-tags">
                @if ($formation->code_rncp)
                    <span class="fc-chip">{{ $formation->code_rncp }}</span>
                @endif
                @if ($formation->is_active)
                    <span class="fc-chip fc-chip--ok"><i></i> Active</span>
                @else
                    <span class="fc-chip fc-chip--off"><i></i> Inactive</span>
                @endif
            </div>
        </div>

        {{-- ============ STATS ============ --}}
        <div class="fc-stats">
            <div class="fc-stat">
                <div class="fc-stat-ic fc-stat-ic--a">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                </div>
                <div>
                    <div class="fc-stat-v">{{ count($matieres) }}</div>
                    <div class="fc-stat-l">Matière{{ count($matieres) > 1 ? 's' : '' }} au programme</div>
                </div>
            </div>
            <div class="fc-stat">
                <div class="fc-stat-ic fc-stat-ic--b">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </div>
                <div>
                    <div class="fc-stat-v">{{ $formation->duree_mois ?? '—' }}</div>
                    <div class="fc-stat-l">Mois de formation</div>
                </div>
            </div>
            <div class="fc-stat">
                <div class="fc-stat-ic fc-stat-ic--c">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </div>
                <div>
                    <div class="fc-stat-v">{{ $formation->niveau ?? '—' }}</div>
                    <div class="fc-stat-l">Niveau</div>
                </div>
            </div>
            <div class="fc-stat">
                <div class="fc-stat-ic fc-stat-ic--d">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                </div>
                <div>
                    <div class="fc-stat-v">{{ $nbApprenants }}</div>
                    <div class="fc-stat-l">Apprenant{{ $nbApprenants > 1 ? 's' : '' }} · {{ $nbClasses }} classe{{ $nbClasses > 1 ? 's' : '' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ PROGRAMME ============ --}}
    <div class="fc-section">
        <div class="fc-sec-head">
            <span class="fc-sec-bar"></span>
            <span class="fc-sec-title">Programme pédagogique</span>
            @if (count($matieres))
                <span class="fc-sec-count">{{ count($matieres) }} matière{{ count($matieres) > 1 ? 's' : '' }}</span>
            @endif
        </div>

        @if (count($matieres))
            <div class="fc-grid">
                @foreach ($matieres as $i => $matiere)
                    @php [$c1, $c2] = $degrades[$i % count($degrades)]; @endphp
                    <div class="fc-mat" style="--c1:{{ $c1 }};--c2:{{ $c2 }}">
                        <div class="fc-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="fc-mat-name">{{ $matiere }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="fc-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                <p>Aucune matière au programme pour le moment.<br>Ajoutez-les via le bouton <strong>Modifier</strong>.</p>
            </div>
        @endif
    </div>
</div>
