<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Candidature') — CFA</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-slate-50 text-slate-800 antialiased">
    {{-- Hero « Rejoignez-nous » (image de fond + dégradé pour la lisibilité) --}}
    <header class="relative overflow-hidden text-white">
        <img src="{{ asset('Rejoignez-nous2.jpg') }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-700/90 via-indigo-700/80 to-violet-700/90"></div>
        <div class="relative mx-auto max-w-3xl px-4 pb-16 pt-12 text-center sm:pt-16">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-white/20 backdrop-blur">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M11.7 2.805a.75.75 0 0 1 .6 0A60.65 60.65 0 0 1 22.83 8.72a.75.75 0 0 1-.231 1.337 49.949 49.949 0 0 0-9.902 3.912l-.003.002c-.114.06-.23.119-.346.18a.75.75 0 0 1-.7 0A50.88 50.88 0 0 0 7.5 12.173v-.224c0-.131.067-.248.172-.311a54.615 54.615 0 0 1 4.653-2.52.75.75 0 0 0-.65-1.352 56.123 56.123 0 0 0-4.78 2.589 1.858 1.858 0 0 0-.859 1.228 49.803 49.803 0 0 0-4.634-1.527.75.75 0 0 1-.231-1.337A60.653 60.653 0 0 1 11.7 2.805Z" /></svg>
                CFA · Formation en alternance
            </span>
            <h1 class="mt-5 text-3xl font-extrabold tracking-tight sm:text-4xl">@yield('heading', 'Rejoignez-nous')</h1>
            <p class="mx-auto mt-3 max-w-xl text-indigo-100">@yield('subheading')</p>
        </div>
    </header>

    <div class="relative z-10 mx-auto -mt-10 max-w-3xl px-4 pb-12">
        <main class="rounded-2xl bg-white p-6 shadow-xl shadow-indigo-900/5 ring-1 ring-slate-900/5 sm:p-8">
            @yield('content')
        </main>
        <footer class="mt-6 text-center text-xs text-slate-400">
            Vos données servent uniquement au traitement de votre candidature.
        </footer>
    </div>
</body>
</html>
