@extends('candidature.layout')

@section('title', 'Candidature envoyée')
@section('heading', 'Merci, votre candidature est bien reçue ✅')
@section('subheading', 'Notre équipe va l\'étudier et vous recontactera.')

@section('content')
    <div class="py-6 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>
        <p class="mt-4 text-slate-600">
            Votre dossier et vos pièces ont bien été transmis au CFA.<br>
            Vous serez recontacté(e) par email ou téléphone.
        </p>
        {{-- Retour vers le formulaire du MÊME CFA (slug flashé par le contrôleur). --}}
        @php $slug = session('cfa_slug') ?? request('cfa'); @endphp
        <a href="{{ route('candidature.create', $slug ? ['cfa' => $slug] : []) }}"
            class="mt-6 inline-block rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
            Déposer une autre candidature
        </a>
    </div>
@endsection
