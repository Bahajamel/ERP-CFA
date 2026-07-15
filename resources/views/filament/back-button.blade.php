@php
    // Lien « Retour à la liste » déterministe : on dérive la route index de la
    // ressource à partir de la route courante (…resources.candidates.edit →
    // …resources.candidates.index). Repli sur l'historique si non résolue.
    $current = request()->route()?->getName();
    $backUrl = null;

    if ($current && str_contains($current, '.resources.')) {
        $indexRoute = preg_replace('/\.[^.]+$/', '.index', $current);

        if (\Illuminate\Support\Facades\Route::has($indexRoute)) {
            $backUrl = route($indexRoute);
        }
    }
@endphp

<div class="cfa-back">
    <a
        href="{{ $backUrl ?? '#' }}"
        @unless ($backUrl) x-data x-on:click.prevent="window.history.back()" @endunless
        class="cfa-back-btn"
        title="Revenir à la liste"
    >
        <span class="cfa-back-ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        </span>
        <span class="cfa-back-tx">Retour à la liste</span>
    </a>
</div>

<style>
    .cfa-back { margin-bottom: 1.15rem; }
    .cfa-back-btn {
        display: inline-flex; align-items: center; gap: .55rem;
        padding: .35rem .85rem .35rem .4rem; border-radius: 9999px;
        text-decoration: none; font-size: .82rem; font-weight: 600; line-height: 1;
        color: #475569; background: #fff; border: 1px solid rgba(15,23,42,.08);
        box-shadow: 0 1px 2px rgba(15,23,42,.05);
        transition: color .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .cfa-back-btn:hover { color: #4f46e5; border-color: rgba(79,70,229,.35); box-shadow: 0 6px 16px -6px rgba(79,70,229,.45); }
    .cfa-back-ic {
        display: grid; place-items: center; width: 1.6rem; height: 1.6rem; flex: none;
        border-radius: 9999px; background: #f1f5f9; color: #475569;
        transition: background .18s ease, color .18s ease, transform .18s ease;
    }
    .cfa-back-ic svg { width: .95rem; height: .95rem; }
    .cfa-back-btn:hover .cfa-back-ic { background: #4f46e5; color: #fff; transform: translateX(-2px); }

    .dark .cfa-back-btn { color: #cbd5e1; background: #18202f; border-color: rgba(255,255,255,.1); box-shadow: 0 1px 2px rgba(0,0,0,.25); }
    .dark .cfa-back-btn:hover { color: #c4b5fd; border-color: rgba(129,140,248,.45); box-shadow: 0 6px 16px -6px rgba(99,102,241,.5); }
    .dark .cfa-back-ic { background: #263041; color: #cbd5e1; }
    .dark .cfa-back-btn:hover .cfa-back-ic { background: #6366f1; color: #fff; }
</style>
