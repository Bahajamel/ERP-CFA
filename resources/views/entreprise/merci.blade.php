@extends('entreprise.layout')

@section('title', 'Demande envoyée')
@section('heading', 'Merci, votre demande est bien reçue ✅')
@section('subheading', 'Notre équipe vous recontacte pour finaliser le partenariat.')

@section('content')
    <div class="py-6 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>
        @if (session('besoin_depose'))
            <p class="mt-4 text-slate-600">
                Votre entreprise et votre besoin ont bien été transmis au CFA.<br>
                Un conseiller étudie votre demande et vous proposera des candidats.
            </p>
        @else
            <p class="mt-4 text-slate-600">
                Votre entreprise et votre contact ont bien été transmis au CFA.<br>
                Un conseiller vous recontactera prochainement.
            </p>
        @endif
        <a href="{{ route('entreprise.create') }}"
            class="mt-6 inline-block rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200">
            Enregistrer une autre entreprise
        </a>
    </div>
@endsection
