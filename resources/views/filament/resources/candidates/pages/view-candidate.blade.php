@php
    $record = $this->getRecord();
    $badge = ['gray' => 'fc-b-gray', 'info' => 'fc-b-info', 'primary' => 'fc-b-info', 'warning' => 'fc-b-warn', 'danger' => 'fc-b-danger', 'success' => 'fc-b-success'];
    $bcls = fn ($enum) => $badge[$enum?->getColor() ?? 'gray'] ?? 'fc-b-gray';
    $age = $record->date_naissance ? $record->date_naissance->age.' ans' : null;
@endphp

<div class="fc-page">
    <x-filament-actions::modals />
    <style>
        .fc-page { --fc-line: rgba(15,23,42,.08); display: flex; flex-direction: column; gap: 1rem; }
        .dark .fc-page { --fc-line: rgba(255,255,255,.09); }
        .fc-card { background: #fff; border: 1px solid var(--fc-line); border-radius: 1rem; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .dark .fc-card { background: #18202f; }
        .fc-cardhead { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .85rem 1.1rem; border-bottom: 1px solid var(--fc-line); }
        .fc-cardhead h3 { font-weight: 700; font-size: .95rem; color: #0f172a; display: flex; align-items: center; gap: .5rem; }
        .dark .fc-cardhead h3 { color: #f1f5f9; }
        .fc-cardhead h3 svg { width: 1.1rem; height: 1.1rem; color: #64748b; }
        .fc-cardbody { padding: 1.1rem; }
        .fc-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; color: #0f172a; }
        .dark .fc-title { color: #f8fafc; }
        .fc-subtitle { font-size: .85rem; color: #64748b; margin-top: .1rem; }

        /* En-tête */
        .fc-header { display: flex; flex-wrap: wrap; gap: 1.3rem; align-items: flex-start; padding: 1.3rem; }
        .fc-avatar { width: 5rem; height: 5rem; border-radius: 9999px; display: grid; place-items: center; flex: none; overflow: hidden;
            background: linear-gradient(135deg,#4f46e5,#7c3aed); color: #fff; font-size: 1.5rem; font-weight: 800; }
        .fc-avatar-img { object-fit: cover; background: none; }
        .fc-h-main { flex: 1; min-width: 260px; }
        .fc-h-name { display: flex; align-items: center; gap: .7rem; flex-wrap: wrap; }
        .fc-h-name .n { font-size: 1.6rem; font-weight: 800; color: #0f172a; } .dark .fc-h-name .n { color: #f8fafc; }
        .fc-h-info { margin-top: .9rem; display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: .8rem 1.5rem; }
        .fc-h-item { display: flex; gap: .55rem; align-items: flex-start; }
        .fc-h-item svg { width: 1.05rem; height: 1.05rem; color: #94a3b8; margin-top: .15rem; flex: none; }
        .fc-h-item .l { font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8; font-weight: 600; }
        .fc-h-item .v { font-size: .85rem; color: #1e293b; font-weight: 500; } .dark .fc-h-item .v { color: #e2e8f0; }
        .fc-h-actions { display: flex; flex-direction: column; gap: .45rem; align-items: stretch; min-width: 170px; }

        .fc-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 9999px; font-size: .72rem; font-weight: 700; }
        .fc-b-gray { background: #f1f5f9; color: #475569; } .dark .fc-b-gray { background: #334155; color: #cbd5e1; }
        .fc-b-info { background: #dbeafe; color: #1d4ed8; } .dark .fc-b-info { background: rgba(37,99,235,.25); color: #93c5fd; }
        .fc-b-warn { background: #ffedd5; color: #c2410c; } .dark .fc-b-warn { background: rgba(234,88,12,.22); color: #fdba74; }
        .fc-b-danger { background: #fee2e2; color: #b91c1c; } .dark .fc-b-danger { background: rgba(239,68,68,.2); color: #fca5a5; }
        .fc-b-success { background: #dcfce7; color: #15803d; } .dark .fc-b-success { background: rgba(34,197,94,.2); color: #86efac; }

        .fc-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; padding: .5rem .85rem; border-radius: .6rem;
            font-size: .82rem; font-weight: 600; border: 1px solid var(--fc-line); background: #fff; color: #334155; cursor: pointer; text-decoration: none; }
        .dark .fc-btn { background: #18202f; color: #cbd5e1; } .fc-btn:hover { background: #f8fafc; } .dark .fc-btn:hover { background: #1f2937; }
        .fc-btn svg { width: 1rem; height: 1rem; }
        .fc-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; } .fc-btn--primary:hover { background: #1d4ed8; }

        /* KPI */
        .fc-kpis { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .8rem; }
        .fc-kpi { display: flex; align-items: center; gap: .8rem; padding: .9rem 1rem; }
        .fc-kpi-ic { width: 2.5rem; height: 2.5rem; border-radius: 9999px; display: grid; place-items: center; flex: none; }
        .fc-kpi-ic svg { width: 1.25rem; height: 1.25rem; }
        .fc-kpi .val { font-size: 1.4rem; font-weight: 800; color: #0f172a; line-height: 1; } .dark .fc-kpi .val { color: #f8fafc; }
        .fc-kpi .lbl { font-size: .76rem; color: #64748b; margin-top: .15rem; }
        .fc-ic-blue { background: #dbeafe; color: #2563eb; } .dark .fc-ic-blue { background: rgba(37,99,235,.2); }
        .fc-ic-violet { background: #ede9fe; color: #7c3aed; } .dark .fc-ic-violet { background: rgba(124,58,237,.2); }
        .fc-ic-green { background: #dcfce7; color: #16a34a; } .dark .fc-ic-green { background: rgba(34,197,94,.2); }
        .fc-ic-amber { background: #fef3c7; color: #d97706; } .dark .fc-ic-amber { background: rgba(217,119,6,.2); }

        /* Grille */
        .fc-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; align-items: start; }
        .fc-col { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }

        .fc-fields { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: .9rem 1.5rem; }
        .fc-field .l { font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8; font-weight: 600; }
        .fc-field .v { font-size: .88rem; color: #1e293b; font-weight: 500; margin-top: .15rem; } .dark .fc-field .v { color: #e2e8f0; }

        /* Pièces */
        .fc-pieces { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: .6rem; }
        .fc-piece { display: flex; align-items: center; gap: .6rem; padding: .6rem .75rem; border: 1px solid var(--fc-line); border-radius: .7rem; text-decoration: none; }
        .fc-piece:hover { background: #f8fafc; } .dark .fc-piece:hover { background: #1f2937; }
        .fc-piece-ic { width: 2rem; height: 2rem; border-radius: .5rem; display: grid; place-items: center; flex: none; background: #f1f5f9; }
        .dark .fc-piece-ic { background: #334155; }
        .fc-piece-ic svg { width: 1.05rem; height: 1.05rem; color: #64748b; }
        .fc-piece .nm { font-size: .82rem; font-weight: 600; color: #1e293b; } .dark .fc-piece .nm { color: #e2e8f0; }
        .fc-piece .st { font-size: .72rem; }
        .fc-dot { margin-left: auto; width: 1.15rem; height: 1.15rem; }

        /* Table */
        .fc-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .fc-table th { text-align: left; padding: .5rem .6rem; font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8; font-weight: 600; }
        .fc-table td { padding: .6rem; border-top: 1px solid var(--fc-line); color: #1e293b; } .dark .fc-table td { color: #e2e8f0; }
        .fc-tablewrap { overflow-x: auto; }
        .fc-mini-donut { position: relative; width: 2.1rem; height: 2.1rem; border-radius: 50%; display: inline-grid; place-items: center;
            background: conic-gradient(#2563eb calc(var(--v)*1%), #e5e7eb 0); }
        .dark .fc-mini-donut { background: conic-gradient(#2563eb calc(var(--v)*1%), #334155 0); }
        .fc-mini-donut::before { content:''; position: absolute; inset: .28rem; border-radius: 50%; background: #fff; } .dark .fc-mini-donut::before { background: #18202f; }
        .fc-mini-donut span { position: relative; font-size: .62rem; font-weight: 800; color: #2563eb; }

        /* Timeline activité */
        .fc-tl { display: flex; flex-direction: column; gap: .8rem; }
        .fc-tl-item { display: flex; gap: .7rem; }
        .fc-tl-dot { width: .6rem; height: .6rem; border-radius: 9999px; background: #2563eb; margin-top: .35rem; flex: none; }
        .fc-tl .d { font-size: .72rem; color: #94a3b8; }
        .fc-tl .t { font-size: .84rem; color: #1e293b; font-weight: 500; } .dark .fc-tl .t { color: #e2e8f0; }

        /* Sidebar rows */
        .fc-adm { display: flex; flex-direction: column; gap: .1rem; }
        .fc-adm-row { display: flex; align-items: center; gap: .6rem; padding: .5rem 0; border-bottom: 1px dashed var(--fc-line); }
        .fc-adm-row:last-child { border-bottom: none; }
        .fc-adm-row svg { width: 1.1rem; height: 1.1rem; flex: none; }
        .fc-adm-row .lbl { font-size: .82rem; color: #475569; } .dark .fc-adm-row .lbl { color: #cbd5e1; }
        .fc-adm-row .val { margin-left: auto; font-size: .78rem; font-weight: 600; }

        .fc-task { display: flex; align-items: flex-start; gap: .55rem; padding: .55rem 0; border-bottom: 1px dashed var(--fc-line); }
        .fc-task:last-child { border-bottom: none; }
        .fc-task .tt { font-size: .82rem; color: #1e293b; font-weight: 500; } .dark .fc-task .tt { color: #e2e8f0; }
        .fc-task .ee { font-size: .72rem; color: #94a3b8; }
        .fc-note { padding: .6rem .75rem; background: #f8fafc; border: 1px solid var(--fc-line); border-radius: .6rem; margin-bottom: .5rem; }
        .dark .fc-note { background: #0f1725; }
        .fc-note .c { font-size: .82rem; color: #334155; } .dark .fc-note .c { color: #cbd5e1; }
        .fc-note .m { font-size: .7rem; color: #94a3b8; margin-top: .3rem; }
        .fc-empty { color: #94a3b8; font-size: .82rem; padding: .5rem 0; }

        @media (max-width: 1024px) { .fc-grid { grid-template-columns: 1fr; } .fc-kpis { grid-template-columns: repeat(2,1fr); } .fc-h-info { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 640px) { .fc-fields, .fc-pieces { grid-template-columns: 1fr; } .fc-h-info { grid-template-columns: 1fr; } .fc-h-actions { width: 100%; } }
    </style>

    <div>
        <div class="fc-title">Fiche candidat</div>
        <div class="fc-subtitle">Vue complète du dossier candidat</div>
    </div>

    {{-- En-tête --}}
    <div class="fc-card fc-header">
        @if ($photo = $record->photoUrl())
            <img src="{{ $photo }}" alt="Photo de {{ $record->nom_complet }}" class="fc-avatar fc-avatar-img">
        @else
            <span class="fc-avatar">{{ $record->initiales }}</span>
        @endif
        <div class="fc-h-main">
            <div class="fc-h-name">
                <span class="n">{{ $record->nom_complet }}</span>
                <span class="fc-badge {{ $bcls($record->statut) }}">{{ $record->statut?->getLabel() }}</span>
            </div>
            <div class="fc-h-info">
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg><div><div class="l">Formation visée</div><div class="v">{{ $record->formationVisee?->libelle ?? '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg><div><div class="l">Téléphone</div><div class="v">{{ $record->telephone ?: '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 5L2 7"/></svg><div><div class="l">Email</div><div class="v">{{ $record->email ?: '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg><div><div class="l">Adresse</div><div class="v">{{ $record->adresse ?: '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><div><div class="l">Disponibilité</div><div class="v">{{ $record->disponibilite ?: '—' }}</div></div></div>
                <div class="fc-h-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg><div><div class="l">Mobilité</div><div class="v">{{ $record->mobilite ?: '—' }}</div></div></div>
            </div>
        </div>
        <div class="fc-h-actions">
            @if (! $statutFinal && ! $entretienActif)
                <button type="button" class="fc-btn" wire:click="mountAction('planifierEntretien')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg> Planifier un entretien</button>
            @endif
            @if ($kpis['matchings'] > 0)
                <a href="{{ \App\Filament\Resources\Matchings\MatchingResource::getUrl('index') }}" class="fc-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h10v10M7 17 17 7"/></svg> Voir le matching</a>
            @endif
            <a href="{{ \App\Filament\Resources\Candidates\CandidateResource::getUrl('edit', ['record' => $record]) }}" class="fc-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg> Modifier</a>
        </div>
    </div>

    {{-- KPI --}}
    <div class="fc-kpis">
        <div class="fc-card fc-kpi"><span class="fc-kpi-ic fc-ic-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg></span><div><div class="val">{{ $kpis['dossier'] }} %</div><div class="lbl">Dossier complet</div></div></div>
        <div class="fc-card fc-kpi"><span class="fc-kpi-ic fc-ic-violet"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg></span><div><div class="val">{{ $kpis['matchings'] }}</div><div class="lbl">Matchings / propositions</div></div></div>
        <div class="fc-card fc-kpi"><span class="fc-kpi-ic fc-ic-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span><div><div class="val">{{ $kpis['entretiens'] }}</div><div class="lbl">Entretiens</div></div></div>
        <div class="fc-card fc-kpi"><span class="fc-kpi-ic fc-ic-amber"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span><div><div class="val">{{ $kpis['docsManquants'] }}</div><div class="lbl">Documents manquants</div></div></div>
    </div>

    <div class="fc-grid">
        {{-- Colonne principale --}}
        <div class="fc-col">
            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Parcours de l'apprenant</h3></div>
                <div class="fc-cardbody">{!! view('filament.parcours.timeline', ['etapes' => $etapes])->render() !!}</div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg> Informations & parcours</h3></div>
                <div class="fc-cardbody">
                    <div class="fc-fields">
                        <div class="fc-field"><div class="l">Date de naissance</div><div class="v">{{ $record->date_naissance?->format('d/m/Y') ?? '—' }} @if($age)<span style="color:#94a3b8">({{ $age }})</span>@endif</div></div>
                        <div class="fc-field"><div class="l">Niveau actuel</div><div class="v">{{ $record->niveau_actuel ?: '—' }}</div></div>
                        <div class="fc-field"><div class="l">Formation visée</div><div class="v">{{ $record->formationVisee?->libelle ?? '—' }}</div></div>
                        <div class="fc-field"><div class="l">Classes (matières)</div><div class="v">{{ $record->promotions->map->nom_complet->implode(', ') ?: 'Non affecté' }}</div></div>
                        <div class="fc-field"><div class="l">Disponible à partir du</div><div class="v">{{ $record->date_disponibilite?->format('d/m/Y') ?? '—' }}</div></div>
                        <div class="fc-field"><div class="l">Commercial référent</div><div class="v">{{ $record->commercial?->name ?? '—' }}</div></div>
                    </div>
                </div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg> Pièces justificatives</h3></div>
                <div class="fc-cardbody">
                    <div class="fc-pieces">
                        @foreach ($pieces as $p)
                            <a class="fc-piece" @if($p['url']) href="{{ $p['url'] }}" target="_blank" @endif>
                                <span class="fc-piece-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></span>
                                <div><div class="nm">{{ $p['label'] }}</div><div class="st" style="color: {{ $p['present'] ? '#16a34a' : ($p['requis'] ? '#dc2626' : '#94a3b8') }}">{{ $p['present'] ? 'Fournie' : ($p['requis'] ? 'Manquante' : 'Non requise') }}</div></div>
                                @if ($p['present'])
                                    <svg class="fc-dot" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                @elseif ($p['requis'])
                                    <svg class="fc-dot" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg> Matchings / propositions</h3></div>
                <div class="fc-cardbody">
                    @if ($matchings->isEmpty())
                        <div class="fc-empty">Aucune proposition pour ce candidat.</div>
                    @else
                        <div class="fc-tablewrap">
                            <table class="fc-table">
                                <thead><tr><th>Entreprise</th><th>Besoin</th><th>Statut</th><th>Score</th><th>Date</th></tr></thead>
                                <tbody>
                                    @foreach ($matchings as $m)
                                        <tr>
                                            <td style="font-weight:600">{{ $m['entreprise'] }}</td>
                                            <td>{{ $m['besoin'] }}</td>
                                            <td><span class="fc-badge {{ $bcls($m['statut']) }}">{{ $m['statut']?->getLabel() }}</span></td>
                                            <td>@if($m['score'] !== null)<span class="fc-mini-donut" style="--v: {{ $m['score'] }}"><span>{{ $m['score'] }}</span></span>@else — @endif</td>
                                            <td style="color:#94a3b8">{{ $m['date']?->format('d/m/Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Historique & activité</h3></div>
                <div class="fc-cardbody">
                    @if ($activites->isEmpty())
                        <div class="fc-empty">Aucune activité enregistrée.</div>
                    @else
                        <div class="fc-tl">
                            @foreach ($activites as $a)
                                <div class="fc-tl-item">
                                    <span class="fc-tl-dot"></span>
                                    <div>
                                        <div class="d">{{ $a->created_at?->format('d/m/Y H:i') }}</div>
                                        <div class="t">{{ ['created' => 'Dossier créé', 'updated' => 'Mise à jour du dossier', 'deleted' => 'Suppression'][$a->description] ?? $a->description }}@if($a->causer) — {{ $a->causer->name }}@endif</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Colonne latérale --}}
        <div class="fc-col">
            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Tâches & relances</h3></div>
                <div class="fc-cardbody">
                    @forelse ($taches as $t)
                        <div class="fc-task">
                            <span class="fc-badge {{ $bcls($t->priorite) }}" style="margin-top:.1rem">{{ $t->priorite?->getLabel() }}</span>
                            <div><div class="tt">{{ $t->titre }}</div>@if($t->due_date)<div class="ee">Échéance : {{ $t->due_date->format('d/m/Y') }}</div>@endif</div>
                        </div>
                    @empty
                        <div class="fc-empty">Aucune tâche en cours.</div>
                    @endforelse
                </div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Statut administratif</h3></div>
                <div class="fc-cardbody">
                    @php
                        $adm = [
                            ['Admission', $record->admission?->statut?->getLabel() ?? 'Pas encore admis', $record->admission?->statut?->getColor() ?? 'gray'],
                            ['CV', $record->hasCv() ? 'Fourni' : 'Manquant', $record->hasCv() ? 'success' : 'danger'],
                            ['Consentement CV', $record->cv_consentement ? 'Autorisé' : 'Non autorisé', $record->cv_consentement ? 'success' : 'danger'],
                            ['Contrat', $contrat?->statut_contrat?->getLabel() ?? 'Aucun', $contrat?->statut_contrat?->getColor() ?? 'gray'],
                            ['Dossier OPCO', $contrat?->opcoFile?->statut?->getLabel() ?? 'Aucun', $contrat?->opcoFile?->statut?->getColor() ?? 'gray'],
                        ];
                    @endphp
                    <div class="fc-adm">
                        @foreach ($adm as [$lbl, $val, $col])
                            <div class="fc-adm-row">
                                <svg viewBox="0 0 24 24" fill="none" stroke="{{ $col === 'success' ? '#16a34a' : ($col === 'danger' ? '#dc2626' : ($col === 'warning' ? '#d97706' : '#94a3b8')) }}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">@if($col==='success')<path d="M20 6 9 17l-5-5"/>@else<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>@endif</svg>
                                <span class="lbl">{{ $lbl }}</span>
                                <span class="val" style="color: {{ $col === 'success' ? '#16a34a' : ($col === 'danger' ? '#dc2626' : ($col === 'warning' ? '#d97706' : '#64748b')) }}">{{ $val }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="fc-card">
                <div class="fc-cardhead"><h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Commentaires internes</h3></div>
                <div class="fc-cardbody">
                    @forelse ($notes as $n)
                        <div class="fc-note"><div class="c">{{ $n->contenu }}</div><div class="m">{{ $n->author?->name ?? 'Système' }} — {{ $n->created_at?->format('d/m/Y H:i') }}</div></div>
                    @empty
                        <div class="fc-empty">Aucun commentaire interne.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Documents (RelationManager existant) --}}
    <div class="fc-card">
        <div class="fc-cardbody">
            @livewire(\App\Filament\Resources\Candidates\RelationManagers\DocumentsRelationManager::class, ['ownerRecord' => $record, 'pageClass' => $this::class], key('fc-docs-'.$record->getKey()))
        </div>
    </div>
</div>
