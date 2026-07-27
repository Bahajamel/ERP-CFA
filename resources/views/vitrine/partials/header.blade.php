{{--
    En-tête sticky de la vitrine. Navigation par ancres + accès à l'ERP existant.
    Menu burger accessible en JavaScript vanilla (pas d'Alpine sur cette page
    autonome). Le bouton d'accès s'adapte : « Mon espace » si déjà connecté.
--}}
@php
    $liens = [
        '#solution' => 'Solution',
        '#fonctionnalites' => 'Fonctionnalités',
        '#pour-qui' => 'Pour qui ?',
        '#securite' => 'Sécurité',
        '#faq' => 'FAQ',
        '#demonstration' => 'Contact',
    ];
    $connecte = auth()->check();
    $urlEspace = $connecte ? url('/admin') : route('filament.admin.auth.login');
    $labelEspace = $connecte ? 'Mon espace' : 'Se connecter';
@endphp

<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/85 backdrop-blur" data-menu-root>
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8" aria-label="Navigation principale">
        <a href="/" class="shrink-0" aria-label="Meridian CFA — accueil">
            @include('vitrine.partials.logo')
        </a>

        {{-- Liens (desktop) --}}
        <div class="hidden items-center gap-1 lg:flex">
            @foreach ($liens as $ancre => $libelle)
                <a href="{{ $ancre }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">{{ $libelle }}</a>
            @endforeach
        </div>

        {{-- Actions (desktop) --}}
        <div class="hidden items-center gap-2.5 lg:flex">
            <a href="{{ $urlEspace }}" class="rounded-lg px-3.5 py-2 text-sm font-semibold text-slate-700 transition hover:text-indigo-600">{{ $labelEspace }}</a>
            <a href="#demonstration" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Demander une démonstration
            </a>
        </div>

        {{-- Burger (mobile) --}}
        <button type="button" data-menu-btn aria-controls="menu-mobile" aria-expanded="false" aria-label="Ouvrir le menu"
            class="inline-flex items-center justify-center rounded-lg p-2 text-slate-700 hover:bg-slate-100 lg:hidden">
            <svg data-menu-icon="bars" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
            <svg data-menu-icon="close" class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
    </nav>

    {{-- Menu mobile --}}
    <div id="menu-mobile" data-menu hidden class="border-t border-slate-200 bg-white lg:hidden">
        <div class="space-y-1 px-4 py-4 sm:px-6">
            @foreach ($liens as $ancre => $libelle)
                <a href="{{ $ancre }}" data-menu-link class="block rounded-lg px-3 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-100">{{ $libelle }}</a>
            @endforeach
            <div class="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3">
                <a href="{{ $urlEspace }}" class="rounded-lg px-3 py-2.5 text-center text-sm font-semibold text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ $labelEspace }}</a>
                <a href="#demonstration" data-menu-link class="rounded-lg bg-indigo-600 px-3 py-2.5 text-center text-sm font-semibold text-white hover:bg-indigo-500">Demander une démonstration</a>
            </div>
        </div>
    </div>
</header>