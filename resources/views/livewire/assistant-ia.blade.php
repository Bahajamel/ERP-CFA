{{-- Assistant d'aide « Demander à l'IA » : bouton flottant + panneau de chat. --}}
<div
    class="cfa-ia"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @assistant-ouvrir.window="open = true"
>
    <style>
        [x-cloak] { display: none !important; }

        .cfa-ia-fab {
            position: fixed; z-index: 50; right: 1.5rem; bottom: 1.5rem;
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .7rem 1.1rem; border-radius: 9999px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff; font-weight: 600; font-size: .875rem;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, .5);
            border: none; cursor: pointer; transition: transform .15s ease, box-shadow .15s ease;
        }
        .cfa-ia-fab:hover { transform: translateY(-2px); box-shadow: 0 14px 30px -6px rgba(79, 70, 229, .6); }
        .cfa-ia-fab svg { width: 1.15rem; height: 1.15rem; }

        .cfa-ia-panel {
            position: fixed; z-index: 51; right: 1.5rem; bottom: 1.5rem;
            width: 380px; max-width: calc(100vw - 2rem);
            height: 560px; max-height: calc(100vh - 3rem);
            display: flex; flex-direction: column; overflow: hidden;
            background: #fff; color: #111827;
            border-radius: 1rem; border: 1px solid rgba(0, 0, 0, .08);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .35);
        }
        .cfa-ia-head {
            display: flex; align-items: center; gap: .6rem; padding: .9rem 1rem;
            background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff;
        }
        .cfa-ia-head-icon {
            display: grid; place-items: center; width: 2rem; height: 2rem; flex: none;
            background: rgba(255, 255, 255, .18); border-radius: 9999px;
        }
        .cfa-ia-head-icon svg { width: 1.15rem; height: 1.15rem; }
        .cfa-ia-title { font-weight: 700; font-size: .9rem; line-height: 1.1; }
        .cfa-ia-sub { font-size: .72rem; opacity: .85; }
        .cfa-ia-close {
            margin-left: auto; background: transparent; border: none; color: #fff;
            cursor: pointer; opacity: .85; padding: .25rem; border-radius: .4rem;
        }
        .cfa-ia-close:hover { opacity: 1; background: rgba(255, 255, 255, .15); }
        .cfa-ia-close svg { width: 1.2rem; height: 1.2rem; }

        .cfa-ia-body {
            flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: .75rem;
            background: #f9fafb;
        }
        .cfa-ia-msg { display: flex; flex-direction: column; gap: .45rem; max-width: 90%; }
        .cfa-ia-msg--user { align-self: flex-end; align-items: flex-end; }
        .cfa-ia-msg--bot { align-self: flex-start; }
        .cfa-ia-bubble { padding: .6rem .8rem; border-radius: .9rem; font-size: .85rem; line-height: 1.45; }
        .cfa-ia-msg--user .cfa-ia-bubble {
            background: #4f46e5; color: #fff; border-bottom-right-radius: .25rem;
        }
        .cfa-ia-msg--bot .cfa-ia-bubble {
            background: #fff; color: #1f2937; border: 1px solid rgba(0, 0, 0, .07);
            border-bottom-left-radius: .25rem;
        }

        .cfa-ia-links { display: flex; flex-direction: column; gap: .35rem; }
        .cfa-ia-link {
            display: inline-flex; align-items: center; gap: .4rem; align-self: flex-start;
            padding: .4rem .7rem; border-radius: .6rem; font-size: .8rem; font-weight: 600;
            background: #eef2ff; color: #4338ca; text-decoration: none; border: 1px solid #e0e7ff;
        }
        .cfa-ia-link:hover { background: #e0e7ff; }
        .cfa-ia-link svg { width: .9rem; height: .9rem; }

        .cfa-ia-suggests { display: flex; flex-wrap: wrap; gap: .35rem; }
        .cfa-ia-chip {
            padding: .35rem .65rem; border-radius: 9999px; font-size: .76rem;
            background: #fff; color: #4338ca; border: 1px solid #c7d2fe; cursor: pointer;
            text-align: left; transition: background .12s ease;
        }
        .cfa-ia-chip:hover { background: #eef2ff; }

        .cfa-ia-foot { padding: .75rem; border-top: 1px solid rgba(0, 0, 0, .07); background: #fff; }
        .cfa-ia-form { display: flex; align-items: center; gap: .5rem; }
        .cfa-ia-input {
            flex: 1; padding: .6rem .8rem; border-radius: .7rem; font-size: .85rem;
            border: 1px solid rgba(0, 0, 0, .15); background: #fff; color: #111827; outline: none;
        }
        .cfa-ia-input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99, 102, 241, .18); }
        .cfa-ia-send {
            display: grid; place-items: center; width: 2.4rem; height: 2.4rem; flex: none;
            border-radius: .7rem; border: none; cursor: pointer; color: #fff;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
        }
        .cfa-ia-send:disabled { opacity: .5; cursor: default; }
        .cfa-ia-send svg { width: 1.15rem; height: 1.15rem; }
        .cfa-ia-note { margin-top: .5rem; font-size: .68rem; color: #9ca3af; text-align: center; }

        /* ── Thème sombre ────────────────────────────────────────────── */
        .dark .cfa-ia-panel { background: #1f2937; color: #e5e7eb; border-color: rgba(255, 255, 255, .08); }
        .dark .cfa-ia-body { background: #111827; }
        .dark .cfa-ia-msg--bot .cfa-ia-bubble { background: #1f2937; color: #e5e7eb; border-color: rgba(255, 255, 255, .08); }
        .dark .cfa-ia-link { background: #312e81; color: #c7d2fe; border-color: #3730a3; }
        .dark .cfa-ia-link:hover { background: #3730a3; }
        .dark .cfa-ia-chip { background: #1f2937; color: #c7d2fe; border-color: #3730a3; }
        .dark .cfa-ia-chip:hover { background: #312e81; }
        .dark .cfa-ia-foot { background: #1f2937; border-color: rgba(255, 255, 255, .08); }
        .dark .cfa-ia-input { background: #111827; color: #e5e7eb; border-color: rgba(255, 255, 255, .15); }

        @media (max-width: 480px) {
            .cfa-ia-panel { right: 1rem; bottom: 1rem; height: calc(100vh - 2rem); }
        }
    </style>

    {{-- Bouton flottant « Demander à l'IA » --}}
    <button type="button" class="cfa-ia-fab" x-show="!open" x-cloak x-on:click="open = true" aria-label="Besoin d'aide ? Demander à l'IA">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3a9 9 0 0 0-9 9v3a3 3 0 0 0 3 3h1v-6H5v-.5a7 7 0 0 1 14 0V15h-2v6h1a3 3 0 0 0 3-3v-3a9 9 0 0 0-9-9Z"/>
        </svg>
        Demander à l'IA
    </button>

    {{-- Panneau de chat --}}
    <div class="cfa-ia-panel" x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0">
        <div class="cfa-ia-head">
            <span class="cfa-ia-head-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3a9 9 0 0 0-9 9v3a3 3 0 0 0 3 3h1v-6H5v-.5a7 7 0 0 1 14 0V15h-2v6h1a3 3 0 0 0 3-3v-3a9 9 0 0 0-9-9Z"/>
                </svg>
            </span>
            <span>
                <span class="cfa-ia-title">Assistant CFA</span><br>
                <span class="cfa-ia-sub">Besoin d'aide ? Posez votre question</span>
            </span>
            <button type="button" class="cfa-ia-close" x-on:click="open = false" aria-label="Fermer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="cfa-ia-body" x-ref="corps"
             @assistant-defiler.window="$nextTick(() => $refs.corps.scrollTop = $refs.corps.scrollHeight)">
            @foreach ($messages as $message)
                <div class="cfa-ia-msg cfa-ia-msg--{{ $message['role'] }}" wire:key="msg-{{ $loop->index }}">
                    <div class="cfa-ia-bubble">{{ $message['texte'] }}</div>

                    @if (! empty($message['liens']))
                        <div class="cfa-ia-links">
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

                    @if (! empty($message['suggestions']))
                        <div class="cfa-ia-suggests">
                            @foreach ($message['suggestions'] as $suggestion)
                                <button type="button" class="cfa-ia-chip" wire:click="demander(@js($suggestion))">
                                    {{ $suggestion }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="cfa-ia-foot">
            <form class="cfa-ia-form" wire:submit="envoyer">
                <input type="text" class="cfa-ia-input" wire:model="question"
                       placeholder="Ex : comment ajouter un apprenant ?" autocomplete="off">
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
