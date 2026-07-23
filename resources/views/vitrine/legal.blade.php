{{-- Gabarit des pages légales (mentions légales, confidentialité).
     Contenu neutre et honnête : aucune identité juridique inventée. Les éléments
     à fournir sont marqués « [à compléter] ». --}}
@extends('vitrine.layout')

@section('title', $titre.' — Meridian CFA')
@section('description', $titre.' de Meridian CFA, ERP de gestion pour CFA.')

@section('content')
    <section class="bg-slate-50">
        <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
            <a href="/" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                Retour à l'accueil
            </a>
            <h1 class="mt-6 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">{{ $titre }}</h1>
            <p class="mt-2 text-sm text-slate-500">Dernière mise à jour : {{ now()->translatedFormat('F Y') }}</p>

            <div class="mt-10 space-y-8">
                @foreach ($sections as $section)
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ $section['titre'] }}</h2>
                        <div class="mt-3 space-y-3 text-sm leading-relaxed text-slate-600">
                            @foreach ($section['paragraphes'] as $paragraphe)
                                <p>{!! $paragraphe !!}</p>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="mt-12 rounded-xl bg-amber-50 p-4 text-xs text-amber-800 ring-1 ring-amber-600/10">
                Ce document est un modèle. Les informations entre crochets <span class="font-semibold">[à compléter]</span>
                doivent être renseignées avec l'identité juridique réelle de l'éditeur avant toute mise en ligne publique.
            </p>
        </div>
    </section>
@endsection