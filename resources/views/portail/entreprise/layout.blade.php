@php
    use App\Support\IdentiteVisuelle;

    /** @var \App\Models\Organisation $cfa */
    /** @var \App\Models\Company $company */
    $logo = $cfa ? IdentiteVisuelle::logoDataUri($cfa) : null;
    $variables = $cfa ? IdentiteVisuelle::variablesCss($cfa) : '';
    $nomCfa = $cfa?->designation() ?? 'Espace entreprise';

    $onglets = [
        'accueil' => ['Accueil', 'home', route('portail.entreprise', ['token' => $token])],
        'alternants' => ['Mes alternants', 'users', route('portail.entreprise.alternants', ['token' => $token])],
        'documents' => ['Documents', 'folder', route('portail.entreprise.documents', ['token' => $token])],
        'factures' => ['Factures', 'banknotes', route('portail.entreprise.factures', ['token' => $token])],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Espace entreprise') — {{ $nomCfa }}</title>
    @vite(['resources/css/app.css'])
    <style>
        :root{ {!! $variables !!} }
        .pa-accent{ color:var(--primary-600); }
        .pa-accent-bg{ background-color:var(--primary-600); }
        .pa-hero{
            background-image:
                radial-gradient(circle at 88% 18%, color-mix(in oklab, var(--primary-400) 45%, transparent) 0, transparent 42%),
                linear-gradient(135deg, var(--primary-700), var(--primary-900));
        }
        .pa-dots{
            background-image: radial-gradient(rgba(255,255,255,.35) 1.1px, transparent 1.1px);
            background-size: 16px 16px;
        }
    </style>
</head>
<body class="min-h-full bg-slate-50 text-slate-800 antialiased">
    <header class="pa-hero relative overflow-hidden text-white">
        <div class="pa-dots pointer-events-none absolute right-0 top-0 h-40 w-72 opacity-40"></div>

        <div class="relative mx-auto flex max-w-5xl items-center gap-4 px-4 pt-6 pb-4 sm:px-6">
            <span class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white/95 shadow-lg shadow-black/10 ring-1 ring-white/40">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $nomCfa }}" class="h-full w-full object-contain p-1.5">
                @else
                    <span class="pa-accent text-lg font-black">{{ mb_strtoupper(mb_substr($nomCfa, 0, 1)) }}</span>
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-semibold uppercase tracking-[0.18em] text-white/70">{{ mb_strtoupper($nomCfa) }} · Espace entreprise</p>
                <h1 class="truncate text-xl font-bold leading-tight sm:text-2xl">{{ $company->raison_sociale }}</h1>
            </div>
            <span class="hidden items-center gap-1.5 rounded-full bg-white/10 py-1.5 pl-1.5 pr-3 ring-1 ring-white/20 sm:inline-flex">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-white/20 text-sm font-bold">{{ $company->initiales }}</span>
                @include('portail.apprenant._icon', ['name' => 'chevron-down', 'class' => 'h-4 w-4 text-white/70'])
            </span>
        </div>

        <nav class="relative mx-auto max-w-5xl px-3 pb-3 sm:px-5">
            <div class="flex gap-1.5 overflow-x-auto">
                @foreach ($onglets as $cle => [$libelle, $icone, $url])
                    <a href="{{ $url }}"
                       @class([
                           'inline-flex items-center gap-2 whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition',
                           'bg-white text-slate-900 shadow-sm' => $actif === $cle,
                           'text-white/80 hover:bg-white/10 hover:text-white' => $actif !== $cle,
                       ])>
                        @include('portail.apprenant._icon', ['name' => $icone, 'class' => 'h-5 w-5'])
                        {{ $libelle }}
                    </a>
                @endforeach
            </div>
        </nav>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-7 sm:px-6">
        @if (session('erreur'))
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ session('erreur') }}
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto max-w-5xl px-4 pb-10 pt-2">
        <p class="flex items-center justify-center gap-1.5 text-center text-xs text-slate-400">
            @include('portail.apprenant._icon', ['name' => 'lock', 'class' => 'h-3.5 w-3.5'])
            Espace entreprise — {{ $nomCfa }}. Ce lien vous est réservé, ne le partagez pas.
        </p>
    </footer>
</body>
</html>