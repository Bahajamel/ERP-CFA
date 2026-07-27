{{--
    Page de connexion « Meridian CFA » — écran divisé premium.
    Gauche : hero marketing (dégradé, formes, icônes flottantes, bénéfices, badges).
    Droite : formulaire Filament réel via {{ $this->content }} (aucune logique modifiée).
    CSS scopé et inline : cette vue n'existe que sur le login, donc pas de fuite de style
    ni de rebuild Vite. Le login est forcé en thème clair (le reste de l'app garde le Dark).
--}}

{{-- Force le thème CLAIR uniquement sur le login, pour des champs nets sur fond blanc.
     Filament (Alpine) ré-applique « .dark » sur <html> au chargement selon la préférence
     stockée ; un MutationObserver le retire en continu tant que cette page est affichée.
     Aucune préférence globale n'est modifiée : quitter le login = rechargement complet
     qui restaure le thème Dark du reste de l'application. --}}

{{-- RACINE UNIQUE : Livewire exige un seul élément racine pour la page. Tout
     (script, style, contenu) est enveloppé ici, sinon le formulaire n'est pas
     relié et le bouton « Connexion » ne déclenche pas authenticate(). --}}
<div class="cfa-login-root">
<script>
    (function () {
        var html = document.documentElement;
        var strip = function () { if (html.classList.contains('dark')) html.classList.remove('dark'); };
        strip();
        html.style.colorScheme = 'light';
        new MutationObserver(strip).observe(html, { attributes: true, attributeFilter: ['class'] });
    })();
</script>

<style>
    /* Racine Livewire : transparente à la mise en page. */
    .cfa-login-root { min-height: 100dvh; }

    /* ── Neutralise la carte centrée du layout « simple » pour un plein écran ── */
    .fi-simple-layout,
    .fi-simple-main-ctn,
    .fi-simple-main {
        max-width: none !important;
        width: 100% !important;
        min-height: 100dvh;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        display: block !important;
    }

    .cfa-login {
        --cfa-navy: #0b1533;
        --cfa-blue: #1e40af;
        --cfa-bright: #2563eb;
        --cfa-cyan: #22d3ee;
        --cfa-indigo: #4f46e5;
        --cfa-ink: #0f172a;
        --cfa-muted: #64748b;
        --cfa-line: #e2e8f0;

        display: grid;
        /* minmax(0,…) : évite que le contenu (champs Filament) déborde la colonne */
        grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr);
        min-height: 100dvh;
        font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        color: var(--cfa-ink);
    }

    /* ══════════════════ COLONNE GAUCHE — HERO ══════════════════ */
    .cfa-hero {
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 2.5rem;
        padding: clamp(2.5rem, 4vw, 4.5rem);
        min-width: 0;
        color: #eaf1ff;
        background:
            radial-gradient(1200px 500px at 15% -10%, rgba(79, 70, 229, .45), transparent 60%),
            radial-gradient(900px 600px at 110% 110%, rgba(34, 211, 238, .30), transparent 55%),
            linear-gradient(150deg, #0b1220 0%, var(--cfa-navy) 38%, var(--cfa-blue) 78%, var(--cfa-bright) 120%);
        isolation: isolate;
    }

    /* Formes abstraites légères */
    .cfa-shape {
        position: absolute;
        border-radius: 999px;
        filter: blur(2px);
        opacity: .5;
        z-index: -1;
        pointer-events: none;
    }
    .cfa-shape--1 { top: -120px; right: -90px; width: 360px; height: 360px;
        background: radial-gradient(circle at 30% 30%, rgba(34, 211, 238, .55), transparent 70%); }
    .cfa-shape--2 { bottom: -140px; left: -100px; width: 420px; height: 420px;
        background: radial-gradient(circle at 60% 40%, rgba(99, 102, 241, .5), transparent 70%); }
    .cfa-hero::before {
        content: "";
        position: absolute; inset: 0; z-index: -1;
        background-image:
            linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px);
        background-size: 44px 44px;
        mask-image: radial-gradient(120% 80% at 50% 0%, #000 30%, transparent 75%);
    }

    /* Icônes flottantes discrètes */
    .cfa-float {
        position: absolute;
        color: rgba(255, 255, 255, .16);
        z-index: -1;
        animation: cfaFloat 7s ease-in-out infinite;
    }
    .cfa-float svg { width: 100%; height: 100%; }
    .cfa-float--a { top: 20%; right: 12%; width: 46px; height: 46px; animation-delay: 0s; }
    .cfa-float--b { top: 58%; right: 22%; width: 34px; height: 34px; animation-delay: 1.4s; }
    .cfa-float--c { top: 40%; left: 8%; width: 40px; height: 40px; animation-delay: .7s; }
    @keyframes cfaFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-14px); } }

    /* Marque en haut du hero */
    .cfa-hero-brand { display: flex; align-items: center; gap: .75rem; font-weight: 700; letter-spacing: .01em; }
    .cfa-mark {
        display: grid; place-items: center;
        width: 44px; height: 44px; border-radius: 13px;
        font-size: 1.25rem; font-weight: 800; color: #fff;
        background: linear-gradient(135deg, var(--cfa-cyan), var(--cfa-indigo));
        box-shadow: 0 8px 24px -6px rgba(34, 211, 238, .6);
    }
    .cfa-hero-brand .cfa-brand-txt { font-size: 1.15rem; }
    .cfa-hero-brand .cfa-brand-txt small { display: block; font-weight: 500; font-size: .72rem; color: rgba(234, 241, 255, .65); }

    .cfa-hero-body { max-width: 34rem; }
    .cfa-hero-title {
        font-size: clamp(1.9rem, 3vw, 2.9rem);
        line-height: 1.1; font-weight: 800; letter-spacing: -.02em; margin: 0 0 1rem;
    }
    .cfa-hero-title .cfa-accent {
        background: linear-gradient(90deg, #a5f3fc, #c7d2fe);
        -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .cfa-hero-sub { font-size: 1.02rem; line-height: 1.6; color: rgba(234, 241, 255, .82); margin: 0 0 2rem; }

    .cfa-benefits { display: grid; gap: .85rem; }
    .cfa-benefit {
        display: flex; align-items: flex-start; gap: .9rem;
        padding: .95rem 1.1rem; border-radius: 16px;
        background: rgba(255, 255, 255, .07);
        border: 1px solid rgba(255, 255, 255, .13);
        backdrop-filter: blur(10px);
    }
    .cfa-benefit-ico {
        flex: none; display: grid; place-items: center;
        width: 40px; height: 40px; border-radius: 11px;
        color: #ecfeff;
        background: linear-gradient(135deg, rgba(34, 211, 238, .35), rgba(79, 70, 229, .4));
        border: 1px solid rgba(255, 255, 255, .18);
    }
    .cfa-benefit-ico svg { width: 20px; height: 20px; }
    .cfa-benefit b { display: block; font-size: .95rem; margin-bottom: .12rem; }
    .cfa-benefit span { font-size: .84rem; line-height: 1.45; color: rgba(234, 241, 255, .72); }

    .cfa-badges { display: flex; flex-wrap: wrap; gap: .6rem; }
    .cfa-badge {
        display: inline-flex; align-items: center; gap: .4rem;
        font-size: .76rem; font-weight: 600; color: rgba(234, 241, 255, .9);
        padding: .4rem .75rem; border-radius: 999px;
        background: rgba(255, 255, 255, .08);
        border: 1px solid rgba(255, 255, 255, .16);
    }
    .cfa-badge svg { width: 14px; height: 14px; opacity: .9; }

    /* ══════════════════ COLONNE DROITE — FORMULAIRE ══════════════════ */
    .cfa-panel {
        /* align-items par défaut (stretch) : la carte occupe la largeur dispo puis
           est bornée par max-width — évite tout débordement sur petits écrans. */
        display: flex; flex-direction: column; justify-content: center;
        padding: clamp(2rem, 4vw, 3.5rem);
        background: #ffffff;
        position: relative;
        min-width: 0;
    }
    .cfa-form-wrap { width: 100%; max-width: 26rem; margin-inline: auto; box-sizing: border-box; animation: cfaFade .5s ease both; }
    /* Les colonnes de grille Filament ont min-width:auto → elles refusent de
       rétrécir sous leur contenu et débordent sur petit écran. On force une
       colonne fluide unique et on autorise le rétrécissement (scopé au login). */
    .cfa-form-wrap .fi-grid { grid-template-columns: minmax(0, 1fr) !important; width: 100% !important; }
    .cfa-form-wrap :is(.fi-sc, .fi-grid, .fi-grid-col, .fi-sc-component, .fi-fo-field,
        .fi-input-wrp, .fi-input-wrp-content-ctn, .fi-input) {
        min-width: 0 !important;
        max-width: 100% !important;
    }
    @keyframes cfaFade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

    .cfa-panel-brand { display: flex; align-items: center; gap: .6rem; margin-bottom: 2.25rem; }
    .cfa-panel-brand .cfa-mark { width: 38px; height: 38px; border-radius: 11px; font-size: 1.05rem; }
    .cfa-panel-brand b { font-size: 1.05rem; font-weight: 800; letter-spacing: -.01em; }
    .cfa-panel-brand b small { display: block; font-weight: 500; font-size: .7rem; color: var(--cfa-muted); letter-spacing: 0; }

    .cfa-form-head { margin-bottom: 1.75rem; }
    .cfa-form-head h1 { font-size: 1.7rem; font-weight: 800; letter-spacing: -.02em; margin: 0 0 .4rem; }
    .cfa-form-head p { font-size: .95rem; color: var(--cfa-muted); margin: 0; }

    /* Espace / lisibilité du formulaire Filament réinjecté */
    .cfa-form-wrap form { margin: 0; }
    .cfa-footer { margin-top: 2.25rem; text-align: center; font-size: .78rem; color: #94a3b8; }

    /* ══════════════════ RESPONSIVE ══════════════════ */
    @media (max-width: 1024px) {
        .cfa-login { grid-template-columns: 1fr; }
        .cfa-hero {
            min-height: auto;
            flex-direction: row; align-items: center; flex-wrap: wrap;
            gap: 1.25rem; padding: 1.75rem clamp(1.5rem, 5vw, 2.5rem);
        }
        .cfa-hero-body { max-width: none; flex: 1 1 320px; }
        .cfa-hero-title { font-size: 1.5rem; margin-bottom: .5rem; }
        .cfa-hero-sub { display: none; }
        .cfa-benefits { display: none; }              /* formulaire prioritaire */
        .cfa-float, .cfa-hero-brand { display: none; }
        .cfa-badges { flex: 1 1 100%; }
    }
    @media (max-width: 640px) {
        .cfa-hero { display: none; }                   /* mobile : 100% formulaire */
        .cfa-panel { justify-content: flex-start; padding: 3rem 1.5rem 2rem; }
    }

    @media (prefers-reduced-motion: reduce) {
        .cfa-float, .cfa-form-wrap { animation: none; }
    }
</style>

<div class="cfa-login">
    {{-- ─────────────── GAUCHE : présentation ─────────────── --}}
    <aside class="cfa-hero">
        <span class="cfa-shape cfa-shape--1"></span>
        <span class="cfa-shape cfa-shape--2"></span>

        {{-- Icônes flottantes discrètes --}}
        <span class="cfa-float cfa-float--a" aria-hidden="true">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
        </span>
        <span class="cfa-float cfa-float--b" aria-hidden="true">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <span class="cfa-float cfa-float--c" aria-hidden="true">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h12M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5"/></svg>
        </span>

        <div class="cfa-hero-brand">
            <span class="cfa-mark">M</span>
            <span class="cfa-brand-txt">Meridian&nbsp;CFA<small>Cockpit apprentissage</small></span>
        </div>

        <div class="cfa-hero-body">
            <h1 class="cfa-hero-title">
                L'ERP qui <span class="cfa-accent">simplifie la gestion</span> de votre CFA
            </h1>
            <p class="cfa-hero-sub">
                Centralisez vos candidats, entreprises, contrats, dossiers OPCO, documents
                et tableaux de bord depuis une plateforme unique.
            </p>

            <div class="cfa-benefits">
                <div class="cfa-benefit">
                    <span class="cfa-benefit-ico">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z"/></svg>
                    </span>
                    <div><b>Gain de temps</b><span>Automatisez le suivi commercial et administratif.</span></div>
                </div>
                <div class="cfa-benefit">
                    <span class="cfa-benefit-ico">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/></svg>
                    </span>
                    <div><b>Suivi centralisé</b><span>Candidats, entreprises, contrats et OPCO au même endroit.</span></div>
                </div>
                <div class="cfa-benefit">
                    <span class="cfa-benefit-ico">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.96 11.96 0 013.598 6 12 12 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622a12 12 0 00-.598-3A11.96 11.96 0 0112 2.714z"/></svg>
                    </span>
                    <div><b>Sécurisé</b><span>Données protégées, accès maîtrisés et conformité RGPD.</span></div>
                </div>
            </div>
        </div>

        <div class="cfa-badges">
            <span class="cfa-badge">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                RGPD
            </span>
            <span class="cfa-badge">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 0h10.5a1.5 1.5 0 011.5 1.5v6a1.5 1.5 0 01-1.5 1.5H6.75a1.5 1.5 0 01-1.5-1.5v-6a1.5 1.5 0 011.5-1.5z"/></svg>
                Données sécurisées
            </span>
            <span class="cfa-badge">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                Made in France
            </span>
        </div>
    </aside>

    {{-- ─────────────── DROITE : formulaire ─────────────── --}}
    <main class="cfa-panel">
        <div class="cfa-form-wrap">
            <div class="cfa-panel-brand">
                <span class="cfa-mark">M</span>
                <b>Meridian&nbsp;CFA<small>Espace ERP</small></b>
            </div>

            <div class="cfa-form-head">
                <h1>Connexion</h1>
                <p>Accédez à votre espace ERP CFA</p>
            </div>

            {{-- Formulaire Filament RÉEL : champs email/password/remember, bouton
                 de soumission, gestion d'erreurs, MFA, bouton démo (render hooks).
                 Aucune logique modifiée. --}}
            {{ $this->content }}

            <p class="cfa-footer">© 2026 Meridian CFA. Tous droits réservés.</p>
        </div>
    </main>
</div>
</div>{{-- fin .cfa-login-root (racine unique Livewire) --}}
