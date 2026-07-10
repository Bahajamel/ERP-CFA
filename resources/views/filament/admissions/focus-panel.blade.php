@php
    use App\Enums\AdmissionStatut;
    use App\Filament\Resources\Admissions\AdmissionResource;
    use App\Filament\Resources\Ruptures\RuptureResource;
    use App\Models\Admission;

    $tones = ['info' => '#3b82f6', 'success' => '#10b981', 'danger' => '#f43f5e'];
    $col = $tones[$a->statut->getColor()] ?? '#3b82f6';

    $c = $a->candidate;
    $contrat = $a->contract;

    // Pièces obligatoires (source fiable : documents réels du candidat).
    $presents = $c ? $c->documents->pluck('type')->map(fn ($t) => $t instanceof \BackedEnum ? $t->value : (string) $t)->all() : [];
    $pieces = collect(Admission::PIECES_OBLIGATOIRES)->map(fn ($t) => [
        'label' => $t->getLabel(),
        'present' => in_array($t->value, $presents, true),
    ]);
    $manquantes = $pieces->where('present', false)->count();

    $editUrl = AdmissionResource::getUrl('edit', ['record' => $a]);
@endphp

<div class="cfa-focus">
    <div class="cfa-focus-h">
        <span class="cfa-focus-spark">@svg('heroicon-o-sparkles', 'w-4 h-4')</span>
        Focus du jour
        <button type="button" class="cfa-focus-close" wire:click="unfocus" title="Fermer" aria-label="Fermer le panneau">
            @svg('heroicon-o-x-mark', 'w-4 h-4')
        </button>
    </div>

    {{-- Identité --}}
    <div class="cfa-focus-id">
        <span class="cfa-focus-avatar" style="--fa:{{ $col }}">{{ $c?->initiales ?? '?' }}</span>
        <div class="cfa-focus-id-txt">
            <div class="cfa-focus-name">{{ $c?->nom_complet ?? 'Candidat supprimé' }}</div>
            <span class="cfa-focus-badge" style="--fb:{{ $col }}">{{ $a->statut->getLabel() }}</span>
        </div>
    </div>

    <ul class="cfa-focus-meta">
        @if ($contrat?->company)<li>@svg('heroicon-o-building-office-2', 'w-4 h-4'){{ $contrat->company->raison_sociale }}</li>@endif
        @if ($contrat?->formation)<li>@svg('heroicon-o-academic-cap', 'w-4 h-4'){{ $contrat->formation->libelle }}</li>@endif
        <li>@svg('heroicon-o-banknotes', 'w-4 h-4')OPCO : {{ $contrat?->opcoFile?->statut?->getLabel() ?? 'non déterminé' }}</li>
    </ul>

    <a href="{{ $editUrl }}" class="cfa-focus-btn primaire">
        @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4') Ouvrir le dossier
    </a>

    {{-- Pièces obligatoires de l'admission --}}
    <div class="cfa-focus-card">
        <div class="cfa-focus-card-h">
            @svg('heroicon-o-document-text', 'w-4 h-4')
            Pièces obligatoires ({{ $manquantes }} manquante{{ $manquantes > 1 ? 's' : '' }})
        </div>
        <ul class="cfa-focus-docs">
            @foreach ($pieces as $p)
                <li>
                    <span class="cfa-focus-dot {{ $p['present'] ? 'ok' : 'danger' }}"></span>
                    {{ $p['label'] }}
                    <span class="cfa-focus-req {{ $p['present'] ? 'ok' : '' }}">{{ $p['present'] ? 'Reçue' : 'Manquante' }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    {{-- Prochaine étape (contextuelle au statut réel) --}}
    @if ($a->statut === AdmissionStatut::Rupture)
        <div class="cfa-focus-card">
            <div class="cfa-focus-card-h">@svg('heroicon-o-exclamation-triangle', 'w-4 h-4') Rupture</div>
            <div class="cfa-focus-muted">Contrat rompu — le dossier de rupture prend le relais.</div>
            @if ($contrat?->rupture)
                <a href="{{ RuptureResource::getUrl('edit', ['record' => $contrat->rupture]) }}" class="cfa-focus-btn">
                    @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4') Gérer la rupture
                </a>
            @endif
        </div>
    @elseif ($a->statut === AdmissionStatut::Valide)
        <div class="cfa-focus-card">
            <div class="cfa-focus-card-h">@svg('heroicon-o-check-badge', 'w-4 h-4') Admission</div>
            <div class="cfa-focus-ok">@svg('heroicon-o-check-circle', 'w-4 h-4') Apprenant officiellement inscrit
                @if ($a->validated_at)— le {{ $a->validated_at->translatedFormat('d M Y') }}@endif.</div>
        </div>
    @elseif ($manquantes > 0)
        <div class="cfa-focus-card">
            <div class="cfa-focus-card-h">@svg('heroicon-o-flag', 'w-4 h-4') Prochaine étape</div>
            <div class="cfa-focus-rdv"><b>Réclamer les pièces manquantes</b><span>La validation reste bloquée tant qu'une pièce obligatoire manque.</span></div>
            <a href="{{ $editUrl }}" class="cfa-focus-btn">@svg('heroicon-o-inbox-arrow-down', 'w-4 h-4') Compléter le dossier</a>
        </div>
    @else
        <div class="cfa-focus-card">
            <div class="cfa-focus-card-h">@svg('heroicon-o-flag', 'w-4 h-4') Prochaine étape</div>
            <div class="cfa-focus-rdv"><b>Valider l'admission</b><span>Pièces complètes et financement OPCO acquis : le dossier peut être inscrit.</span></div>
            <a href="{{ $editUrl }}" class="cfa-focus-btn">@svg('heroicon-o-check-badge', 'w-4 h-4') Valider l'admission</a>
        </div>
    @endif
</div>
