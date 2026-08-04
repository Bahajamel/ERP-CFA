@extends('portail.entreprise.layout')

@section('title', 'Accueil')

@section('content')
    @php
        $taux = $assiduite['taux'];
        $tauxCouleur = $taux === null ? 'text-slate-400'
            : ($taux >= 90 ? 'text-emerald-600' : ($taux >= 70 ? 'text-amber-600' : 'text-rose-600'));
        $absences = $assiduite['absences_injustifiees'];
    @endphp

    {{-- Introduction --}}
    <div class="mb-5">
        <h2 class="text-2xl font-bold text-slate-900">Bienvenue <span class="align-middle">👋</span></h2>
        <p class="mt-1 text-slate-500">Le suivi de vos alternants en un coup d'œil.</p>
    </div>

    {{-- Deux grandes cartes (comme l'espace apprenant) --}}
    <div class="grid gap-4 sm:grid-cols-2">
        {{-- Assiduité globale --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-100 text-emerald-600">
                    @include('portail.apprenant._icon', ['name' => 'shield', 'class' => 'h-6 w-6'])
                </span>
                <h3 class="font-semibold text-slate-700">Assiduité globale</h3>
            </div>
            <p class="mt-4 text-5xl font-extrabold {{ $tauxCouleur }}">{{ $taux === null ? '—' : $taux.'%' }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $assiduite['presents'] }} présence(s) sur {{ $assiduite['renseignees'] }} séance(s)</p>
            <svg class="pointer-events-none absolute -bottom-2 right-3 h-24 w-28 opacity-90" viewBox="0 0 120 96" fill="none" aria-hidden="true">
                <circle cx="70" cy="46" r="42" fill="#ecfdf5"/>
                <rect x="42" y="52" width="12" height="22" rx="3" fill="#a7f3d0"/>
                <rect x="60" y="40" width="12" height="34" rx="3" fill="#6ee7b7"/>
                <rect x="78" y="28" width="12" height="46" rx="3" fill="#34d399"/>
                <circle cx="92" cy="26" r="11" fill="#10b981"/>
                <path d="M87 26l3.5 3.5L97 22" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        {{-- Mes alternants --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-100 text-indigo-600">
                    @include('portail.apprenant._icon', ['name' => 'users', 'class' => 'h-6 w-6'])
                </span>
                <h3 class="font-semibold text-slate-700">Mes alternants</h3>
            </div>
            <p class="mt-4 text-5xl font-extrabold text-slate-800">{{ $alternants->count() }}</p>
            <p class="mt-1 text-sm text-slate-500">en contrat d'alternance</p>
            <svg class="pointer-events-none absolute -bottom-1 right-3 h-24 w-28 opacity-90" viewBox="0 0 120 96" fill="none" aria-hidden="true">
                <circle cx="72" cy="46" r="42" fill="#eef2ff"/>
                <circle cx="58" cy="40" r="11" fill="#a5b4fc"/>
                <path d="M40 76c0-11 8-18 18-18s18 7 18 18" fill="#c7d2fe"/>
                <circle cx="86" cy="44" r="9" fill="#818cf8"/>
                <path d="M72 76c0-9 7-15 14-15s14 6 14 15" fill="#a5b4fc"/>
            </svg>
        </div>
    </div>

    {{-- Absences injustifiées (pleine largeur, comme l'alternance côté apprenant) --}}
    <div class="relative mt-4 overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-xl {{ $absences > 0 ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600' }}">
                @include('portail.apprenant._icon', ['name' => $absences > 0 ? 'alert' : 'shield', 'class' => 'h-6 w-6'])
            </span>
            <h3 class="font-semibold text-slate-700">Absences injustifiées</h3>
        </div>
        <p class="mt-3 text-4xl font-extrabold {{ $absences > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ $absences }}</p>
        <p class="mt-1 max-w-md text-sm text-slate-500">
            {{ $absences > 0 ? 'Absence(s) injustifiée(s) sur l\'ensemble de vos alternants — à surveiller avec le CFA.' : 'Aucune absence injustifiée à signaler. Tout est en ordre.' }}
        </p>
        @if ($absences > 0)
            <svg class="pointer-events-none absolute -bottom-2 right-4 hidden h-24 w-28 opacity-90 sm:block" viewBox="0 0 120 96" fill="none" aria-hidden="true">
                <circle cx="70" cy="48" r="40" fill="#fff1f2"/>
                <path d="M70 26l24 42H46l24-42Z" fill="#fda4af"/>
                <path d="M70 44v12" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                <circle cx="70" cy="62" r="2.4" fill="#fff"/>
            </svg>
        @else
            <svg class="pointer-events-none absolute -bottom-2 right-4 hidden h-24 w-28 opacity-90 sm:block" viewBox="0 0 120 96" fill="none" aria-hidden="true">
                <circle cx="70" cy="46" r="40" fill="#ecfdf5"/>
                <circle cx="70" cy="46" r="20" fill="#34d399"/>
                <path d="M62 46l6 6 12-12" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        @endif
    </div>

    {{-- Aperçu alternants --}}
    <div class="mt-6 flex items-center justify-between">
        <h3 class="font-semibold text-slate-700">Vos alternants</h3>
        @if ($alternants->count() > 4)
            <a href="{{ route('portail.entreprise.alternants', ['token' => $token]) }}" class="pa-accent text-sm font-semibold">Voir tous →</a>
        @endif
    </div>
    <div class="mt-2 space-y-2">
        @forelse ($alternants->take(4) as $row)
            @include('portail.entreprise._alternant', ['row' => $row])
        @empty
            <p class="rounded-xl bg-white p-5 text-center text-sm text-slate-500 ring-1 ring-slate-900/5">Aucun alternant enregistré pour le moment.</p>
        @endforelse
    </div>

    {{-- Accès rapides --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('portail.entreprise.documents', ['token' => $token]) }}"
           class="group flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-slate-900/10">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-indigo-100 text-indigo-600">
                @include('portail.apprenant._icon', ['name' => 'folder', 'class' => 'h-6 w-6'])
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-slate-700">Documents</h3>
                <p class="mt-0.5 text-sm text-slate-500">{{ $nbDocuments }} document(s) disponible(s)</p>
            </div>
            <span class="pa-accent transition group-hover:translate-x-0.5">@include('portail.apprenant._icon', ['name' => 'arrow-right', 'class' => 'h-5 w-5'])</span>
        </a>
        <a href="{{ route('portail.entreprise.factures', ['token' => $token]) }}"
           class="group flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-slate-900/10">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-indigo-100 text-indigo-600">
                @include('portail.apprenant._icon', ['name' => 'banknotes', 'class' => 'h-6 w-6'])
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-slate-700">Factures</h3>
                <p class="mt-0.5 text-sm text-slate-500">{{ $nbFactures }} facture(s)</p>
            </div>
            <span class="pa-accent transition group-hover:translate-x-0.5">@include('portail.apprenant._icon', ['name' => 'arrow-right', 'class' => 'h-5 w-5'])</span>
        </a>
    </div>
@endsection