<x-filament-panels::page>
    @php $qc = $this->getQuickCounts(); @endphp

    <div class="mb-board">
        {{-- Filtres rapides = dossiers qui demandent une intervention (cliquables) --}}
        <div class="cfa-cand-quick">
            <button type="button" wire:click="setQuickScope('a_planifier')"
                class="cfa-quick {{ $quickScope === 'a_planifier' ? 'actif' : '' }}" style="--q:#3b82f6"
                aria-pressed="{{ $quickScope === 'a_planifier' ? 'true' : 'false' }}">
                <span class="cfa-quick-ico">@svg('heroicon-o-calendar-days', 'w-5 h-5')</span>
                <span class="cfa-quick-txt">
                    <b>{{ $qc['a_planifier'] }} entretiens à planifier</b>
                    <small>Premier échange à organiser</small>
                </span>
            </button>

            <button type="button" wire:click="setQuickScope('a_decider')"
                class="cfa-quick {{ $quickScope === 'a_decider' ? 'actif' : '' }}" style="--q:#f59e0b"
                aria-pressed="{{ $quickScope === 'a_decider' ? 'true' : 'false' }}">
                <span class="cfa-quick-ico">@svg('heroicon-o-clipboard-document-check', 'w-5 h-5')</span>
                <span class="cfa-quick-txt">
                    <b>{{ $qc['a_decider'] }} décisions en attente</b>
                    <small>Entretien réalisé — à accepter ou refuser</small>
                </span>
            </button>

            <button type="button" wire:click="setQuickScope('a_orienter')"
                class="cfa-quick {{ $quickScope === 'a_orienter' ? 'actif' : '' }}" style="--q:#10b981"
                aria-pressed="{{ $quickScope === 'a_orienter' ? 'true' : 'false' }}">
                <span class="cfa-quick-ico">@svg('heroicon-o-paper-airplane', 'w-5 h-5')</span>
                <span class="cfa-quick-txt">
                    <b>{{ $qc['a_orienter'] }} acceptés à orienter</b>
                    <small>À envoyer vers une entreprise</small>
                </span>
            </button>
        </div>

        {{-- Barre d'outils « Monday » : Ajouter une colonne / Nouveau tableau,
             placée juste AU-DESSUS du filtre (première rangée du tableau). --}}
        <div class="mb-toolbar">
            {{ $this->ajouterColonneAction }}
            {{ $this->nouveauTableauAction }}
        </div>

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
    </style>
</x-filament-panels::page>
