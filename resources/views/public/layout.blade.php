<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Formulaire') — CFA</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-slate-50 text-slate-800 antialiased">
    {{-- Hero dégradé (même charte que les formulaires publics existants). --}}
    <header class="relative overflow-hidden text-white">
        <img src="{{ asset('Rejoignez-nous2.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-700/90 via-indigo-700/80 to-violet-700/90"></div>
        <div class="relative mx-auto max-w-3xl px-4 pb-16 pt-12 text-center sm:pt-16">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-white/20 backdrop-blur">
                @yield('badge', 'CFA')
            </span>
            <h1 class="mt-5 text-3xl font-extrabold tracking-tight sm:text-4xl">@yield('heading', 'Formulaire')</h1>
            <p class="mx-auto mt-3 max-w-xl text-indigo-100">@yield('subheading')</p>
        </div>
    </header>

    <div class="relative z-10 mx-auto -mt-10 max-w-3xl px-4 pb-12">
        <main class="rounded-2xl bg-white p-6 shadow-xl shadow-indigo-900/5 ring-1 ring-slate-900/5 sm:p-8">
            @yield('content')
        </main>
        <footer class="mt-6 text-center text-xs text-slate-400">
            @yield('footer', 'Vos informations servent uniquement au traitement de votre demande.')
        </footer>
    </div>
</body>
</html>
