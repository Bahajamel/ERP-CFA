@extends('portail.entreprise.layout')

@section('title', 'Accueil')

@section('content')
    @php
        $taux = $assiduite['taux'];
        $tauxCouleur = $taux === null ? 'text-slate-400'
            : ($taux >= 90 ? 'text-emerald-600' : ($taux >= 70 ? 'text-amber-600' : 'text-rose-600'));
        $absences = $assiduite['absences_injustifiees'];
    @endphp

    <div class="mb-5">
        <h2 class="text-2xl font-bold text-slate-900">Bienvenue <span class="align-middle">👋</span></h2>
        <p class="mt-1 text-slate-500">Le suivi de vos alternants en un coup d'œil.</p>
    </div>

    {{-- KPIs --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-100 text-indigo-600">
                    @include('portail.apprenant._icon', ['name' => 'users', 'class' => 'h-5 w-5'])
                </span>
                <h3 class="text-sm font-semibold text-slate-600">Mes alternants</h3>
            </div>
            <p class="mt-3 text-4xl font-extrabold text-slate-800">{{ $alternants->count() }}</p>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald-100 text-emerald-600">
                    @include('portail.apprenant._icon', ['name' => 'shield', 'class' => 'h-5 w-5'])
                </span>
                <h3 class="text-sm font-semibold text-slate-600">Assiduité globale</h3>
            </div>
            <p class="mt-3 text-4xl font-extrabold {{ $tauxCouleur }}">{{ $taux === null ? '—' : $taux.'%' }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $assiduite['presents'] }} / {{ $assiduite['renseignees'] }} séance(s)</p>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-xl {{ $absences > 0 ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-500' }}">
                    @include('portail.apprenant._icon', ['name' => 'alert', 'class' => 'h-5 w-5'])
                </span>
                <h3 class="text-sm font-semibold text-slate-600">Absences injustifiées</h3>
            </div>
            <p class="mt-3 text-4xl font-extrabold {{ $absences > 0 ? 'text-rose-600' : 'text-slate-800' }}">{{ $absences }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $absences > 0 ? 'À surveiller' : 'Rien à signaler' }}</p>
        </div>
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