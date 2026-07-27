@php
    use App\Filament\Resources\Candidates\CandidateResource;

    $indexUrl = CandidateResource::getUrl('index');

    $icons = [
        'user' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
        'cap' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'pin' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
        'doc' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
    ];
@endphp

<div class="ep-page" x-data="{
        dirty: false,
        pct: 0,
        recompute() {
            const host = this.$root.querySelector('.ep-form-host');
            if (! host) return;
            const sel = 'input[type=text], input[type=email], input[type=tel], input[type=date], input[type=number], textarea';
            let filled = 0, total = 0;
            host.querySelectorAll(sel).forEach(f => {
                if (f.type === 'search' || f.getAttribute('role') === 'combobox' || f.closest('[role=listbox]')) return;
                total++;
                if ((f.value || '').trim() !== '') filled++;
            });
            this.pct = total ? Math.round(filled / total * 100) : 0;
        }
    }"
    x-init="$nextTick(() => recompute())"
    x-on:input="dirty = true; recompute()"
    x-on:change="dirty = true; recompute()">
    <x-filament-actions::modals />
    <style>
        .ep-page { --ep-line: rgba(15,23,42,.08); display: flex; flex-direction: column; gap: 1rem; padding-bottom: 5rem; }
        .dark .ep-page { --ep-line: rgba(255,255,255,.09); }
        .ep-card { background: #fff; border: 1px solid var(--ep-line); border-radius: 1rem; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .dark .ep-card { background: #18202f; }

        .ep-head { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; justify-content: space-between; }
        .ep-head .t { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; color: #0f172a; } .dark .ep-head .t { color: #f8fafc; }
        .ep-head .s { font-size: .85rem; color: #64748b; margin-top: .15rem; }
        .ep-head-actions { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }

        .ep-btn { display: inline-flex; align-items: center; justify-content: center; gap: .4rem; padding: .55rem .95rem; border-radius: .6rem;
            font-size: .84rem; font-weight: 600; border: 1px solid var(--ep-line); background: #fff; color: #334155; cursor: pointer; text-decoration: none; }
        .dark .ep-btn { background: #18202f; color: #cbd5e1; } .ep-btn:hover { background: #f8fafc; } .dark .ep-btn:hover { background: #1f2937; }
        .ep-btn svg { width: 1rem; height: 1rem; }
        .ep-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; } .ep-btn--primary:hover { background: #1d4ed8; }
        .ep-btn[disabled] { opacity: .6; cursor: not-allowed; }

        .ep-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 9999px; font-size: .72rem; font-weight: 700; }
        .ep-b-new { background: #ede9fe; color: #6d28d9; } .dark .ep-b-new { background: rgba(124,58,237,.25); color: #c4b5fd; }

        .ep-grid { display: grid; grid-template-columns: 320px 1fr; gap: 1rem; align-items: start; }
        .ep-col { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }

        /* Résumé du dossier */
        .ep-summary { position: sticky; top: 1rem; padding: 1.4rem; }
        .ep-summary .head { display: flex; align-items: center; gap: .5rem; font-weight: 700; font-size: .95rem; color: #0f172a; margin-bottom: 1rem; }
        .dark .ep-summary .head { color: #f1f5f9; } .ep-summary .head svg { width: 1.1rem; height: 1.1rem; color: #2563eb; }
        .ep-ava-wrap { text-align: center; }
        .ep-avatar { width: 5.5rem; height: 5.5rem; border-radius: 9999px; display: grid; place-items: center; margin: 0 auto .5rem;
            background: #eef2f7; color: #94a3b8; } .dark .ep-avatar { background: #263041; }
        .ep-avatar svg { width: 2.4rem; height: 2.4rem; }
        .ep-ava-wrap .nm { font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-top: .3rem; } .dark .ep-ava-wrap .nm { color: #f8fafc; }
        .ep-ava-wrap .st { margin-top: .4rem; }
        .ep-sep { height: 1px; background: var(--ep-line); margin: 1.1rem 0; }

        .ep-prog-head { display: flex; justify-content: space-between; font-size: .8rem; margin-bottom: .4rem; }
        .ep-prog-head .l { color: #475569; font-weight: 600; } .dark .ep-prog-head .l { color: #cbd5e1; } .ep-prog-head .p { color: #2563eb; font-weight: 800; }
        .ep-prog { height: .5rem; border-radius: 9999px; background: #e5e7eb; overflow: hidden; } .dark .ep-prog { background: #334155; }
        .ep-prog > span { display: block; height: 100%; border-radius: 9999px; background: linear-gradient(90deg,#2563eb,#7c3aed); transition: width .25s ease; }
        .ep-prog-note { font-size: .72rem; color: #94a3b8; margin-top: .35rem; }

        .ep-check { display: flex; flex-direction: column; gap: .1rem; }
        .ep-check-row { display: flex; align-items: center; gap: .6rem; padding: .55rem 0; border-bottom: 1px dashed var(--ep-line); }
        .ep-check-row:last-child { border-bottom: none; }
        .ep-check-row .ic { width: 1.7rem; height: 1.7rem; border-radius: .5rem; display: grid; place-items: center; flex: none; background: #f1f5f9; }
        .dark .ep-check-row .ic { background: #263041; } .ep-check-row .ic svg { width: 1rem; height: 1rem; color: #64748b; }
        .ep-check-row .lbl { font-size: .84rem; color: #334155; font-weight: 500; } .dark .ep-check-row .lbl { color: #e2e8f0; }
        .ep-check-row .stt { margin-left: auto; font-size: .72rem; font-weight: 600; color: #d97706; } .dark .ep-check-row .stt { color: #fbbf24; }

        .ep-hint { display: flex; gap: .5rem; align-items: flex-start; margin-top: 1rem; padding: .7rem .8rem; border-radius: .7rem;
            background: #eff6ff; border: 1px solid #dbeafe; font-size: .78rem; color: #1e40af; }
        .dark .ep-hint { background: rgba(37,99,235,.12); border-color: rgba(37,99,235,.3); color: #93c5fd; }
        .ep-hint svg { width: 1rem; height: 1rem; flex: none; margin-top: .1rem; }

        .ep-form { padding: 1.4rem; }

        .ep-sticky { position: sticky; bottom: 0; z-index: 20; margin-top: .25rem;
            display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
            padding: .8rem 1.1rem; background: rgba(255,255,255,.92); backdrop-filter: blur(8px);
            border: 1px solid var(--ep-line); border-radius: .9rem; box-shadow: 0 -4px 16px rgba(15,23,42,.06); }
        .dark .ep-sticky { background: rgba(24,32,47,.92); }
        .ep-sticky .msg { display: flex; align-items: center; gap: .5rem; font-size: .8rem; color: #64748b; }
        .ep-sticky .msg svg { width: 1rem; height: 1rem; }

        @media (max-width: 1024px) { .ep-grid { grid-template-columns: 1fr; } .ep-summary { position: static; } }
    </style>

    {{-- En-tête --}}
    <div class="ep-head">
        <div>
            <div class="t">Nouveau candidat</div>
            <div class="s">Renseignez les informations du candidat. Les champs marqués d'un <span style="color:#dc2626">*</span> sont obligatoires.</div>
        </div>
        <div class="ep-head-actions">
            <a href="{{ $indexUrl }}" class="ep-btn">Annuler</a>
            <button type="button" class="ep-btn ep-btn--primary" wire:click="create" wire:target="create" wire:loading.attr="disabled">
                <svg wire:loading.remove wire:target="create" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                <span wire:loading.remove wire:target="create">Enregistrer le candidat</span>
                <span wire:loading wire:target="create">Enregistrement…</span>
            </button>
        </div>
    </div>

    <div class="ep-grid">
        {{-- Colonne gauche : résumé du dossier --}}
        <div class="ep-col">
            <div class="ep-card ep-summary">
                <div class="head"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg> Résumé du dossier</div>

                <div class="ep-ava-wrap">
                    <span class="ep-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    <div class="nm">Nouveau candidat</div>
                    <div class="st"><span class="ep-badge ep-b-new">Nouveau</span></div>
                </div>

                <div class="ep-sep"></div>

                <div class="ep-prog-head"><span class="l">Complétude du dossier</span><span class="p" x-text="pct + ' %'">0 %</span></div>
                <div class="ep-prog"><span :style="'width:' + pct + '%'" style="width:0%"></span></div>
                <div class="ep-prog-note">Progression indicative pendant la saisie.</div>

                <div class="ep-sep"></div>

                <div class="ep-check">
                    @foreach ($sections as $s)
                        <div class="ep-check-row">
                            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$s['icon']] ?? '' !!}</svg></span>
                            <span class="lbl">{{ $s['label'] }}</span>
                            <span class="stt">À compléter</span>
                        </div>
                    @endforeach
                </div>

                <div class="ep-hint">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                    <span>Enregistrez le dossier pour commencer à suivre votre candidat.</span>
                </div>
            </div>
        </div>

        {{-- Colonne droite : formulaire Filament intact --}}
        <div class="ep-col">
            <div class="ep-card ep-form ep-form-host">
                {{ $this->form }}
            </div>
        </div>
    </div>

    {{-- Barre d'action sticky --}}
    <div class="ep-sticky">
        <div class="msg">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>Vos informations seront enregistrées après validation.</span>
        </div>
        <div class="ep-head-actions">
            <a href="{{ $indexUrl }}" class="ep-btn">Annuler</a>
            <button type="button" class="ep-btn ep-btn--primary" wire:click="create" wire:target="create" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="create">Enregistrer le candidat</span>
                <span wire:loading wire:target="create">Enregistrement…</span>
            </button>
        </div>
    </div>
</div>
