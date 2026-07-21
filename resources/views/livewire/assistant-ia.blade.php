{{--
    Assistant FAQ de la partie du logiciel consultée.

    Mise en page calquée sur la maquette « Chatbots FAQ par section » :
    bouton flottant (avatar + nom + icône de section), panneau avec en-tête
    coloré, message d'accueil, « Questions rapides » en lignes cliquables,
    champ de recherche, « Réponse » puis « Questions liées ».

    Toute la teinte vient de la variable CSS « --ia », alimentée par la couleur
    de l'assistant : une seule feuille de style pour cinq identités.
--}}
@if ($bot === null)
    {{-- Aucun assistant installé (seeder non joué) : on n'affiche rien. --}}
    <div wire:key="assistant-absent"></div>
@else
<div
    class="cfa-ia"
    style="--ia: {{ $bot->color }};"
    x-data="{ open: false, reduit: false }"
    @keydown.escape.window="open = false"
    @assistant-ouvrir.window="open = true"
>
    <style>
        [x-cloak] { display: none !important; }

        /* ── Bouton flottant : avatar + nom + icône de section ───────── */
        .cfa-ia-fab {
            position: fixed; z-index: 50; right: 1.5rem; bottom: 1.5rem;
            display: inline-flex; align-items: center; gap: .6rem;
            padding: .45rem .8rem .45rem .45rem; border-radius: 9999px;
            background: var(--ia); color: #fff; font-weight: 600; font-size: .875rem;
            box-shadow: 0 12px 28px -8px rgba(0, 0, 0, .45);
            border: none; cursor: pointer; transition: transform .15s ease, box-shadow .15s ease;
        }
        .cfa-ia-fab:hover { transform: translateY(-2px); box-shadow: 0 16px 34px -8px rgba(0, 0, 0, .5); }
        .cfa-ia-fab-fin { display: grid; place-items: center; width: 1.5rem; height: 1.5rem; opacity: .9; }
        .cfa-ia-fab-fin .cfa-ia-ico, .cfa-ia-fab-fin svg { width: 1.05rem; height: 1.05rem; }

        /* ── Avatar (image téléversée, sinon icône dans un rond clair) ── */
        .cfa-ia-avatar {
            position: relative; flex: none; display: grid; place-items: center;
            width: 2.4rem; height: 2.4rem; border-radius: 9999px; overflow: hidden;
            background: #fff; color: var(--ia);
        }
        .cfa-ia-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .cfa-ia-avatar .cfa-ia-ico, .cfa-ia-avatar svg { width: 1.3rem; height: 1.3rem; }
        /* Pastille « en ligne », comme sur la maquette. */
        .cfa-ia-dot {
            position: absolute; right: -1px; bottom: -1px; width: .7rem; height: .7rem;
            border-radius: 9999px; background: #22c55e; border: 2px solid var(--ia);
        }
        .cfa-ia-fab .cfa-ia-dot { border-color: var(--ia); }

        /* ── Panneau ─────────────────────────────────────────────────── */
        .cfa-ia-panel {
            position: fixed; z-index: 51; right: 1.5rem; bottom: 1.5rem;
            width: 400px; max-width: calc(100vw - 2rem);
            height: 600px; max-height: calc(100vh - 3rem);
            display: flex; flex-direction: column; overflow: hidden;
            background: #fff; color: #111827;
            border-radius: 1rem; border: 1px solid rgba(0, 0, 0, .08);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .35);
        }
        .cfa-ia-panel--reduit { height: auto; }
        .cfa-ia-head {
            display: flex; align-items: center; gap: .7rem; padding: .85rem 1rem;
            background: var(--ia); color: #fff; flex: none;
        }
        .cfa-ia-titres { min-width: 0; }
        .cfa-ia-title { font-weight: 700; font-size: .95rem; line-height: 1.2; }
        .cfa-ia-sub { font-size: .74rem; opacity: .9; }
        .cfa-ia-actions { margin-left: auto; display: flex; align-items: center; gap: .15rem; }
        .cfa-ia-icone-btn {
            background: transparent; border: none; color: #fff; cursor: pointer;
            opacity: .85; padding: .3rem; border-radius: .4rem; line-height: 0;
        }
        .cfa-ia-icone-btn:hover { opacity: 1; background: rgba(255, 255, 255, .18); }
        .cfa-ia-icone-btn svg { width: 1.15rem; height: 1.15rem; }

        .cfa-ia-body {
            flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 1rem;
            background: #f8fafc;
        }

        /* ── Bulles ──────────────────────────────────────────────────── */
        .cfa-ia-bubble {
            padding: .75rem .9rem; border-radius: .75rem; font-size: .85rem; line-height: 1.5;
            background: #fff; color: #1f2937; border: 1px solid rgba(0, 0, 0, .07);
        }
        .cfa-ia-bubble--user {
            align-self: flex-end; max-width: 85%;
            background: var(--ia); color: #fff; border-color: transparent;
        }
        /* Réponse : bulle teintée, comme sur la maquette. */
        .cfa-ia-bubble--reponse {
            background: color-mix(in srgb, var(--ia) 8%, #fff);
            border-color: color-mix(in srgb, var(--ia) 22%, #fff);
            color: color-mix(in srgb, var(--ia) 78%, #000);
        }

        /* ── Libellés de section ─────────────────────────────────────── */
        .cfa-ia-label {
            font-size: .78rem; font-weight: 700; color: #334155; margin-bottom: .4rem;
        }
        .cfa-ia-label--ia { color: var(--ia); }

        /* ── Questions : lignes pleine largeur avec chevron ──────────── */
        .cfa-ia-liste { display: flex; flex-direction: column; gap: .4rem; }
        .cfa-ia-item {
            display: flex; align-items: center; gap: .6rem; width: 100%;
            padding: .65rem .8rem; border-radius: .6rem; cursor: pointer; text-align: left;
            font-size: .82rem; line-height: 1.35;
            background: #fff; border: 1px solid rgba(0, 0, 0, .09);
            color: color-mix(in srgb, var(--ia) 80%, #000);
            transition: background .12s ease, border-color .12s ease;
        }
        .cfa-ia-item:hover {
            background: color-mix(in srgb, var(--ia) 7%, #fff);
            border-color: color-mix(in srgb, var(--ia) 30%, #fff);
        }
        .cfa-ia-item-texte { flex: 1; }
        .cfa-ia-item svg { width: .95rem; height: .95rem; flex: none; opacity: .55; }

        /* ── Recherche dans l'aide ───────────────────────────────────── */
        .cfa-ia-search { position: relative; }
        .cfa-ia-search svg {
            position: absolute; left: .7rem; top: 50%; transform: translateY(-50%);
            width: 1rem; height: 1rem; color: #94a3b8; pointer-events: none;
        }
        .cfa-ia-search input {
            width: 100%; padding: .65rem .8rem .65rem 2.2rem; border-radius: .6rem;
            font-size: .82rem; background: #fff; color: #111827; outline: none;
            border: 1px solid rgba(0, 0, 0, .12);
        }
        .cfa-ia-search input:focus {
            border-color: var(--ia);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--ia) 22%, transparent);
        }

        /* ── Liens vers une page ─────────────────────────────────────── */
        .cfa-ia-link {
            display: inline-flex; align-items: center; gap: .4rem; align-self: flex-start;
            padding: .45rem .75rem; border-radius: .6rem; font-size: .8rem; font-weight: 600;
            text-decoration: none; background: #f3f4f6; color: #374151;
            border: 1px solid rgba(0, 0, 0, .08);
            background: color-mix(in srgb, var(--ia) 12%, #fff);
            color: color-mix(in srgb, var(--ia) 78%, #000);
            border-color: color-mix(in srgb, var(--ia) 28%, #fff);
        }
        .cfa-ia-link:hover { background: color-mix(in srgb, var(--ia) 20%, #fff); }
        .cfa-ia-link svg { width: .9rem; height: .9rem; }

        /* ── Pied : poser une autre question ─────────────────────────── */
        .cfa-ia-foot { padding: .75rem; border-top: 1px solid rgba(0, 0, 0, .07); background: #fff; flex: none; }
        .cfa-ia-form { display: flex; align-items: center; gap: .5rem; }
        .cfa-ia-input {
            flex: 1; padding: .65rem .85rem; border-radius: 9999px; font-size: .84rem;
            border: 1px solid rgba(0, 0, 0, .15); background: #fff; color: #111827; outline: none;
        }
        .cfa-ia-input:focus { border-color: var(--ia); box-shadow: 0 0 0 3px color-mix(in srgb, var(--ia) 22%, transparent); }
        .cfa-ia-send {
            display: grid; place-items: center; width: 2.5rem; height: 2.5rem; flex: none;
            border-radius: 9999px; border: none; cursor: pointer; color: #fff; background: var(--ia);
        }
        .cfa-ia-send:disabled { opacity: .5; cursor: default; }
        .cfa-ia-send svg { width: 1.1rem; height: 1.1rem; }
        .cfa-ia-note { margin-top: .5rem; font-size: .68rem; color: #9ca3af; text-align: center; }

        /* ── Thème sombre ────────────────────────────────────────────── */
        .dark .cfa-ia-panel { background: #1f2937; color: #e5e7eb; border-color: rgba(255, 255, 255, .08); }
        .dark .cfa-ia-body { background: #111827; }
        .dark .cfa-ia-bubble { background: #1f2937; color: #e5e7eb; border-color: rgba(255, 255, 255, .08); }
        .dark .cfa-ia-bubble--reponse {
            background: color-mix(in srgb, var(--ia) 20%, #111827);
            border-color: color-mix(in srgb, var(--ia) 32%, #111827);
            color: color-mix(in srgb, var(--ia) 28%, #fff);
        }
        .dark .cfa-ia-label { color: #cbd5e1; }
        .dark .cfa-ia-item {
            background: #1f2937; border-color: rgba(255, 255, 255, .1);
            color: color-mix(in srgb, var(--ia) 30%, #fff);
        }
        .dark .cfa-ia-item:hover { background: color-mix(in srgb, var(--ia) 20%, #111827); }
        .dark .cfa-ia-search input { background: #111827; color: #e5e7eb; border-color: rgba(255, 255, 255, .15); }
        .dark .cfa-ia-link {
            background: color-mix(in srgb, var(--ia) 22%, #111827);
            color: color-mix(in srgb, var(--ia) 30%, #fff);
            border-color: color-mix(in srgb, var(--ia) 35%, #111827);
        }
        .dark .cfa-ia-foot { background: #1f2937; border-color: rgba(255, 255, 255, .08); }
        .dark .cfa-ia-input { background: #111827; color: #e5e7eb; border-color: rgba(255, 255, 255, .15); }
        .dark .cfa-ia-avatar { background: rgba(255, 255, 255, .92); }

        /* ── Mobile : panneau quasi plein écran ──────────────────────── */
        @media (max-width: 640px) {
            .cfa-ia-fab { right: 1rem; bottom: 1rem; }
            .cfa-ia-fab-nom, .cfa-ia-fab-fin { display: none; }
            .cfa-ia-fab { padding: .45rem; }
            .cfa-ia-panel {
                right: .5rem; left: .5rem; bottom: .5rem;
                width: auto; max-width: none;
                height: calc(100dvh - 1rem); max-height: calc(100dvh - 1rem);
            }
            .cfa-ia-item { padding: .8rem; font-size: .85rem; }
            .cfa-ia-icone-btn { padding: .5rem; }
        }
    </style>

    {{-- 1. Bouton flottant : avatar + nom de l'assistant + icône de section --}}
    <button type="button" class="cfa-ia-fab" x-show="!open" x-cloak x-on:click="open = true"
            title="{{ $bot->name }}" aria-label="{{ $bot->name }}">
        <span class="cfa-ia-avatar">
            @if ($bot->avatarUrl())
                <img src="{{ $bot->avatarUrl() }}" alt="">
            @else
                @svg($bot->icone(), 'cfa-ia-ico')
            @endif
            <span class="cfa-ia-dot"></span>
        </span>
        <span class="cfa-ia-fab-nom">{{ $bot->name }}</span>
        <span class="cfa-ia-fab-fin">@svg($bot->icone(), 'cfa-ia-ico')</span>
    </button>

    {{-- 3. Panneau du chatbot --}}
    <div class="cfa-ia-panel" :class="reduit && 'cfa-ia-panel--reduit'" x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0">

        {{-- En-tête : avatar + nom + sous-titre + réduire/fermer --}}
        <div class="cfa-ia-head">
            <span class="cfa-ia-avatar">
                @if ($bot->avatarUrl())
                    <img src="{{ $bot->avatarUrl() }}" alt="">
                @else
                    @svg($bot->icone(), 'cfa-ia-ico')
                @endif
                <span class="cfa-ia-dot"></span>
            </span>
            <span class="cfa-ia-titres">
                <span class="cfa-ia-title">{{ $bot->name }}</span><br>
                <span class="cfa-ia-sub">{{ $bot->description ?: 'Besoin d\'aide ? Posez votre question' }}</span>
            </span>
            <span class="cfa-ia-actions">
                <button type="button" class="cfa-ia-icone-btn" x-on:click="reduit = !reduit"
                        :aria-label="reduit ? 'Agrandir' : 'Réduire'">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M5 12h14"/>
                    </svg>
                </button>
                <button type="button" class="cfa-ia-icone-btn" x-on:click="open = false" aria-label="Fermer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M18 6 6 18M6 6l12 12"/>
                    </svg>
                </button>
            </span>
        </div>

        <div x-show="!reduit" style="display:contents">
            <div class="cfa-ia-body" x-ref="corps"
                 @assistant-defiler.window="$nextTick(() => $refs.corps.scrollTop = $refs.corps.scrollHeight)">

                @foreach ($messages as $message)
                    @php $type = $message['type'] ?? 'reponse'; @endphp

                    <div wire:key="msg-{{ $loop->index }}">
                        {{-- Question de l'utilisateur --}}
                        @if ($message['role'] === 'user')
                            <div class="cfa-ia-bubble cfa-ia-bubble--user">{{ $message['texte'] }}</div>
                        @else
                            {{-- Libellé « Réponse » au-dessus d'une vraie réponse --}}
                            @if ($type === 'reponse')
                                <div class="cfa-ia-label cfa-ia-label--ia">Réponse</div>
                            @endif

                            <div @class([
                                'cfa-ia-bubble',
                                'cfa-ia-bubble--reponse' => $type === 'reponse',
                            ])>{{ $message['texte'] }}</div>

                            {{-- Lien vers la page concernée --}}
                            @if (! empty($message['liens']))
                                <div style="margin-top:.5rem; display:flex; flex-direction:column;">
                                    @foreach ($message['liens'] as $lien)
                                        <a href="{{ $lien['url'] }}" class="cfa-ia-link" wire:navigate>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M5 12h14M13 6l6 6-6 6"/>
                                            </svg>
                                            {{ $lien['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Questions rapides (accueil) ou liées (après réponse) --}}
                            @if (! empty($message['suggestions']))
                                <div style="margin-top:.75rem;">
                                    <div class="cfa-ia-label">
                                        {{ $type === 'reponse' ? 'Questions liées' : 'Questions rapides' }}
                                    </div>
                                    <div class="cfa-ia-liste">
                                        @foreach ($message['suggestions'] as $suggestion)
                                            <button type="button" class="cfa-ia-item" wire:click="demander(@js($suggestion))">
                                                <span class="cfa-ia-item-texte">{{ $suggestion }}</span>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="m9 18 6-6-6-6"/>
                                                </svg>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Champ de recherche, sous le message d'accueil --}}
                            @if ($type === 'accueil')
                                <div class="cfa-ia-search" style="margin-top:.75rem;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                                    </svg>
                                    <input type="text" wire:model="question" wire:keydown.enter="envoyer"
                                           placeholder="Rechercher dans l'aide…" autocomplete="off">
                                </div>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Pied : poser une autre question --}}
            <div class="cfa-ia-foot">
                <form class="cfa-ia-form" wire:submit="envoyer">
                    <input type="text" class="cfa-ia-input" wire:model="question"
                           placeholder="Poser une autre question…" autocomplete="off">
                    <button type="submit" class="cfa-ia-send" aria-label="Envoyer" wire:loading.attr="disabled">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/>
                        </svg>
                    </button>
                </form>
                <p class="cfa-ia-note">Assistant local — vos données restent dans l'ERP.</p>
            </div>
        </div>
    </div>
</div>
@endif
