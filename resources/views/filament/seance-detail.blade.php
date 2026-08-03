@php
    use App\Enums\PresenceStatut;

    $presences = $seance->presences;
    $total = $presences->count();
    $presents = $presences->whereIn('statut', PresenceStatut::presents())->count();
    $retards = $presences->where('statut', PresenceStatut::Retard)->count();
    $absents = $presences->filter(fn ($p) => $p->statut?->estAbsence())->count();
    $nonRenseignees = $presences->where('statut', PresenceStatut::NonRenseigne)->count();
    $signes = $presences->whereNotNull('signed_at')->count();
    $taux = $seance->tauxPresence();

    $statut = $seance->statutAffiche();
    $emargement = $seance->etatEmargement();
    $scanDepose = $seance->feuilleEmargement() !== null;
    $justifManquant = $presences
        ->filter(fn ($p) => $p->statut === PresenceStatut::AbsentJustifie && ! $p->aJustificatif())
        ->count();

    $chip = [
        'gray' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-400',
        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
        'info' => 'bg-sky-100 text-sky-700 dark:bg-sky-400/10 dark:text-sky-400',
        'danger' => 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-400',
    ];
@endphp

<div class="space-y-5 text-sm">

    {{-- Statuts en tête --}}
    <div class="flex flex-wrap gap-2">
        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $chip[$statut['color']] }}">{{ $statut['label'] }}</span>
        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $chip[$emargement['color']] }}">Émargement : {{ $emargement['label'] }}</span>
    </div>

    {{-- Informations --}}
    <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
        <div class="col-span-2">
            <dt class="text-xs uppercase tracking-wide text-gray-400">Formation</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $seance->promotion?->formation?->libelle ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-400">Session</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $seance->promotion?->nom_complet ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-400">Matière</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $seance->libelle ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-400">Formateur</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $seance->formateur?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-400">Date</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $seance->date->format('d/m/Y') }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-400">Horaires</dt>
            <dd class="font-medium text-gray-900 dark:text-white">
                @if ($seance->heure_debut){{ \Illuminate\Support\Carbon::parse($seance->heure_debut)->format('H\hi') }}@if ($seance->heure_fin) – {{ \Illuminate\Support\Carbon::parse($seance->heure_fin)->format('H\hi') }}@endif @else — @endif
            </dd>
        </div>
        <div class="col-span-2">
            <dt class="text-xs uppercase tracking-wide text-gray-400">Lieu</dt>
            <dd class="font-medium text-gray-900 dark:text-white">{{ $seance->promotion?->lieuFormationLisible() ?? '—' }}</dd>
        </div>
    </dl>

    {{-- Compteurs --}}
    <div class="grid grid-cols-4 gap-2 text-center">
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $total }}</p>
            <p class="text-xs text-gray-500">Apprenants</p>
        </div>
        <div class="rounded-lg bg-emerald-50 p-3 dark:bg-emerald-400/10">
            <p class="text-lg font-bold text-emerald-700 dark:text-emerald-400">{{ $presents }}</p>
            <p class="text-xs text-gray-500">Présents</p>
        </div>
        <div class="rounded-lg bg-rose-50 p-3 dark:bg-rose-400/10">
            <p class="text-lg font-bold text-rose-700 dark:text-rose-400">{{ $absents }}</p>
            <p class="text-xs text-gray-500">Absents</p>
        </div>
        <div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-400/10">
            <p class="text-lg font-bold text-amber-700 dark:text-amber-400">{{ $retards }}</p>
            <p class="text-xs text-gray-500">Retards</p>
        </div>
    </div>

    <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-white/10">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-400">Taux de présence</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $taux === null ? '—' : $taux.' %' }}</p>
        </div>
        <div class="text-right">
            <p class="text-xs uppercase tracking-wide text-gray-400">Signatures</p>
            <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $signes }}/{{ $total }}</p>
        </div>
    </div>

    {{-- Conformité Qualiopi --}}
    <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Conformité Qualiopi</p>
        <div class="flex flex-wrap gap-2">
            @if ($nonRenseignees === 0 && $total > 0)
                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $chip['success'] }}">✓ Présences complètes</span>
            @else
                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $chip['warning'] }}">⚠ {{ $nonRenseignees }} présence(s) à renseigner</span>
            @endif

            @if ($scanDepose)
                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $chip['success'] }}">✓ Preuve archivée (scan)</span>
            @else
                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $chip['gray'] }}">Feuille scannée non déposée</span>
            @endif

            @if ($justifManquant > 0)
                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $chip['danger'] }}">⚠ {{ $justifManquant }} justificatif(s) manquant(s)</span>
            @endif
        </div>
    </div>
</div>