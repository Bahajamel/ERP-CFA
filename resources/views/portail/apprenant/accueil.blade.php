@extends('portail.apprenant.layout')

@section('title', 'Accueil')

@section('content')
    @php
        $taux = $assiduite['taux'];
        $tauxCouleur = $taux === null ? 'text-slate-400'
            : ($taux >= 90 ? 'text-emerald-600' : ($taux >= 70 ? 'text-amber-600' : 'text-rose-600'));
    @endphp

    {{-- Introduction --}}
    <div class="mb-5">
        <h2 class="text-2xl font-bold text-slate-900">Bonjour {{ $candidate->prenom }} <span class="align-middle">👋</span></h2>
        <p class="mt-1 text-slate-500">Voici un aperçu de votre espace personnel.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        {{-- Mon assiduité --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald-100 text-emerald-600">
                    @include('portail.apprenant._icon', ['name' => 'shield', 'class' => 'h-6 w-6'])
                </span>
                <h3 class="font-semibold text-slate-700">Mon assiduité</h3>
            </div>
            <p class="mt-4 text-5xl font-extrabold {{ $tauxCouleur }}">{{ $taux === null ? '—' : $taux.'%' }}</p>
            <p class="mt-1 text-sm text-slate-500">
                {{ $assiduite['presents'] }} présence(s) sur {{ $assiduite['renseignees'] }} séance(s) renseignée(s)
            </p>
            {{-- Illustration légère : barres de progression --}}
            <svg class="pointer-events-none absolute -bottom-2 right-3 h-24 w-28 opacity-90" viewBox="0 0 120 96" fill="none" aria-hidden="true">
                <circle cx="70" cy="46" r="42" fill="#ecfdf5"/>
                <rect x="42" y="52" width="12" height="22" rx="3" fill="#a7f3d0"/>
                <rect x="60" y="40" width="12" height="34" rx="3" fill="#6ee7b7"/>
                <rect x="78" y="28" width="12" height="46" rx="3" fill="#34d399"/>
                <circle cx="92" cy="26" r="11" fill="#10b981"/>
                <path d="M87 26l3.5 3.5L97 22" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        {{-- Prochaine séance --}}
        <div class="relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-100 text-indigo-600">
                    @include('portail.apprenant._icon', ['name' => 'calendar', 'class' => 'h-6 w-6'])
                </span>
                <h3 class="font-semibold text-slate-700">Prochaine séance</h3>
            </div>
            @if ($prochaine && $prochaine->seance)
                @php $s = $prochaine->seance; @endphp
                <p class="mt-4 text-lg font-bold text-slate-800">{{ $s->libelle ?: $s->promotion?->nom_complet }}</p>
                <div class="mt-2 space-y-1 text-sm text-slate-600">
                    <p class="flex items-center gap-1.5">
                        @include('portail.apprenant._icon', ['name' => 'calendar', 'class' => 'h-4 w-4 text-slate-400'])
                        {{ $s->date->translatedFormat('l j F') }}
                        <span class="pa-accent font-semibold">· {{ \Illuminate\Support\Str::limit($s->heure_debut, 5, '') }}–{{ \Illuminate\Support\Str::limit($s->heure_fin, 5, '') }}</span>
                    </p>
                    @if ($s->formateur)
                        <p class="text-slate-500">{{ $s->formateur->name }}</p>
                    @endif
                </div>
                @if (blank($prochaine->signed_at) && filled($prochaine->signature_token))
                    <a href="{{ route('emargement.signer', ['token' => $prochaine->signature_token]) }}"
                       class="pa-accent-bg mt-4 inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold text-white transition hover:opacity-90">
                        @include('portail.apprenant._icon', ['name' => 'pen', 'class' => 'h-4 w-4'])
                        Signer ma présence
                    </a>
                @endif
            @else
                <p class="mt-4 max-w-[16rem] text-sm text-slate-500">Aucune séance à venir pour le moment.</p>
                <svg class="pointer-events-none absolute -bottom-1 right-3 h-24 w-24 opacity-90" viewBox="0 0 96 96" fill="none" aria-hidden="true">
                    <circle cx="48" cy="48" r="40" fill="#eef2ff"/>
                    <rect x="30" y="30" width="36" height="32" rx="5" fill="#c7d2fe"/>
                    <rect x="30" y="30" width="36" height="9" rx="5" fill="#818cf8"/>
                    <circle cx="63" cy="60" r="12" fill="#6366f1"/>
                    <path d="M63 54v6l4 3" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            @endif
        </div>
    </div>

    {{-- Mon alternance (pleine largeur) --}}
    <div class="relative mt-4 overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-amber-100 text-amber-600">
                @include('portail.apprenant._icon', ['name' => 'briefcase', 'class' => 'h-6 w-6'])
            </span>
            <h3 class="font-semibold text-slate-700">Mon alternance</h3>
        </div>
        @if ($contrat)
            <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Entreprise</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $contrat->company?->raison_sociale ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Maître d'apprentissage</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $contrat->tuteur?->nom_complet ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Formation</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">{{ $contrat->formation?->libelle ?? $candidate->formationVisee?->libelle ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-400">Période</dt>
                    <dd class="mt-0.5 font-medium text-slate-800">
                        @php $d = $contrat->dateDebutEffective(); $f = $contrat->dateFinEffective(); @endphp
                        {{ $d ? $d->format('d/m/Y') : '—' }} → {{ $f ? $f->format('d/m/Y') : '—' }}
                    </dd>
                </div>
            </dl>
        @else
            <p class="mt-4 max-w-md text-sm text-slate-500">Aucun contrat enregistré pour l'instant.</p>
            <svg class="pointer-events-none absolute -bottom-2 right-4 hidden h-24 w-28 opacity-90 sm:block" viewBox="0 0 120 96" fill="none" aria-hidden="true">
                <path d="M24 44h72v34a6 6 0 0 1-6 6H30a6 6 0 0 1-6-6V44Z" fill="#fef3c7"/>
                <path d="M24 44l10-12h20l6 8h30a6 6 0 0 1 6 6v6H24v-8Z" fill="#fcd34d"/>
                <rect x="44" y="24" width="32" height="22" rx="3" fill="#fff" stroke="#fbbf24" stroke-width="2"/>
                <path d="M50 32h20M50 38h14" stroke="#f59e0b" stroke-width="2" stroke-linecap="round"/>
            </svg>
        @endif
    </div>

    {{-- Mes classes + Mes documents --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-indigo-100 text-indigo-600">
                    @include('portail.apprenant._icon', ['name' => 'cap', 'class' => 'h-6 w-6'])
                </span>
                <h3 class="font-semibold text-slate-700">Mes classes</h3>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @forelse ($classes as $classe)
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700">
                        {{ $classe->nom_complet }}
                    </span>
                @empty
                    <p class="text-sm text-slate-500">Vous n'êtes rattaché à aucune classe pour le moment.</p>
                @endforelse
            </div>
        </div>

        <a href="{{ route('portail.apprenant.documents', ['token' => $token]) }}"
           class="group flex items-center gap-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-slate-900/10">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-indigo-100 text-indigo-600">
                @include('portail.apprenant._icon', ['name' => 'document', 'class' => 'h-6 w-6'])
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-slate-700">Mes documents</h3>
                <p class="mt-0.5 text-sm text-slate-500">{{ $nbDocuments }} document(s) disponible(s)</p>
            </div>
            <span class="pa-accent transition group-hover:translate-x-0.5">
                @include('portail.apprenant._icon', ['name' => 'arrow-right', 'class' => 'h-5 w-5'])
            </span>
        </a>
    </div>
@endsection