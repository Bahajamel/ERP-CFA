<x-filament-panels::page>
    <div class="mb-board">
        {{-- Barre d'outils « Monday » (au-dessus du filtre), même logique que les
             tableaux personnalisés : « Configurer » (renommer / supprimer des
             colonnes) + « Ajouter une colonne ». « Ajouter une ligne » est en pied
             de tableau. La création d'un tableau passe par le sélecteur de tables. --}}
        @if (\App\Support\CustomFields::peutGerer())
            <div class="mb-toolbar">
                {{-- Configurer : regroupe le renommage et la suppression de colonnes. --}}
                <x-filament::dropdown placement="bottom-start">
                    <x-slot name="trigger">
                        <x-filament::button color="gray" icon="heroicon-o-cog-6-tooth">
                            Configurer
                        </x-filament::button>
                    </x-slot>

                    <x-filament::dropdown.list>
                        <x-filament::dropdown.list.item
                            icon="heroicon-o-pencil-square"
                            wire:click="mountAction('renommerColonnes')"
                        >
                            Renommer les colonnes
                        </x-filament::dropdown.list.item>

                        <x-filament::dropdown.list.item
                            icon="heroicon-o-trash"
                            color="danger"
                            wire:click="mountAction('supprimerColonne')"
                        >
                            Supprimer une colonne
                        </x-filament::dropdown.list.item>
                    </x-filament::dropdown.list>
                </x-filament::dropdown>

                {{-- Ajouter une colonne (même modal que les tableaux personnalisés). --}}
                {{ $this->ajouterColonneAction }}
            </div>
        @endif

        {{-- Tableau opérationnel + panneau Focus (affiché seulement à la sélection) --}}
        @php $focus = $this->getFocusCandidate(); @endphp
        <div class="cfa-cand-layout {{ $focus ? 'has-focus' : '' }}">
            <div class="cfa-cand-main">
                {{ $this->table }}

                {{-- Pied « Ajouter un élément » (= nouvelle ligne / nouveau candidat), comme Monday. --}}
                <div class="mb-add-row">
                    {{ $this->ajouterElementAction }}
                </div>
            </div>
            @if ($focus)
                <aside class="cfa-cand-focus-col">
                    @include('filament.candidates.focus-panel', ['c' => $focus])
                </aside>
            @endif
        </div>
    </div>

    {{-- Habillage clair « façon Monday » scopé à cette page (l'app est en thème
         sombre par défaut ; on force ici un rendu clair : cartes blanches, badges
         pastel, lignes aérées, fidèle à la maquette). --}}
    <style>
        .mb-board { --mb-line: #e8ecf2; --mb-ink: #1e293b; }
        .mb-toolbar { display: flex; flex-wrap: wrap; gap: .6rem; margin: 0 0 .75rem; }

        /* Carte tableau : blanc, arrondi, ombre douce */
        .mb-board .fi-ta,
        .mb-board .fi-ta-ctn {
            background: #fff !important;
            border: 1px solid var(--mb-line) !important;
            border-radius: 1rem !important;
            box-shadow: 0 1px 2px rgba(15,23,42,.05), 0 10px 30px -20px rgba(15,23,42,.2) !important;
            color: var(--mb-ink) !important;
        }
        /* Barre de filtres + toolbar interne en clair */
        .mb-board .fi-ta-header-ctn,
        .mb-board .fi-ta-header-toolbar,
        .mb-board .fi-ta-filters,
        .mb-board .fi-ta-selection-ctn { background: #fff !important; border-color: var(--mb-line) !important; }
        .mb-board .fi-ta-filters { background: #f8fafc !important; border-radius: .75rem; }
        .mb-board .fi-fo-field-wrp-label,
        .mb-board .fi-ta-filters label { color: #64748b !important; }

        /* En-têtes de colonnes */
        .mb-board .fi-ta-header-cell,
        .mb-board thead th { background: #f8fafc !important; border-color: var(--mb-line) !important; }
        .mb-board .fi-ta-header-cell-label,
        .mb-board thead th { color: #64748b !important; font-weight: 600; }

        /* Lignes & cellules */
        .mb-board .fi-ta-row { background: #fff !important; }
        .mb-board .fi-ta-row:hover { background: #f8fafc !important; }
        .mb-board tbody tr { border-top: 1px solid #f1f5f9 !important; }
        .mb-board .fi-ta-cell,
        .mb-board tbody td { color: var(--mb-ink) !important; }
        .mb-board .fi-ta-record-content * { color: var(--mb-ink); }
        .mb-board .fi-ta-empty-state,
        .mb-board .fi-ta-empty-state-heading { color: #475569 !important; }

        /* Champs (recherche, selects, group-by) en clair */
        .mb-board .fi-input,
        .mb-board .fi-select-input,
        .mb-board .fi-input-wrp { background: #fff !important; color: #0f172a !important; border-color: var(--mb-line) !important; }
        .mb-board .fi-input::placeholder { color: #94a3b8 !important; }

        /* Pagination */
        .mb-board .fi-pagination,
        .mb-board .fi-pagination * { color: #475569 !important; }

        /* Pied « Ajouter un élément » */
        .mb-add-row { display: flex; align-items: center; padding: .6rem .25rem 0; }
        .mb-add-row .fi-link { font-weight: 600; }

        /* --- Contraste : lisibilité sur fond blanc (le thème sombre laisse des
               textes/badges trop pâles ; on les fonce sans toucher aux badges
               de couleur verte/ambre/rouge). --- */
        .mb-board .fi-ta-filters,
        .mb-board .fi-ta-filters legend,
        .mb-board .fi-ta-filters label,
        .mb-board .fi-fo-field-wrp-label,
        .mb-board .fi-ta-header-toolbar,
        .mb-board .fi-ta-grouping-settings,
        .mb-board .fi-dropdown-trigger { color: #475569 !important; }
        .mb-board .fi-ta-cell,
        .mb-board .fi-ta-cell .fi-ta-text,
        .mb-board .fi-ta-record-content,
        .mb-board .fi-ta-text-item-label,
        .mb-board tbody td { color: #334155 !important; }
        .mb-board .fi-ta-cell .fi-ta-text-item-icon { color: #94a3b8 !important; }
        /* Panneau de filtres : titre + labels + valeurs lisibles */
        .mb-board .fi-ta-filters-heading,
        .mb-board .fi-ta-filters label,
        .mb-board .fi-ta-filters .fi-fo-field-wrp-label { color: #475569 !important; }
        .mb-board .fi-ta-filters .fi-input,
        .mb-board .fi-ta-filters .fi-select-input { color: #0f172a !important; }
        /* Léger liseré sur les badges pour les détacher du blanc */
        .mb-board .fi-ta-cell .fi-badge { box-shadow: inset 0 0 0 1px rgba(15,23,42,.06); }

        /* --- Redimensionnement des colonnes à la souris (façon Monday) ---
               Poignée invisible collée au bord droit de chaque en-tête ; on tire
               pour élargir/réduire, double-clic pour revenir à l'auto. --- */
        .mb-board thead th[data-col-key] { position: relative; }
        .mb-board .mb-resizer {
            position: absolute; top: 0; right: -3px; width: 8px; height: 100%;
            cursor: col-resize; user-select: none; touch-action: none; z-index: 5;
        }
        .mb-board .mb-resizer::after {
            content: ''; position: absolute; top: 18%; bottom: 18%; left: 3px; width: 2px;
            border-radius: 2px; background: transparent; transition: background .12s;
        }
        .mb-board thead th:hover .mb-resizer::after,
        body.mb-resizing .mb-resizer::after { background: #3b82f6; }
        body.mb-resizing { cursor: col-resize !important; user-select: none !important; }

        /* --- Déplacement des colonnes par glisser-déposer de l'en-tête (façon
               Monday) : on attrape l'en-tête et on le fait glisser à gauche/droite ;
               un liseré indigo indique où la colonne sera déposée. --- */
        .mb-board thead th[data-col-key] { cursor: grab; }
        .mb-board thead th.mb-dragging { opacity: .45; }
        .mb-board thead th.mb-drop-before { box-shadow: inset 3px 0 0 0 #4f46e5; }
        .mb-board thead th.mb-drop-after { box-shadow: inset -3px 0 0 0 #4f46e5; }
        body.mb-reordering, body.mb-reordering * { cursor: grabbing !important; }

        /* --- Groupes « façon Monday » : chaque groupe (statut par défaut) est un
               bloc repliable, avec un accent coloré, un titre en gras et un
               compteur. La couleur est posée par JS selon le statut (--mb-g). --- */
        .mb-board .fi-ta-group-header-cell { padding: 0 !important; background: #f1f5f9 !important; }
        .mb-board .fi-ta-group-header {
            display: flex; align-items: center; gap: .55rem;
            padding: .7rem 1rem; cursor: pointer;
            border-left: 4px solid var(--mb-g, #6366f1);
            background: #f8fafc;
        }
        .mb-board .fi-ta-group-header:hover { background: #eef2ff; }
        .mb-board .fi-ta-group-heading {
            display: inline-flex; align-items: center; gap: .5rem;
            font-weight: 700; font-size: .95rem; color: #1e293b !important;
        }
        .mb-board .fi-ta-group-heading::before {
            content: ''; width: .7rem; height: .7rem; border-radius: 3px;
            background: var(--mb-g, #6366f1); flex: none;
        }
        .mb-board .mb-group-count {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 1.4rem; height: 1.4rem; padding: 0 .45rem; margin-left: .1rem;
            border-radius: 999px; background: var(--mb-g, #6366f1);
            color: #fff; font-size: .72rem; font-weight: 700; line-height: 1;
        }
        /* Chevron de repli aligné à droite. */
        .mb-board .fi-ta-group-header .fi-icon-btn,
        .mb-board .fi-ta-group-header > button:last-child { margin-left: auto; }

        .dark .mb-board .fi-ta-group-header-cell { background: #0b1220 !important; }
        .dark .mb-board .fi-ta-group-header { background: #0f172a; }
        .dark .mb-board .fi-ta-group-header:hover { background: #17233b; }
        .dark .mb-board .fi-ta-group-heading { color: #e2e8f0 !important; }
    </style>

    {{-- Redimensionnement des colonnes à la souris : on greffe une poignée au bord
         droit de chaque en-tête ; la largeur obtenue est persistée par CFA via
         setLargeurColonne(). Ré-appliqué après chaque rendu Livewire (tri, filtre,
         pagination re-génèrent le <thead>). --}}
    <script>
        (function () {
            const MIN = 80;

            function composant(board) {
                const root = board.closest('[wire\\:id]');
                return root ? (window.Livewire && window.Livewire.find(root.getAttribute('wire:id'))) : null;
            }

            /** Ordre courant des clés de colonnes tel qu'affiché dans l'en-tête. */
            function ordreActuel(board) {
                return Array.from(board.querySelectorAll('table thead th[data-col-key]'))
                    .map((x) => x.getAttribute('data-col-key'));
            }

            function greffer(board) {
                board.querySelectorAll('table thead th[data-col-key]').forEach(function (th) {
                    // --- Poignée de redimensionnement (bord droit) ---
                    if (!th.querySelector('.mb-resizer')) {
                        const handle = document.createElement('span');
                        handle.className = 'mb-resizer';

                        // Empêche le tri quand on saisit la poignée.
                        handle.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); });

                        // Double-clic = réinitialiser (largeur auto).
                        handle.addEventListener('dblclick', function (e) {
                            e.preventDefault(); e.stopPropagation();
                            const cmp = composant(board);
                            if (cmp) cmp.call('setLargeurColonne', th.getAttribute('data-col-key'), null);
                        });

                        handle.addEventListener('mousedown', function (e) {
                            e.preventDefault(); e.stopPropagation();
                            const startX = e.pageX;
                            const startW = th.offsetWidth;
                            document.body.classList.add('mb-resizing');
                            // Verrou : pas de glisser-déposer de colonne pendant un resize.
                            board.__mbResizing = true;
                            th.setAttribute('draggable', 'false');

                            function onMove(ev) {
                                const w = Math.max(MIN, startW + (ev.pageX - startX));
                                th.style.width = w + 'px';
                            }
                            function onUp(ev) {
                                document.removeEventListener('mousemove', onMove);
                                document.removeEventListener('mouseup', onUp);
                                document.body.classList.remove('mb-resizing');
                                board.__mbResizing = false;
                                th.setAttribute('draggable', 'true');
                                const w = Math.max(MIN, startW + (ev.pageX - startX));
                                const cmp = composant(board);
                                if (cmp) cmp.call('setLargeurColonne', th.getAttribute('data-col-key'), Math.round(w));
                            }
                            document.addEventListener('mousemove', onMove);
                            document.addEventListener('mouseup', onUp);
                        });

                        th.appendChild(handle);
                    }

                    // --- Déplacement de la colonne par glisser-déposer de l'en-tête ---
                    if (th.dataset.mbDrag !== '1') {
                        th.dataset.mbDrag = '1';
                        th.setAttribute('draggable', 'true');

                        th.addEventListener('dragstart', function (e) {
                            if (board.__mbResizing) { e.preventDefault(); return; }
                            board.__mbDragKey = th.getAttribute('data-col-key');
                            th.classList.add('mb-dragging');
                            document.body.classList.add('mb-reordering');
                            if (e.dataTransfer) {
                                e.dataTransfer.effectAllowed = 'move';
                                try { e.dataTransfer.setData('text/plain', board.__mbDragKey); } catch (_) {}
                            }
                        });
                        th.addEventListener('dragend', function () {
                            th.classList.remove('mb-dragging');
                            document.body.classList.remove('mb-reordering');
                            board.querySelectorAll('.mb-drop-before, .mb-drop-after')
                                .forEach((el) => el.classList.remove('mb-drop-before', 'mb-drop-after'));
                            board.__mbDragKey = null;
                        });
                        th.addEventListener('dragover', function (e) {
                            const src = board.__mbDragKey;
                            if (!src || src === th.getAttribute('data-col-key')) return;
                            e.preventDefault();
                            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
                            const rect = th.getBoundingClientRect();
                            const avant = (e.clientX - rect.left) < rect.width / 2;
                            th.classList.toggle('mb-drop-before', avant);
                            th.classList.toggle('mb-drop-after', !avant);
                        });
                        th.addEventListener('dragleave', function () {
                            th.classList.remove('mb-drop-before', 'mb-drop-after');
                        });
                        th.addEventListener('drop', function (e) {
                            e.preventDefault();
                            const src = board.__mbDragKey;
                            const dst = th.getAttribute('data-col-key');
                            th.classList.remove('mb-drop-before', 'mb-drop-after');
                            if (!src || src === dst) return;

                            const rect = th.getBoundingClientRect();
                            const avant = (e.clientX - rect.left) < rect.width / 2;
                            const cles = ordreActuel(board);
                            const from = cles.indexOf(src);
                            if (from === -1) return;
                            cles.splice(from, 1);
                            let to = cles.indexOf(dst);
                            if (to === -1) return;
                            if (!avant) to += 1;
                            cles.splice(to, 0, src);

                            const cmp = composant(board);
                            if (cmp) cmp.call('setOrdreColonnes', cles);
                        });
                    }
                });
            }

            /** Couleur d'un groupe selon le libellé de statut (aligné sur les badges). */
            function couleurStatut(texte) {
                const t = (texte || '').toLowerCase();
                if (t.includes('planifier')) return '#3b82f6';
                if (t.includes('prévu') || t.includes('prevu')) return '#6366f1';
                if (t.includes('réalis') || t.includes('realis')) return '#f59e0b';
                if (t.includes('accept')) return '#10b981';
                if (t.includes('refus')) return '#ef4444';
                return '#6366f1';
            }

            /** Colore chaque en-tête de groupe (façon Monday) et ajoute un compteur. */
            function stylerGroupes(board) {
                let courant = null;
                let compte = 0;

                const flush = () => {
                    if (!courant) return;
                    const heading = courant.querySelector('.fi-ta-group-heading');
                    if (!heading) return;
                    let pill = heading.querySelector('.mb-group-count');
                    if (!pill) {
                        pill = document.createElement('span');
                        pill.className = 'mb-group-count';
                        heading.appendChild(pill);
                    }
                    if (pill.textContent !== String(compte)) pill.textContent = compte;
                };

                board.querySelectorAll('table tbody tr').forEach(function (tr) {
                    if (tr.classList.contains('fi-ta-group-header-row')) {
                        flush();
                        compte = 0;
                        courant = tr;
                        const header = tr.querySelector('.fi-ta-group-header');
                        const heading = tr.querySelector('.fi-ta-group-heading');
                        // Libellé sans le compteur déjà injecté.
                        const label = heading ? heading.textContent.replace(/\d+\s*$/, '') : '';
                        if (header) header.style.setProperty('--mb-g', couleurStatut(label));
                    } else if (tr.classList.contains('fi-ta-row')) {
                        compte++;
                    }
                });

                flush();
            }

            function appliquer(board) {
                greffer(board);
                stylerGroupes(board);
            }

            function demarrer() {
                const board = document.querySelector('.mb-board');
                if (!board || board.dataset.mbResize === '1') return;
                board.dataset.mbResize = '1';
                appliquer(board);
                // Le tableau est reconstruit à chaque rendu Livewire (tri, filtre,
                // repli de groupe…) : on ré-applique poignées, couleurs et compteurs.
                new MutationObserver(() => appliquer(board)).observe(board, { childList: true, subtree: true });
            }

            document.addEventListener('DOMContentLoaded', demarrer);
            document.addEventListener('livewire:navigated', demarrer);
            if (document.readyState !== 'loading') demarrer();
        })();
    </script>

    {{-- Conteneur des fenêtres modales des actions « méthode » de la page
         (Ajouter une colonne, Renommer les colonnes, Nouveau tableau) : requis
         pour que ces modales se montent, comme dans les autres vues custom. --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
