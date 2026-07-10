@php
    use App\Filament\Resources\Companies\CompanyResource;

    // Pas de notion de statut ici : toute entreprise listée est un partenaire.
    $col = '#14b8a6';

    $principal = $e->contactPrincipal->first() ?? $e->contacts->first();
    $tuteur = $e->tuteurs->first();
    $besoins = $e->needs()->ouverts()->get();
    $suggestions = $e->matchingSuggere(3);
    $interactions = $e->interactions()->limit(3)->get();
    $ficheUrl = CompanyResource::getUrl('view', ['record' => $e]);
@endphp

<div class="cfa-focus">
    <div class="cfa-focus-h">
        <span class="cfa-focus-spark">@svg('heroicon-o-sparkles', 'w-4 h-4')</span>
        Focus entreprise
        <button type="button" class="cfa-focus-close" wire:click="unfocus" title="Fermer" aria-label="Fermer le panneau">
            @svg('heroicon-o-x-mark', 'w-4 h-4')
        </button>
    </div>

    {{-- Identité --}}
    <div class="cfa-focus-id">
        <span class="cfa-focus-avatar" style="--fa:{{ $col }}">{{ $e->initiales }}</span>
        <div class="cfa-focus-id-txt">
            <div class="cfa-focus-name">{{ $e->raison_sociale }}</div>
            <span class="cfa-focus-badge" style="--fb:{{ $col }}">Partenaire</span>
        </div>
    </div>

    {{-- Contact principal --}}
    <div class="cfa-focus-card">
        <div class="cfa-focus-card-h">@svg('heroicon-o-user', 'w-4 h-4') Contact principal</div>
        @if ($principal)
            <div class="cfa-focus-rdv"><b>{{ trim($principal->prenom.' '.$principal->nom) }}</b>
                @if ($principal->fonction)<span>{{ $principal->fonction }}</span>@endif</div>
            <ul class="cfa-focus-meta">
                @if ($principal->telephone)<li>@svg('heroicon-o-phone', 'w-4 h-4'){{ $principal->telephone }}</li>@endif
                @if ($principal->email)<li>@svg('heroicon-o-envelope', 'w-4 h-4'){{ $principal->email }}</li>@endif
            </ul>
        @else
            <div class="cfa-focus-muted">Aucun contact enregistré.</div>
        @endif
    </div>

    {{-- Tuteur / maître d'apprentissage --}}
    @if ($tuteur)
        <div class="cfa-focus-card">
            <div class="cfa-focus-card-h">@svg('heroicon-o-academic-cap', 'w-4 h-4') Maître d'apprentissage</div>
            <div class="cfa-focus-rdv"><b>{{ trim($tuteur->prenom.' '.$tuteur->nom) }}</b>
                @if ($tuteur->telephone)<span>{{ $tuteur->telephone }}</span>@endif</div>
        </div>
    @endif

    {{-- Secteur + OPCO --}}
    <ul class="cfa-focus-meta">
        @if ($e->secteur)<li>@svg('heroicon-o-tag', 'w-4 h-4')Secteur : {{ $e->secteur }}</li>@endif
        <li>@svg('heroicon-o-banknotes', 'w-4 h-4')OPCO : {{ $e->opco?->nom ?? 'non déterminé' }}</li>
    </ul>

    <a href="{{ $ficheUrl }}" class="cfa-focus-btn primaire">
        @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4') Ouvrir la fiche complète
    </a>

    {{-- Besoins en cours --}}
    <div class="cfa-focus-card">
        <div class="cfa-focus-card-h">@svg('heroicon-o-briefcase', 'w-4 h-4') Besoins en cours ({{ $besoins->count() }})</div>
        @forelse ($besoins as $b)
            <div class="cfa-focus-need">
                <span>{{ $b->intitule_poste }}</span>
                <span class="cfa-focus-need-badge">{{ $b->postesRestants() }} poste{{ $b->postesRestants() > 1 ? 's' : '' }}</span>
            </div>
        @empty
            <div class="cfa-focus-muted">Aucun besoin ouvert.</div>
        @endforelse
    </div>

    {{-- Matching suggéré (candidats scorés) --}}
    @if (count($suggestions))
        <div class="cfa-focus-card">
            <div class="cfa-focus-card-h">@svg('heroicon-o-arrows-right-left', 'w-4 h-4') Matching suggéré</div>
            @foreach ($suggestions as $s)
                <div class="cfa-focus-match">
                    <span class="cfa-focus-match-avatar">{{ $s['candidate']->initiales }}</span>
                    <span class="cfa-focus-match-txt">
                        <span class="cfa-focus-match-name">{{ $s['candidate']->nom_complet }}</span>
                        <span class="cfa-focus-match-sub">{{ $s['besoin'] }}</span>
                    </span>
                    <span class="cfa-focus-match-score">{{ $s['score'] }}%</span>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Dernières interactions --}}
    <div class="cfa-focus-card">
        <div class="cfa-focus-card-h">@svg('heroicon-o-clock', 'w-4 h-4') Dernières interactions</div>
        @forelse ($interactions as $it)
            <div class="cfa-focus-timeline">
                <span class="cfa-focus-tl-dot"></span>
                <div>
                    <div class="cfa-focus-tl-txt">{{ $it->resume ?: $it->type->getLabel() }}</div>
                    <div class="cfa-focus-tl-date">{{ $it->date_interaction?->translatedFormat('d M') }} · {{ $it->type->getLabel() }}</div>
                </div>
            </div>
        @empty
            <div class="cfa-focus-muted">Aucune interaction enregistrée.</div>
        @endforelse
        <a href="{{ $ficheUrl }}" class="cfa-focus-btn">Voir l'historique complet</a>
    </div>
</div>
