@extends('portail.apprenant.layout')

@section('title', 'Mon planning')

@section('content')
    @php
        /** Classes du « chip » d'une séance selon le statut de présence. */
        $chip = fn ($statut) => match ($statut->getColor()) {
            'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'warning' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'danger' => 'bg-rose-50 text-rose-700 ring-rose-200',
            default => 'bg-slate-100 text-slate-600 ring-slate-200',
        };
        $titre = fn ($p) => $p->seance->libelle ?: ($p->seance->promotion?->nom_complet ?? 'Séance');
        $jours = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    @endphp

    {{-- En-tête + navigation de mois --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Mon planning</h2>
            <p class="mt-1 text-slate-500">{{ $duMois->count() }} séance(s) ce mois-ci.</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($mois->format('Y-m') !== $moisCourant)
                <a href="{{ route('portail.apprenant.planning', ['token' => $token, 'mois' => $moisCourant]) }}"
                   class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm ring-1 ring-slate-900/5 hover:text-slate-900">
                    Aujourd'hui
                </a>
            @endif
            <div class="flex items-center gap-1 rounded-lg bg-white p-1 shadow-sm ring-1 ring-slate-900/5">
                <a href="{{ route('portail.apprenant.planning', ['token' => $token, 'mois' => $moisPrecedent]) }}"
                   class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label="Mois précédent">‹</a>
                <span class="min-w-[9.5rem] text-center text-sm font-semibold capitalize text-slate-800">{{ $mois->translatedFormat('F Y') }}</span>
                <a href="{{ route('portail.apprenant.planning', ['token' => $token, 'mois' => $moisSuivant]) }}"
                   class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800" aria-label="Mois suivant">›</a>
            </div>
        </div>
    </div>

    {{-- ── Calendrier (desktop / tablette) ─────────────────────────────── --}}
    <div class="hidden overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5 sm:block">
        <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/70">
            @foreach ($jours as $j)
                <div class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $j }}</div>
            @endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach ($semaines as $semaine)
                @foreach ($semaine as $jour)
                    @php
                        $dansMois = $jour->isSameMonth($mois);
                        $duJour = $parJour[$jour->toDateString()] ?? collect();
                        $today = $jour->isToday();
                    @endphp
                    <div class="min-h-[104px] border-b border-r border-slate-100 p-1.5 {{ $dansMois ? 'bg-white' : 'bg-slate-50/50' }}">
                        <div class="mb-1 flex justify-end">
                            <span class="grid h-6 w-6 place-items-center rounded-full text-xs font-semibold
                                {{ $today ? 'pa-accent-bg text-white' : ($dansMois ? 'text-slate-600' : 'text-slate-300') }}">
                                {{ $jour->format('j') }}
                            </span>
                        </div>
                        <div class="space-y-1">
                            @foreach ($duJour as $p)
                                @php
                                    $signable = blank($p->signed_at) && filled($p->signature_token);
                                    $heure = \Illuminate\Support\Str::limit($p->seance->heure_debut, 5, '');
                                    $classesChip = 'block rounded-md px-1.5 py-1 text-[11px] leading-tight ring-1 '.$chip($p->statut);
                                @endphp
                                @if ($signable)
                                    <a href="{{ route('emargement.signer', ['token' => $p->signature_token]) }}"
                                       class="{{ $classesChip }} hover:opacity-80" title="{{ $titre($p) }} — {{ $heure }} (à signer)">
                                        <span class="font-semibold">{{ $heure }}</span>
                                        <span class="block truncate font-medium">{{ $titre($p) }}</span>
                                    </a>
                                @else
                                    <div class="{{ $classesChip }}" title="{{ $titre($p) }} — {{ $heure }}">
                                        <span class="font-semibold">{{ $heure }}</span>
                                        <span class="block truncate font-medium">{{ $titre($p) }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- ── Agenda (mobile) ─────────────────────────────────────────────── --}}
    <div class="space-y-4 sm:hidden">
        @forelse ($parJour as $jour => $duJour)
            @php $date = \Illuminate\Support\Carbon::parse($jour); @endphp
            <div>
                <div class="mb-1.5 flex items-center gap-2">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $date->isToday() ? 'pa-accent-bg text-white' : 'bg-slate-100 text-slate-600' }}">
                        <span class="text-sm font-bold leading-none">{{ $date->format('j') }}</span>
                    </span>
                    <p class="text-sm font-semibold capitalize text-slate-700">{{ $date->translatedFormat('l') }}</p>
                </div>
                <div class="space-y-2 pl-11">
                    @foreach ($duJour as $p)
                        @php $signable = blank($p->signed_at) && filled($p->signature_token); @endphp
                        <div class="flex items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-900/5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-slate-800">{{ $titre($p) }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ \Illuminate\Support\Str::limit($p->seance->heure_debut, 5, '') }}–{{ \Illuminate\Support\Str::limit($p->seance->heure_fin, 5, '') }}
                                    @if ($p->seance->formateur) · {{ $p->seance->formateur->name }} @endif
                                </p>
                            </div>
                            @if ($signable)
                                <a href="{{ route('emargement.signer', ['token' => $p->signature_token]) }}"
                                   class="pa-accent-bg inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold text-white">Signer</a>
                            @elseif ($p->signed_at)
                                <span class="text-xs font-semibold text-emerald-600">✓ Signé</span>
                            @else
                                @include('portail.apprenant._statut', ['statut' => $p->statut])
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="rounded-xl bg-white p-5 text-center text-sm text-slate-500 ring-1 ring-slate-900/5">Aucune séance ce mois-ci.</p>
        @endforelse
    </div>

    {{-- Légende --}}
    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>Présent</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>Retard / départ</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>Absence</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>À venir / non renseigné</span>
    </div>
@endsection