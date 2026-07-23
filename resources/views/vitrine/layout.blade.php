{{--
    Layout du site vitrine public de Meridian CFA.

    Distinct du panneau ERP (Filament) et des formulaires publics : c'est la
    page commerciale, servie sur `/`. Aucune donnée sensible, aucune session.
    Charte SaaS (indigo), police système, animations légères et respectueuses
    de prefers-reduced-motion.
--}}
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Meridian CFA — Le logiciel de gestion tout-en-un pour votre CFA')</title>
    <meta name="description" content="@yield('description', 'Meridian CFA centralise candidats, entreprises, contrats d\'apprentissage, formations, financements et suivi OPCO dans un ERP conçu pour les CFA et organismes de formation.')">

    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="robots" content="index, follow">

    {{-- Open Graph / réseaux sociaux --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Meridian CFA">
    <meta property="og:title" content="@yield('og_title', 'Meridian CFA — l\'ERP conçu pour les CFA')">
    <meta property="og:description" content="@yield('description', 'Pilotez votre CFA depuis une seule plateforme : candidats, entreprises, contrats, formations, financements et obligations administratives.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="fr_FR">
    <meta name="twitter:card" content="summary_large_image">

    {{-- Données structurées JSON-LD (logiciel SaaS, aucun chiffre inventé).
         Les clés « context » / « type » sont préfixées d'un double arobase :
         Blade le rend en simple arobase littéral et n'y voit pas ses directives. --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "SoftwareApplication",
        "name": "Meridian CFA",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "description": "ERP de gestion pour CFA et organismes de formation : candidats, entreprises, contrats d'apprentissage, OPCO, formations, scolarité, finance et pilotage.",
        "offers": { "@@type": "Offer", "category": "SaaS" }
    }
    </script>

    @vite(['resources/css/app.css'])

    <style>
        /* Révélation douce au défilement — neutralisée si l'utilisateur a
           demandé moins d'animations (prefers-reduced-motion). */
        [data-reveal] { opacity: 0; transform: translateY(16px); transition: opacity .6s ease, transform .6s ease; }
        [data-reveal].is-visible { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            [data-reveal] { opacity: 1; transform: none; transition: none; }
        }
    </style>
</head>
<body class="min-h-screen bg-white text-slate-700 antialiased selection:bg-indigo-100 selection:text-indigo-900">

    {{-- Lien d'évitement (accessibilité clavier) --}}
    <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-indigo-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">
        Aller au contenu
    </a>

    @include('vitrine.partials.header')

    <main id="contenu">
        @yield('content')
    </main>

    @include('vitrine.partials.footer')

    <script>
        // ── Interactions de la vitrine, en JavaScript vanilla (pas d'Alpine sur
        //    cette page autonome). Tout est délégué et sans dépendance. ──

        // 1. Révélation au défilement, désactivée si mouvement réduit.
        (function () {
            var reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var cibles = document.querySelectorAll('[data-reveal]');
            if (reduit || !('IntersectionObserver' in window)) {
                cibles.forEach(function (el) { el.classList.add('is-visible'); });
                return;
            }
            var obs = new IntersectionObserver(function (entrees) {
                entrees.forEach(function (e) {
                    if (e.isIntersecting) { e.target.classList.add('is-visible'); obs.unobserve(e.target); }
                });
            }, { threshold: 0.12 });
            cibles.forEach(function (el) { obs.observe(el); });
        })();

        // 2. Menu burger (mobile).
        (function () {
            var btn = document.querySelector('[data-menu-btn]');
            var menu = document.querySelector('[data-menu]');
            if (!btn || !menu) return;
            var bars = document.querySelector('[data-menu-icon="bars"]');
            var close = document.querySelector('[data-menu-icon="close"]');

            function afficher(ouvrir) {
                menu.hidden = !ouvrir;
                btn.setAttribute('aria-expanded', ouvrir ? 'true' : 'false');
                if (bars) bars.classList.toggle('hidden', ouvrir);
                if (close) close.classList.toggle('hidden', !ouvrir);
            }
            btn.addEventListener('click', function () { afficher(menu.hidden); });
            // Fermer après un clic sur un lien du menu, ou sur Échap.
            menu.querySelectorAll('[data-menu-link]').forEach(function (a) {
                a.addEventListener('click', function () { afficher(false); });
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') afficher(false); });
        })();

        // 3. Onglets « Pour qui ? » (rôles) — boutons [data-tab] / panneaux [data-panel].
        (function () {
            var groupes = document.querySelectorAll('[data-tabs]');
            groupes.forEach(function (groupe) {
                var boutons = groupe.querySelectorAll('[data-tab]');
                var panneaux = groupe.querySelectorAll('[data-panel]');
                boutons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var cle = btn.getAttribute('data-tab');
                        boutons.forEach(function (b) {
                            var actif = b === btn;
                            b.setAttribute('aria-selected', actif ? 'true' : 'false');
                            b.classList.toggle('est-actif', actif);
                        });
                        panneaux.forEach(function (p) {
                            p.hidden = p.getAttribute('data-panel') !== cle;
                        });
                    });
                });
            });
        })();

        // 4. Accordéon FAQ — boutons [data-accordion-btn] contrôlant [data-accordion-panel].
        (function () {
            document.querySelectorAll('[data-accordion-btn]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var panneau = document.getElementById(btn.getAttribute('aria-controls'));
                    var ouvert = btn.getAttribute('aria-expanded') === 'true';
                    btn.setAttribute('aria-expanded', ouvert ? 'false' : 'true');
                    if (panneau) panneau.hidden = ouvert;
                    var chevron = btn.querySelector('[data-chevron]');
                    if (chevron) chevron.classList.toggle('rotate-180', !ouvert);
                });
            });
        })();
    </script>
</body>
</html>