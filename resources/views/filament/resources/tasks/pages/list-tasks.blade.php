@php
    // Mapping couleur d'enum (Filament) → classe de badge locale.
    $badge = [
        'gray' => 'ta-b-gray', 'info' => 'ta-b-info', 'warning' => 'ta-b-warn',
        'danger' => 'ta-b-danger', 'success' => 'ta-b-success',
    ];
    $d = $this->donnees;
    $liste = $d['liste'];
    $detail = $d['detail'];
    $onglets = [
        'toutes' => 'Toutes', 'mes-taches' => 'Mes tâches', 'urgentes' => 'Urgentes',
        'en-retard' => 'En retard', 'alertes-systeme' => 'Alertes système', 'non-assignees' => 'Non assignées',
    ];
@endphp

<div class="ta-page">
    <style>
        .ta-page { --ta-line: rgba(15,23,42,.08); display: flex; flex-direction: column; gap: 1rem; }
        .ta-card { background: #fff; border: 1px solid var(--ta-line); border-radius: .9rem; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .dark .ta-page { --ta-line: rgba(255,255,255,.09); }
        .dark .ta-card { background: #18202f; }

        /* En-tête */
        .ta-head { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; justify-content: space-between; }
        .ta-title { font-size: 1.6rem; font-weight: 800; letter-spacing: -.02em; color: #0f172a; }
        .dark .ta-title { color: #f1f5f9; }
        .ta-subtitle { margin-top: .15rem; font-size: .85rem; color: #64748b; }
        .ta-head-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .ta-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .5rem .85rem; border-radius: .6rem; font-size: .82rem; font-weight: 600; border: 1px solid var(--ta-line); background: #fff; color: #334155; cursor: pointer; text-decoration: none; }
        .ta-btn:hover { background: #f8fafc; }
        .dark .ta-btn { background: #18202f; color: #cbd5e1; }
        .dark .ta-btn:hover { background: #1f2937; }
        .ta-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .ta-btn--primary:hover { background: #1d4ed8; }
        .ta-btn svg { width: 1rem; height: 1rem; }
        .ta-toggle { display: inline-flex; border: 1px solid var(--ta-line); border-radius: .6rem; overflow: hidden; }
        .ta-toggle button { padding: .5rem .8rem; font-size: .82rem; font-weight: 600; background: #fff; color: #64748b; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: .35rem; }
        .dark .ta-toggle button { background: #18202f; color: #94a3b8; }
        .ta-toggle button.is-active { background: #2563eb; color: #fff; }
        .ta-toggle svg { width: 1rem; height: 1rem; }

        /* KPI */
        .ta-kpis { display: grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap: .75rem; }
        .ta-kpi { position: relative; text-align: left; padding: .85rem .9rem; border-radius: .9rem; border: 1px solid var(--ta-line); border-left-width: 4px; background: #fff; cursor: pointer; display: flex; flex-direction: column; gap: .3rem; transition: transform .12s ease, box-shadow .12s ease; }
        .ta-kpi:hover { transform: translateY(-2px); box-shadow: 0 8px 20px -8px rgba(15,23,42,.25); }
        .dark .ta-kpi { background: #18202f; }
        .ta-kpi-top { display: flex; align-items: center; gap: .45rem; color: #64748b; font-size: .78rem; font-weight: 600; }
        .ta-kpi-top svg { width: 1.05rem; height: 1.05rem; }
        .ta-kpi-val { font-size: 1.7rem; font-weight: 800; color: #0f172a; line-height: 1; }
        .dark .ta-kpi-val { color: #f1f5f9; }
        .ta-kpi-arrow { position: absolute; top: .75rem; right: .7rem; color: #cbd5e1; width: 1rem; height: 1rem; }
        .ta-kpi--bleu   { border-left-color: #3b82f6; } .ta-kpi--bleu   .ta-kpi-top svg { color: #3b82f6; }
        .ta-kpi--orange { border-left-color: #f97316; } .ta-kpi--orange .ta-kpi-top svg { color: #f97316; }
        .ta-kpi--rouge  { border-left-color: #ef4444; } .ta-kpi--rouge  .ta-kpi-top svg { color: #ef4444; }
        .ta-kpi--violet { border-left-color: #8b5cf6; } .ta-kpi--violet .ta-kpi-top svg { color: #8b5cf6; }
        .ta-kpi--gris   { border-left-color: #94a3b8; } .ta-kpi--gris   .ta-kpi-top svg { color: #64748b; }
        .ta-kpi--ambre  { border-left-color: #f59e0b; } .ta-kpi--ambre  .ta-kpi-top svg { color: #f59e0b; }

        /* Filtres */
        .ta-filters { padding: .9rem; display: flex; flex-direction: column; gap: .7rem; }
        .ta-filters-row { display: grid; grid-template-columns: 1.6fr repeat(6, 1fr) auto; gap: .5rem; }
        .ta-input, .ta-select { width: 100%; padding: .5rem .6rem; border: 1px solid var(--ta-line); border-radius: .55rem; font-size: .8rem; background: #fff; color: #334155; }
        .dark .ta-input, .dark .ta-select { background: #0f1725; color: #cbd5e1; }
        .ta-input:focus, .ta-select:focus { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .ta-tabs { display: flex; flex-wrap: wrap; gap: .4rem; }
        .ta-tab { padding: .42rem .8rem; border-radius: 9999px; font-size: .8rem; font-weight: 600; border: 1px solid var(--ta-line); background: #fff; color: #475569; cursor: pointer; display: inline-flex; align-items: center; gap: .35rem; }
        .dark .ta-tab { background: #18202f; color: #94a3b8; }
        .ta-tab.is-active { background: #2563eb; border-color: #2563eb; color: #fff; }

        /* Grille principale */
        .ta-grid { display: grid; grid-template-columns: 60% 40%; gap: 1rem; align-items: start; }
        .ta-left, .ta-right { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
        .ta-cardhead { padding: .9rem 1rem; border-bottom: 1px solid var(--ta-line); font-weight: 700; color: #0f172a; display: flex; align-items: center; justify-content: space-between; }
        .dark .ta-cardhead { color: #f1f5f9; }

        /* Tableau */
        .ta-tablewrap { overflow-x: auto; }
        .ta-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .ta-table th { text-align: left; padding: .55rem .7rem; font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; color: #94a3b8; font-weight: 600; white-space: nowrap; }
        .ta-table td { padding: .6rem .7rem; border-top: 1px solid var(--ta-line); vertical-align: middle; }
        .ta-row { cursor: pointer; }
        .ta-row:hover { background: #f8fafc; }
        .dark .ta-row:hover { background: #1f2937; }
        .ta-row.is-selected { background: #eff6ff; box-shadow: inset 3px 0 0 #2563eb; }
        .dark .ta-row.is-selected { background: rgba(37,99,235,.14); }
        .ta-tache-titre { font-weight: 600; color: #1e293b; }
        .dark .ta-tache-titre { color: #e2e8f0; }
        .ta-link { color: #2563eb; font-weight: 600; text-decoration: none; }
        .ta-link:hover { text-decoration: underline; }
        .ta-muted { color: #94a3b8; }
        .ta-with-icon { display: inline-flex; align-items: center; gap: .3rem; }
        .ta-with-icon svg { width: .9rem; height: .9rem; color: #94a3b8; }

        /* Badges */
        .ta-badge { display: inline-flex; align-items: center; gap: .25rem; padding: .15rem .5rem; border-radius: 9999px; font-size: .72rem; font-weight: 700; white-space: nowrap; }
        .ta-b-gray { background: #f1f5f9; color: #475569; } .dark .ta-b-gray { background: #334155; color: #cbd5e1; }
        .ta-b-info { background: #dbeafe; color: #1d4ed8; } .dark .ta-b-info { background: rgba(37,99,235,.25); color: #93c5fd; }
        .ta-b-warn { background: #ffedd5; color: #c2410c; } .dark .ta-b-warn { background: rgba(234,88,12,.22); color: #fdba74; }
        .ta-b-danger { background: #fee2e2; color: #b91c1c; } .dark .ta-b-danger { background: rgba(239,68,68,.2); color: #fca5a5; }
        .ta-b-success { background: #dcfce7; color: #15803d; } .dark .ta-b-success { background: rgba(34,197,94,.2); color: #86efac; }

        /* Actions ligne */
        .ta-actions { display: inline-flex; gap: .25rem; }
        .ta-iconbtn { display: grid; place-items: center; width: 1.75rem; height: 1.75rem; border-radius: .45rem; border: 1px solid var(--ta-line); background: #fff; color: #64748b; cursor: pointer; }
        .ta-iconbtn:hover { background: #f1f5f9; color: #0f172a; }
        .dark .ta-iconbtn { background: #18202f; color: #94a3b8; }
        .ta-iconbtn svg { width: 1rem; height: 1rem; }
        .ta-iconbtn--go:hover { color: #2563eb; }
        .ta-iconbtn--ok:hover { color: #16a34a; }

        /* Pagination */
        .ta-pagination { display: flex; align-items: center; justify-content: space-between; padding: .7rem 1rem; gap: .5rem; flex-wrap: wrap; font-size: .8rem; color: #64748b; }
        .ta-pages { display: inline-flex; gap: .25rem; }
        .ta-pagebtn { min-width: 1.9rem; height: 1.9rem; padding: 0 .4rem; border-radius: .45rem; border: 1px solid var(--ta-line); background: #fff; color: #475569; cursor: pointer; font-size: .8rem; }
        .dark .ta-pagebtn { background: #18202f; color: #cbd5e1; }
        .ta-pagebtn.is-active { background: #2563eb; border-color: #2563eb; color: #fff; }
        .ta-pagebtn:disabled { opacity: .4; cursor: default; }

        /* Détail */
        .ta-detail { padding: 1rem; display: flex; flex-direction: column; gap: .8rem; }
        .ta-detail-title { font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .dark .ta-detail-title { color: #f1f5f9; }
        .ta-fields { display: grid; grid-template-columns: auto 1fr; gap: .4rem .8rem; font-size: .82rem; }
        .ta-fields dt { color: #94a3b8; }
        .ta-fields dd { color: #1e293b; font-weight: 500; text-align: right; }
        .dark .ta-fields dd { color: #e2e8f0; }
        .ta-section-title { font-size: .78rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .03em; margin-bottom: .4rem; display: flex; align-items: center; gap: .35rem; }
        .dark .ta-section-title { color: #94a3b8; }
        .ta-desc { font-size: .84rem; color: #475569; line-height: 1.5; }
        .dark .ta-desc { color: #cbd5e1; }
        .ta-timeline { display: flex; flex-direction: column; gap: .55rem; }
        .ta-tl-item { display: flex; gap: .55rem; font-size: .78rem; }
        .ta-tl-dot { width: .55rem; height: .55rem; border-radius: 9999px; background: #3b82f6; margin-top: .3rem; flex: none; }
        .ta-tl-date { color: #94a3b8; }
        .ta-tl-text { color: #475569; }
        .dark .ta-tl-text { color: #cbd5e1; }
        .ta-comment { background: #f8fafc; border: 1px solid var(--ta-line); border-radius: .6rem; padding: .55rem .7rem; font-size: .8rem; color: #475569; }
        .dark .ta-comment { background: #0f1725; color: #cbd5e1; }
        .ta-detail-actions { display: flex; flex-wrap: wrap; gap: .5rem; padding-top: .3rem; }
        .ta-empty { padding: 2rem 1rem; text-align: center; color: #94a3b8; font-size: .85rem; }

        /* Alertes auto */
        .ta-alerts { padding: .4rem .5rem; }
        .ta-alert { display: flex; align-items: center; gap: .7rem; padding: .6rem .7rem; border-radius: .6rem; cursor: pointer; }
        .ta-alert:hover { background: #f8fafc; } .dark .ta-alert:hover { background: #1f2937; }
        .ta-alert-ico { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: .5rem; background: #fff7ed; color: #ea580c; flex: none; }
        .dark .ta-alert-ico { background: rgba(234,88,12,.15); }
        .ta-alert-ico svg { width: 1.1rem; height: 1.1rem; }
        .ta-alert-label { flex: 1; font-size: .84rem; font-weight: 600; color: #334155; }
        .dark .ta-alert-label { color: #e2e8f0; }
        .ta-alert-count { background: #fee2e2; color: #b91c1c; font-weight: 700; font-size: .75rem; padding: .1rem .5rem; border-radius: 9999px; }
        .dark .ta-alert-count { background: rgba(239,68,68,.22); color: #fca5a5; }
        .ta-alert-arrow { width: 1rem; height: 1rem; color: #cbd5e1; }

        /* Kanban */
        .ta-kanban-cols { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(200px, 1fr); gap: .75rem; padding: 1rem; overflow-x: auto; }
        .ta-kcol { background: #f8fafc; border: 1px solid var(--ta-line); border-radius: .7rem; padding: .6rem; display: flex; flex-direction: column; gap: .5rem; }
        .dark .ta-kcol { background: #0f1725; }
        .ta-kcol-head { display: flex; align-items: center; justify-content: space-between; font-size: .8rem; font-weight: 700; color: #334155; }
        .dark .ta-kcol-head { color: #e2e8f0; }
        .ta-kcount { background: #e2e8f0; color: #475569; border-radius: 9999px; padding: 0 .45rem; font-size: .72rem; }
        .dark .ta-kcount { background: #334155; color: #cbd5e1; }
        .ta-kcard { background: #fff; border: 1px solid var(--ta-line); border-radius: .55rem; padding: .5rem .6rem; font-size: .78rem; color: #1e293b; cursor: pointer; box-shadow: 0 1px 1px rgba(15,23,42,.04); }
        .dark .ta-kcard { background: #18202f; color: #e2e8f0; }
        .ta-kcard:hover { border-color: #93c5fd; }
        .ta-kmore { font-size: .74rem; color: #2563eb; cursor: pointer; }

        /* Responsive */
        @media (max-width: 1100px) {
            .ta-grid { grid-template-columns: 1fr; }
            .ta-filters-row { grid-template-columns: 1fr 1fr; }
            .ta-kpis { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 640px) {
            .ta-kpis { grid-auto-flow: column; grid-auto-columns: 62%; grid-template-columns: none; overflow-x: auto; scroll-snap-type: x mandatory; }
            .ta-kpi { scroll-snap-align: start; }
            .ta-filters-row { grid-template-columns: 1fr; }
            .ta-head-actions { width: 100%; }
        }
    </style>

    {{-- 1 · En-tête --}}
    <div class="ta-head">
        <div>
            <div class="ta-title">Tâches &amp; Alertes</div>
            <div class="ta-subtitle">Suivez les actions à traiter, les urgences et les dossiers bloqués.</div>
        </div>
        <div class="ta-head-actions">
            <a href="{{ \App\Filament\Resources\Tasks\TaskResource::getUrl('create') }}" class="ta-btn ta-btn--primary">
                <x-filament::icon icon="heroicon-m-plus" /> Nouvelle tâche
            </a>
            <button type="button" class="ta-btn" wire:click="exporter">
                <x-filament::icon icon="heroicon-m-arrow-down-tray" /> Exporter
            </button>
            <div class="ta-toggle">
                <button type="button" wire:click="basculerVue('liste')" class="{{ $vue === 'liste' ? 'is-active' : '' }}">
                    <x-filament::icon icon="heroicon-m-list-bullet" /> Vue Liste
                </button>
                <button type="button" wire:click="basculerVue('kanban')" class="{{ $vue === 'kanban' ? 'is-active' : '' }}">
                    <x-filament::icon icon="heroicon-m-view-columns" /> Vue Kanban
                </button>
            </div>
        </div>
    </div>

    {{-- 2 · KPI --}}
    <div class="ta-kpis">
        @foreach ($d['kpis'] as $kpi)
            <button type="button" class="ta-kpi ta-kpi--{{ $kpi['couleur'] }}" wire:click="appliquerKpi(@js($kpi['filtre']))">
                <span class="ta-kpi-top"><x-filament::icon :icon="$kpi['icone']" /> {{ $kpi['label'] }}</span>
                <span class="ta-kpi-val">{{ $kpi['valeur'] }}</span>
                <x-filament::icon icon="heroicon-m-chevron-right" class="ta-kpi-arrow" />
            </button>
        @endforeach
    </div>

    {{-- 3 · Filtres --}}
    <div class="ta-card ta-filters">
        <div class="ta-filters-row">
            <input type="text" class="ta-input" placeholder="Rechercher une tâche, un dossier…" wire:model.live.debounce.400ms="recherche">
            <select class="ta-select" wire:model.live="responsable">
                <option value="">Responsable</option>
                @foreach ($d['options']['responsables'] as $id => $nom)<option value="{{ $id }}">{{ $nom }}</option>@endforeach
            </select>
            <select class="ta-select" wire:model.live="priorite">
                <option value="">Priorité</option>
                @foreach (\App\Enums\TaskPriorite::cases() as $p)<option value="{{ $p->value }}">{{ $p->getLabel() }}</option>@endforeach
            </select>
            <select class="ta-select" wire:model.live="statut">
                <option value="">Statut</option>
                @foreach (\App\Enums\TaskStatut::cases() as $s)<option value="{{ $s->value }}">{{ $s->getLabel() }}</option>@endforeach
            </select>
            <select class="ta-select" wire:model.live="module">
                <option value="">Module lié</option>
                @foreach ($d['options']['modules'] as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
            </select>
            <select class="ta-select" wire:model.live="echeance">
                <option value="">Échéance</option>
                <option value="aujourdhui">Aujourd'hui</option>
                <option value="semaine">Cette semaine</option>
                <option value="retard">En retard</option>
            </select>
            <select class="ta-select" wire:model.live="type">
                <option value="">Type</option>
                <option value="manuel">Tâche manuelle</option>
                <option value="auto">Alerte système</option>
            </select>
            <button type="button" class="ta-btn" wire:click="reinitialiser" title="Réinitialiser">
                <x-filament::icon icon="heroicon-m-arrow-path" /> Réinitialiser
            </button>
        </div>
        <div class="ta-tabs">
            @foreach ($onglets as $cle => $lbl)
                <button type="button" class="ta-tab {{ $onglet === $cle ? 'is-active' : '' }}" wire:click="choisirOnglet('{{ $cle }}')">{{ $lbl }}</button>
            @endforeach
        </div>
    </div>

    {{-- 4 · Grille principale --}}
    <div class="ta-grid">
        {{-- Colonne gauche : liste (ou kanban plein) + kanban rapide --}}
        <div class="ta-left">
            @if ($vue === 'liste')
                <div class="ta-card">
                    <div class="ta-cardhead">Liste des tâches et alertes</div>
                    <div class="ta-tablewrap">
                        <table class="ta-table">
                            <thead>
                                <tr>
                                    <th>Priorité</th><th>Tâche</th><th>Module</th><th>Dossier lié</th>
                                    <th>Responsable</th><th>Échéance</th><th>Statut</th><th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($liste['items'] as $t)
                                    <tr class="ta-row {{ $t['id'] === $d['detailId'] ? 'is-selected' : '' }}" wire:key="row-{{ $t['id'] }}" wire:click="selectionner({{ $t['id'] }})">
                                        <td><span class="ta-badge {{ $badge[$t['priorite']->getColor()] }}">{{ $t['priorite']->getLabel() }}</span></td>
                                        <td><span class="ta-tache-titre">{{ $t['titre'] }}</span></td>
                                        <td class="ta-muted">{{ $t['module'] }}</td>
                                        <td>
                                            @if ($t['dossierUrl'] && $t['dossierLabel'])
                                                <a href="{{ $t['dossierUrl'] }}" class="ta-link" wire:click.stop wire:navigate>{{ $t['dossierLabel'] }}</a>
                                            @else
                                                <span class="ta-muted">{{ $t['dossierLabel'] ?? '—' }}</span>
                                            @endif
                                        </td>
                                        <td><span class="ta-with-icon"><x-filament::icon icon="heroicon-m-user-circle" /> {{ $t['responsable'] }}</span></td>
                                        <td class="{{ $t['echeanceRouge'] ? 'ta-b-danger' : '' }}" style="{{ $t['echeanceRouge'] ? 'color:#b91c1c;font-weight:600' : '' }}">{{ $t['echeanceLabel'] }}</td>
                                        <td><span class="ta-badge {{ $badge[$t['statut']->getColor()] }}">{{ $t['statut']->getLabel() }}</span></td>
                                        <td>
                                            <div class="ta-actions" wire:click.stop>
                                                <button type="button" class="ta-iconbtn" title="Voir" wire:click="selectionner({{ $t['id'] }})"><x-filament::icon icon="heroicon-m-eye" /></button>
                                                @if ($t['peutDemarrer'])
                                                    <button type="button" class="ta-iconbtn ta-iconbtn--go" title="Démarrer" wire:click="demarrer({{ $t['id'] }})"><x-filament::icon icon="heroicon-m-play" /></button>
                                                @endif
                                                @if ($t['peutTerminer'])
                                                    <button type="button" class="ta-iconbtn ta-iconbtn--ok" title="Terminer" wire:click="terminer({{ $t['id'] }})"><x-filament::icon icon="heroicon-m-check" /></button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="ta-empty">Aucune tâche ne correspond à ces filtres.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{-- Pagination --}}
                    <div class="ta-pagination">
                        <span>{{ $liste['debut'] }}–{{ $liste['fin'] }} sur {{ $liste['total'] }} tâches</span>
                        <div class="ta-pages">
                            <button type="button" class="ta-pagebtn" wire:click="allerPage({{ $liste['page'] - 1 }})" @disabled($liste['page'] <= 1)>‹</button>
                            @foreach (range(1, $liste['pages']) as $p)
                                <button type="button" class="ta-pagebtn {{ $p === $liste['page'] ? 'is-active' : '' }}" wire:click="allerPage({{ $p }})">{{ $p }}</button>
                            @endforeach
                            <button type="button" class="ta-pagebtn" wire:click="allerPage({{ $liste['page'] + 1 }})" @disabled($liste['page'] >= $liste['pages'])>›</button>
                        </div>
                        <label class="ta-with-icon">Afficher
                            <select class="ta-select" style="width:auto" wire:model.live="parPage">
                                <option value="5">5</option><option value="10">10</option><option value="25">25</option>
                            </select>
                        </label>
                    </div>
                </div>
            @else
                {{-- Vue Kanban plein --}}
                <div class="ta-card">
                    <div class="ta-cardhead">Vue Kanban</div>
                    @include('filament.resources.tasks.pages.partials.kanban', ['kanban' => $d['kanban'], 'badge' => $badge, 'detailId' => $d['detailId']])
                </div>
            @endif

            {{-- Kanban rapide (uniquement en vue liste) --}}
            @if ($vue === 'liste')
                <div class="ta-card">
                    <div class="ta-cardhead"><span class="ta-with-icon"><x-filament::icon icon="heroicon-m-view-columns" /> Vue Kanban rapide</span></div>
                    @include('filament.resources.tasks.pages.partials.kanban', ['kanban' => $d['kanban'], 'badge' => $badge, 'detailId' => $d['detailId']])
                </div>
            @endif
        </div>

        {{-- Colonne droite : détail + alertes auto --}}
        <div class="ta-right">
            <div class="ta-card">
                <div class="ta-cardhead">Détail de la tâche</div>
                @if ($detail)
                    <div class="ta-detail">
                        <div class="ta-detail-title">{{ $detail['titre'] }}</div>
                        <dl class="ta-fields">
                            <dt>Statut</dt><dd><span class="ta-badge {{ $badge[$detail['statut']->getColor()] }}">{{ $detail['statut']->getLabel() }}</span></dd>
                            <dt>Priorité</dt><dd><span class="ta-badge {{ $badge[$detail['priorite']->getColor()] }}">{{ $detail['priorite']->getLabel() }}</span></dd>
                            <dt>Responsable</dt><dd>{{ $detail['responsable'] }}</dd>
                            <dt>Date limite</dt><dd>{{ $detail['echeanceLabel'] }}</dd>
                            <dt>Module lié</dt><dd>{{ $detail['module'] }}</dd>
                            <dt>Dossier lié</dt><dd>
                                @if ($detail['dossierUrl'] && $detail['dossierLabel'])
                                    <a href="{{ $detail['dossierUrl'] }}" class="ta-link" wire:navigate>{{ $detail['dossierLabel'] }}</a>
                                @else {{ $detail['dossierLabel'] ?? '—' }} @endif
                            </dd>
                        </dl>

                        @if ($detail['description'])
                            <div>
                                <div class="ta-section-title"><x-filament::icon icon="heroicon-m-bars-3-bottom-left" /> Description</div>
                                <p class="ta-desc">{{ $detail['description'] }}</p>
                            </div>
                        @endif

                        @if (! empty($detail['historique']))
                            <div>
                                <div class="ta-section-title"><x-filament::icon icon="heroicon-m-clock" /> Historique</div>
                                <div class="ta-timeline">
                                    @foreach ($detail['historique'] as $h)
                                        <div class="ta-tl-item">
                                            <span class="ta-tl-dot"></span>
                                            <span><span class="ta-tl-date">{{ $h['date']->format('d/m/Y H:i') }}</span> — <span class="ta-tl-text">{{ $h['texte'] }}</span></span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div>
                            <div class="ta-section-title"><x-filament::icon icon="heroicon-m-chat-bubble-left-right" /> Commentaires</div>
                            {{-- TODO(métier) : brancher un vrai fil de commentaires (table dédiée) quand il existera. --}}
                            <div class="ta-comment ta-muted">Aucun commentaire pour le moment.</div>
                        </div>

                        <div class="ta-detail-actions">
                            @if ($detail['dossierUrl'])
                                <a href="{{ $detail['dossierUrl'] }}" class="ta-btn ta-btn--primary" wire:navigate><x-filament::icon icon="heroicon-m-folder-open" /> Ouvrir le dossier lié</a>
                            @endif
                            @if ($detail['reassignerUrl'])
                                <a href="{{ $detail['reassignerUrl'] }}" class="ta-btn" wire:navigate><x-filament::icon icon="heroicon-m-user-plus" /> Réassigner</a>
                            @endif
                            <button type="button" class="ta-btn" wire:click="reporter({{ $detail['id'] }})"><x-filament::icon icon="heroicon-m-calendar" /> Reporter</button>
                            @if ($detail['peutTerminer'])
                                <button type="button" class="ta-btn" style="color:#16a34a;border-color:#bbf7d0" wire:click="terminer({{ $detail['id'] }})"><x-filament::icon icon="heroicon-m-check-circle" /> Marquer comme terminé</button>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="ta-empty">Sélectionnez une tâche pour voir son détail.</div>
                @endif
            </div>

            {{-- 11 · Alertes automatiques --}}
            <div class="ta-card">
                <div class="ta-cardhead"><span class="ta-with-icon"><x-filament::icon icon="heroicon-m-bell-alert" /> Alertes automatiques</span></div>
                <div class="ta-alerts">
                    @forelse ($d['alertesAuto'] as $a)
                        <div class="ta-alert" wire:click="choisirOnglet('alertes-systeme')">
                            <span class="ta-alert-ico"><x-filament::icon :icon="$a['icone']" /></span>
                            <span class="ta-alert-label">{{ $a['label'] }}</span>
                            <span class="ta-alert-count">{{ $a['total'] }}</span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="ta-alert-arrow" />
                        </div>
                    @empty
                        <div class="ta-empty">Aucune alerte automatique active. 🎉</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
