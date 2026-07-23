{{--
    Logo Meridian CFA : symbole géométrique (arc de méridien) + nom.
    @param string $ton  'sombre' (texte foncé, défaut) ou 'clair' (texte blanc, pour le footer)
--}}
@php($ton = $ton ?? 'sombre')
<span class="inline-flex items-center gap-2.5">
    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 shadow-sm">
        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="white" stroke-width="1.6" opacity="0.55"/>
            <path d="M12 3a9 9 0 0 1 0 18" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M3 12h18" stroke="white" stroke-width="1.6" stroke-linecap="round" opacity="0.9"/>
        </svg>
    </span>
    <span class="text-lg font-bold tracking-tight {{ $ton === 'clair' ? 'text-white' : 'text-slate-900' }}">
        Meridian<span class="text-indigo-500"> CFA</span>
    </span>
</span>