@php
    use App\Filament\Resources\Companies\CompanyResource;

    $record = $this->getRecord();
    $badge = ['gray' => 'ep-b-gray', 'info' => 'ep-b-info', 'primary' => 'ep-b-info', 'warning' => 'ep-b-warn', 'danger' => 'ep-b-danger', 'success' => 'ep-b-success'];
    $bcls = fn ($enum) => $badge[$enum?->getColor() ?? 'gray'] ?? 'ep-b-gray';
    $viewUrl = CompanyResource::getUrl('view', ['record' => $record]);
    $contact = $resume['contact'];
    $trashed = method_exists($record, 'trashed') && $record->trashed();
@endphp

<div class="ep-page" x-data="{ dirty: false }" x-on:input="dirty = true" x-on:change="dirty = true">
    <x-filament-actions::modals />
    <style>
        .ep-page { --ep-line: rgba(15,23,42,.08); display: flex; flex-direction: column; gap: 1rem; padding-bottom: 5rem; }
        .dark .ep-page { --ep-line: rgba(255,255,255,.09); }
        .ep-card { background: #fff; border: 1px solid var(--ep-line); border-radius: 1rem; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .dark .ep-card { background: #18202f; }

        /* En-tête */
        .ep-head { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; justify-content: space-between; }
        .ep-head .t { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; color: #0f172a; } .dark .ep-head .t { color: #f8fafc; }
        .ep-head .s { font-size: .85rem; color: #64748b; margin-top: .15rem; }
        .ep-head-actions { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }

        .ep-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; padding: .55rem .95rem; border-radius: .6rem;
            font-size: .84rem; font-weight: 600; border: 1px solid var(--ep-line); background: #fff; color: #334155; cursor: pointer; text-decoration: none; }
        .dark .ep-btn { background: #18202f; color: #cbd5e1; } .ep-btn:hover { background: #f8fafc; } .dark .ep-btn:hover { background: #1f2937; }
        .ep-btn svg { width: 1rem; height: 1rem; }
        .ep-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; } .ep-btn--primary:hover { background: #1d4ed8; }
        .ep-btn--danger { border-color: transparent; color: #b91c1c; background: transparent; } .ep-btn--danger:hover { background: #fef2f2; } .dark .ep-btn--danger { color: #fca5a5; } .dark .ep-btn--danger:hover { background: rgba(239,68,68,.12); }
        .ep-btn[disabled] { opacity: .6; cursor: not-allowed; }

        .ep-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 9999px; font-size: .72rem; font-weight: 700; }
        .ep-b-gray { background: #f1f5f9; color: #475569; } .dark .ep-b-gray { background: #334155; color: #cbd5e1; }
        .ep-b-info { background: #dbeafe; color: #1d4ed8; } .dark .ep-b-info { background: rgba(37,99,235,.25); color: #93c5fd; }
        .ep-b-warn { background: #ffedd5; color: #c2410c; } .dark .ep-b-warn { background: rgba(234,88,12,.22); color: #fdba74; }
        .ep-b-danger { background: #fee2e2; color: #b91c1c; } .dark .ep-b-danger { background: rgba(239,68,68,.2); color: #fca5a5; }
        .ep-b-success { background: #dcfce7; color: #15803d; } .dark .ep-b-success { background: rgba(34,197,94,.2); color: #86efac; }

        /* Grille */
        .ep-grid { display: grid; grid-template-columns: 320px 1fr; gap: 1rem; align-items: start; }
        .ep-col { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }

        /* Carte résumé */
        .ep-summary { position: sticky; top: 1rem; padding: 1.4rem; text-align: center; }
        .ep-avatar { width: 6rem; height: 6rem; border-radius: 1.25rem; display: grid; place-items: center; margin: 0 auto .9rem;
            background: linear-gradient(135deg,#0ea5e9,#2563eb); color: #fff; font-size: 1.7rem; font-weight: 800; letter-spacing: .02em; }
        .ep-summary .nm { font-size: 1.2rem; font-weight: 800; color: #0f172a; } .dark .ep-summary .nm { color: #f8fafc; }
        .ep-summary .st { margin-top: .5rem; }
        .ep-sep { height: 1px; background: var(--ep-line); margin: 1.1rem 0; }
        .ep-info { display: flex; flex-direction: column; gap: .75rem; text-align: left; }
        .ep-info-row { display: flex; gap: .6rem; align-items: flex-start; }
        .ep-info-row svg { width: 1.1rem; height: 1.1rem; color: #94a3b8; margin-top: .1rem; flex: none; }
        .ep-info-row .l { font-size: .72rem; color: #94a3b8; } .ep-info-row .v { font-size: .85rem; font-weight: 600; color: #1e293b; } .dark .ep-info-row .v { color: #e2e8f0; }
        .ep-stats { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; }
        .ep-stat { border: 1px solid var(--ep-line); border-radius: .7rem; padding: .6rem; text-align: center; }
        .ep-stat .n { font-size: 1.3rem; font-weight: 800; color: #0f172a; line-height: 1; } .dark .ep-stat .n { color: #f8fafc; }
        .ep-stat .l { font-size: .68rem; color: #94a3b8; margin-top: .25rem; }
        .ep-hint { display: flex; gap: .5rem; align-items: flex-start; margin-top: 1rem; padding: .7rem .8rem; border-radius: .7rem;
            background: #eff6ff; border: 1px solid #dbeafe; font-size: .78rem; color: #1e40af; text-align: left; }
        .dark .ep-hint { background: rgba(37,99,235,.12); border-color: rgba(37,99,235,.3); color: #93c5fd; }
        .ep-hint svg { width: 1rem; height: 1rem; flex: none; margin-top: .1rem; }

        /* Carte formulaire */
        .ep-form { padding: 1.4rem; }

        /* Barre sticky */
        .ep-sticky { position: sticky; bottom: 0; z-index: 20; margin-top: .25rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
            padding: .8rem 1.1rem; background: rgba(255,255,255,.92); backdrop-filter: blur(8px);
            border: 1px solid var(--ep-line); border-radius: .9rem; box-shadow: 0 -4px 16px rgba(15,23,42,.06); }
        .dark .ep-sticky { background: rgba(24,32,47,.92); }
        .ep-sticky .msg { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: #64748b; }
        .ep-sticky .msg svg { width: 1rem; height: 1rem; }
        .ep-sticky .msg .warn { color: #c2410c; font-weight: 600; } .dark .ep-sticky .msg .warn { color: #fdba74; }

        @media (max-width: 1024px) { .ep-grid { grid-template-columns: 1fr; } .ep-summary { position: static; } }
    </style>

    {{-- En-tête --}}
    <div class="ep-head">
        <div>
            <div class="t">Modifier les informations</div>
            <div class="s">Mettez à jour les informations principales de l'entreprise partenaire.</div>
        </div>
        <div class="ep-head-actions">
            @if ($trashed)
                <button type="button" class="ep-btn" wire:click="mountAction('restore')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg> Restaurer
                </button>
                <button type="button" class="ep-btn ep-btn--danger" wire:click="mountAction('forceDelete')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg> Supprimer définitivement
                </button>
            @else
                <button type="button" class="ep-btn ep-btn--danger" wire:click="mountAction('delete')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg> Supprimer
                </button>
            @endif
            <a href="{{ $viewUrl }}" class="ep-btn">Annuler</a>
            <button type="button" class="ep-btn ep-btn--primary" wire:click="save" wire:target="save" wire:loading.attr="disabled" x-on:click="dirty = false">
                <svg wire:loading.remove wire:target="save" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                <span wire:loading.remove wire:target="save">Enregistrer les modifications</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </div>

    <div class="ep-grid">
        {{-- Colonne gauche : carte résumé --}}
        <div class="ep-col">
            <div class="ep-card ep-summary">
                <span class="ep-avatar">{{ $record->initiales }}</span>
                <div class="nm">{{ $record->raison_sociale }}</div>
                <div class="st"><span class="ep-badge {{ $bcls($record->statut) }}">{{ $record->statut?->getLabel() }}</span></div>

                <div class="ep-sep"></div>

                <div class="ep-info">
                    <div class="ep-info-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M9 6h.01M15 6h.01M9 10h.01M15 10h.01"/></svg><div><div class="l">Secteur</div><div class="v">{{ $resume['secteur'] ?: '—' }}</div></div></div>
                    <div class="ep-info-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg><div><div class="l">SIRET</div><div class="v">{{ $resume['siret'] ?: '—' }}</div></div></div>
                    <div class="ep-info-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg><div><div class="l">OPCO</div><div class="v">{{ $resume['opco'] ?? '—' }}</div></div></div>
                    <div class="ep-info-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg><div><div class="l">Contact principal</div><div class="v">{{ $contact ? $contact->nom_complet.($contact->fonction ? ' · '.$contact->fonction : '') : '—' }}</div></div></div>
                    <div class="ep-info-row"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><div><div class="l">Dernière mise à jour</div><div class="v">{{ $resume['majLe']?->diffForHumans() ?? '—' }}</div></div></div>
                </div>

                <div class="ep-sep"></div>

                <div class="ep-stats">
                    <div class="ep-stat"><div class="n">{{ $resume['besoinsOuverts'] }}</div><div class="l">Besoins ouverts</div></div>
                    <div class="ep-stat"><div class="n">{{ $resume['contrats'] }}</div><div class="l">Contrats</div></div>
                </div>

                <div class="ep-hint">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    <span>La modification du SIRET peut déclencher la détection automatique de l'OPCO.</span>
                </div>
            </div>
        </div>

        {{-- Colonne droite : formulaire Filament intact --}}
        <div class="ep-col">
            <div class="ep-card ep-form">
                {{ $this->form }}
            </div>
        </div>
    </div>

    {{-- Barre d'action sticky --}}
    <div class="ep-sticky">
        <div class="msg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span x-show="!dirty">Vos modifications sont enregistrées après validation.</span>
            <span class="warn" x-show="dirty" x-cloak>Modifications non enregistrées</span>
        </div>
        <div class="ep-head-actions">
            <a href="{{ $viewUrl }}" class="ep-btn">Annuler</a>
            <button type="button" class="ep-btn ep-btn--primary" wire:click="save" wire:target="save" wire:loading.attr="disabled" x-on:click="dirty = false">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </div>
</div>
