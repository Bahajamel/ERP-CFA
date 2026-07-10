@php
    use App\Filament\Resources\Needs\NeedResource;

    $col = '#6366f1';
    $demandes = max(1, (int) $o->nb_postes);
    $restants = $o->postesRestants();
    $pourvus = max(0, $demandes - $restants);
    $pct = (int) round($pourvus / $demandes * 100);
    $complet = $restants === 0;

    $matchings = $o->matchings; // déjà chargé (with matchings.candidate)
    $suggestions = $o->estCloture() ? collect() : $o->candidatsCompatibles(3);
    $editUrl = NeedResource::getUrl('edit', ['record' => $o]);
@endphp

<div class="cfa-focus">
    <div class="cfa-focus-h">
        <span class="cfa-focus-spark">@svg('heroicon-o-sparkles', 'w-4 h-4')</span>
        Focus offre
        <button type="button" class="cfa-focus-close" wire:click="unfocus" title="Fermer" aria-label="Fermer le panneau">
            @svg('heroicon-o-x-mark', 'w-4 h-4')
        </button>
    </div>

    {{-- Identité --}}
    <div class="cfa-focus-id">
        <span class="cfa-focus-avatar cfa-avatar-alt" style="--fa:{{ $col }}">{{ $o->company?->initiales ?? '?' }}</span>
        <div class="cfa-focus-id-txt">
            <div class="cfa-focus-name">{{ $o->intitule_poste }}</div>
            <span class="cfa-focus-badge" style="--fb:{{ $complet ? '#10b981' : $col }}">
                {{ $complet ? 'Pourvu' : $restants.' poste'.($restants > 1 ? 's' : '').' à pourvoir' }}
            </span>
        </div>
    </div>

    {{-- Détails de l'offre --}}
    <ul class="cfa-focus-meta">
        @if ($o->company)<li>@svg('heroicon-o-building-office-2', 'w-4 h-4'){{ $o->company->raison_sociale }}</li>@endif
        @if ($o->formation)<li>@svg('heroicon-o-academic-cap', 'w-4 h-4'){{ $o->formation->libelle }}</li>@endif
        @if ($o->localisation)<li>@svg('heroicon-o-map-pin', 'w-4 h-4'){{ $o->localisation }}</li>@endif
        @if ($o->rythme)<li>@svg('heroicon-o-clock', 'w-4 h-4'){{ $o->rythme }}</li>@endif
        @if ($o->date_demarrage)<li>@svg('heroicon-o-calendar-days', 'w-4 h-4')Démarrage : {{ $o->date_demarrage->translatedFormat('d M Y') }}</li>@endif
    </ul>

    <a href="{{ $editUrl }}" class="cfa-focus-btn primaire">
        @svg('heroicon-o-pencil-square', 'w-4 h-4') Ouvrir / modifier l'offre
    </a>

    {{-- Avancement du recrutement --}}
    <div class="cfa-focus-card">
        <div class="cfa-focus-card-h">@svg('heroicon-o-chart-bar', 'w-4 h-4') Recrutement</div>
        <div class="cfa-need-bar" role="img" aria-label="{{ $pourvus }} poste(s) pourvu(s) sur {{ $demandes }}">
            <div class="cfa-need-bar-track">
                <span class="cfa-need-bar-fill {{ $complet ? 'complet' : '' }}" style="width: {{ $pct }}%"></span>
            </div>
            <span class="cfa-need-bar-lbl">{{ $pourvus }}/{{ $demandes }} pourvu{{ $demandes > 1 ? 's' : '' }}</span>
        </div>
    </div>

    {{-- Candidats proposés (matchings de l'offre) --}}
    <div class="cfa-focus-card">
        <div class="cfa-focus-card-h">@svg('heroicon-o-user-group', 'w-4 h-4') Candidats proposés ({{ $matchings->count() }})</div>
        @forelse ($matchings as $m)
            <div class="cfa-focus-need">
                <span>{{ $m->candidate?->nom_complet ?? 'Candidat' }}</span>
                <span class="cfa-focus-need-badge">{{ $m->statut->getLabel() }}</span>
            </div>
        @empty
            <div class="cfa-focus-muted">Aucun candidat proposé pour l'instant.</div>
        @endforelse
    </div>

    {{-- Suggestions : candidats compatibles non encore proposés --}}
    @if ($suggestions->isNotEmpty())
        <div class="cfa-focus-card">
            <div class="cfa-focus-card-h">@svg('heroicon-o-arrows-right-left', 'w-4 h-4') Suggestions à proposer</div>
            @foreach ($suggestions as $s)
                <div class="cfa-focus-match">
                    <span class="cfa-focus-match-avatar">{{ $s['candidate']->initiales }}</span>
                    <span class="cfa-focus-match-txt">
                        <span class="cfa-focus-match-name">{{ $s['candidate']->nom_complet }}</span>
                        <span class="cfa-focus-match-sub">{{ $s['explication'] }}</span>
                    </span>
                    <span class="cfa-focus-match-score">{{ $s['score'] }}%</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
