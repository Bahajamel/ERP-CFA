@php
    use App\Filament\Resources\Candidates\CandidateResource;

    $tones = ['gray' => '#64748b', 'info' => '#3b82f6', 'warning' => '#f59e0b', 'success' => '#10b981', 'danger' => '#f43f5e'];
    $col = $c ? ($tones[$c->statut->getColor()] ?? '#64748b') : '#64748b';
    $manquants = $c ? $c->piecesManquantes() : [];
    $entretien = $c ? $c->entretienActif() : null;
    $interactions = $c ? $c->interactions()->limit(3)->get() : collect();
    $parcours = $c ? $c->parcoursFocus() : null;

    // Action contextuelle selon l'étape réelle du cycle (jamais « planifier un
    // entretien » pour un candidat déjà accepté / en matching).
    $ctas = [
        'entretien' => ['Planifier un entretien', 'heroicon-o-calendar-days'],
        'matching' => ['Suivre le matching', 'heroicon-o-arrows-right-left'],
        'contrat' => ['Voir le contrat', 'heroicon-o-document-text'],
        'opco' => ['Suivre le dossier OPCO', 'heroicon-o-banknotes'],
        'admission' => ['Finaliser l\'admission', 'heroicon-o-check-badge'],
        'rupture' => ['Gérer la rupture', 'heroicon-o-exclamation-triangle'],
    ];
@endphp

<div class="cfa-focus">
    <div class="cfa-focus-h">
        <span class="cfa-focus-spark">@svg('heroicon-o-sparkles', 'w-4 h-4')</span>
        Focus du jour
        <button type="button" class="cfa-focus-close" wire:click="unfocus" title="Fermer" aria-label="Fermer le panneau">
            @svg('heroicon-o-x-mark', 'w-4 h-4')
        </button>
    </div>

    @if (! $c)
        <div class="cfa-focus-empty">
            @svg('heroicon-o-cursor-arrow-rays', 'w-6 h-6')
            <span>Cliquez sur un candidat pour afficher son focus.</span>
        </div>
    @else
        {{-- Identité --}}
        <div class="cfa-focus-id">
            <span class="cfa-focus-avatar" style="--fa:{{ $col }}">{{ $c->initiales }}</span>
            <div class="cfa-focus-id-txt">
                <div class="cfa-focus-name">{{ $c->nom_complet }}</div>
                <span class="cfa-focus-badge" style="--fb:{{ $col }}">{{ $c->statut->getLabel() }}</span>
            </div>
        </div>

        <ul class="cfa-focus-meta">
            @if ($c->email)<li>@svg('heroicon-o-envelope', 'w-4 h-4'){{ $c->email }}</li>@endif
            @if ($c->telephone)<li>@svg('heroicon-o-phone', 'w-4 h-4'){{ $c->telephone }}</li>@endif
            @if ($c->formationVisee)<li>@svg('heroicon-o-academic-cap', 'w-4 h-4'){{ $c->formationVisee->libelle }}</li>@endif
            <li>@svg('heroicon-o-user', 'w-4 h-4')Commercial : {{ $c->commercial?->name ?? 'non assigné' }}</li>
        </ul>

        <a href="{{ CandidateResource::getUrl('view', ['record' => $c]) }}" class="cfa-focus-btn primaire">
            @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4') Ouvrir la fiche complète
        </a>

        {{-- Pièces de candidature — obligatoires dès le formulaire (étape 1 du
             cycle). Un candidat existe donc TOUJOURS avec ses pièces : une pièce
             absente est une ANOMALIE (dossier créé hors formulaire : import,
             saisie manuelle…), à régulariser d'urgence. --}}
        <div class="cfa-focus-card">
            <div class="cfa-focus-card-h">
                @svg('heroicon-o-document-text', 'w-4 h-4')
                Pièces de candidature
            </div>
            @if (count($manquants))
                <div class="cfa-focus-alerte">
                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4')
                    <span>Anomalie : {{ count($manquants) }} pièce(s) obligatoire(s) absente(s). Ces pièces sont exigées au formulaire de candidature — dossier à régulariser.</span>
                </div>
                <ul class="cfa-focus-docs">
                    @foreach ($manquants as $m)
                        <li><span class="cfa-focus-dot danger"></span>{{ $m }}<span class="cfa-focus-req">Manquant</span></li>
                    @endforeach
                </ul>
                <a href="{{ CandidateResource::getUrl('view', ['record' => $c]) }}" class="cfa-focus-btn">Régulariser le dossier</a>
            @else
                <div class="cfa-focus-ok">@svg('heroicon-o-check-circle', 'w-4 h-4') Pièces obligatoires réunies.</div>
            @endif
        </div>

        {{-- Prochaine étape (contextuelle au cycle réel) --}}
        @if ($parcours['cle'] === 'entretien' && $entretien && $entretien->date_entretien)
            {{-- Un entretien est planifié : on montre le rendez-vous. --}}
            <div class="cfa-focus-card">
                <div class="cfa-focus-card-h">@svg('heroicon-o-calendar-days', 'w-4 h-4') Prochain rendez-vous</div>
                <div class="cfa-focus-rdv">
                    <b>{{ $entretien->date_entretien->translatedFormat('l j F') }}</b>
                    @if ($entretien->heure_debut)<span>{{ \Illuminate\Support\Carbon::parse($entretien->heure_debut)->format('H:i') }}</span>@endif
                </div>
            </div>
        @elseif ($parcours['cle'] === 'complet')
            <div class="cfa-focus-card">
                <div class="cfa-focus-card-h">@svg('heroicon-o-check-badge', 'w-4 h-4') Parcours</div>
                <div class="cfa-focus-ok">@svg('heroicon-o-check-circle', 'w-4 h-4') Apprenant inscrit — parcours complet.</div>
            </div>
        @elseif ($parcours['cle'] === 'refuse')
            <div class="cfa-focus-card">
                <div class="cfa-focus-card-h">@svg('heroicon-o-x-circle', 'w-4 h-4') Parcours</div>
                <div class="cfa-focus-muted">Candidature refusée — aucune action requise.</div>
            </div>
        @else
            {{-- Étape en cours du cycle : action réellement pertinente. --}}
            @php [$ctaLabel, $ctaIcon] = $ctas[$parcours['cle']] ?? ['Ouvrir la fiche', 'heroicon-o-arrow-top-right-on-square']; @endphp
            <div class="cfa-focus-card">
                <div class="cfa-focus-card-h">@svg('heroicon-o-flag', 'w-4 h-4') Prochaine étape</div>
                <div class="cfa-focus-rdv"><b>{{ $parcours['libelle'] }}</b><span>{{ $parcours['detail'] }}</span></div>
                <a href="{{ CandidateResource::getUrl('view', ['record' => $c]) }}" class="cfa-focus-btn">
                    @svg($ctaIcon, 'w-4 h-4') {{ $ctaLabel }}
                </a>
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
            <a href="{{ CandidateResource::getUrl('view', ['record' => $c]) }}" class="cfa-focus-btn">Voir l'historique complet</a>
        </div>
    @endif
</div>
