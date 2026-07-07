<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Entreprise partenaire') — CFA</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-slate-50 text-slate-800 antialiased">
    <header class="relative overflow-hidden text-white">
        <img src="{{ asset('partenaire.jfif') }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900/90 via-indigo-800/85 to-indigo-700/90"></div>
        <div class="relative mx-auto max-w-3xl px-4 pb-16 pt-12 text-center sm:pt-16">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide ring-1 ring-white/20 backdrop-blur">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M4.5 2.25a.75.75 0 0 0 0 1.5v16.5h-.75a.75.75 0 0 0 0 1.5h16.5a.75.75 0 0 0 0-1.5h-.75V3.75a.75.75 0 0 0 0-1.5h-15ZM9 6a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm-.75 3.75A.75.75 0 0 1 9 9h1.5a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1-.75-.75ZM9 12a.75.75 0 0 0 0 1.5h1.5a.75.75 0 0 0 0-1.5H9Zm3.75-5.25A.75.75 0 0 1 13.5 6H15a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75ZM13.5 9a.75.75 0 0 0 0 1.5H15A.75.75 0 0 0 15 9h-1.5Zm-.75 3.75a.75.75 0 0 1 .75-.75H15a.75.75 0 0 1 0 1.5h-1.5a.75.75 0 0 1-.75-.75ZM9 19.5v-2.25a.75.75 0 0 1 .75-.75h4.5a.75.75 0 0 1 .75.75v2.25a.75.75 0 0 1-.75.75h-4.5A.75.75 0 0 1 9 19.5Z" clip-rule="evenodd" /></svg>
                CFA · Recrutez un(e) alternant(e)
            </span>
            <h1 class="mt-5 text-3xl font-extrabold tracking-tight sm:text-4xl">@yield('heading', 'Devenez entreprise partenaire')</h1>
            <p class="mx-auto mt-3 max-w-xl text-indigo-100">@yield('subheading')</p>
        </div>
    </header>

    <div class="relative z-10 mx-auto -mt-10 max-w-3xl px-4 pb-12">
        <main class="rounded-2xl bg-white p-6 shadow-xl shadow-indigo-900/5 ring-1 ring-slate-900/5 sm:p-8">
            @yield('content')
        </main>
        <footer class="mt-6 text-center text-xs text-slate-400">
            Vos données servent uniquement à établir le partenariat avec notre CFA.
        </footer>
    </div>
</body>
</html>
