@extends('candidature.layout')

@section('title', 'Inscription enregistrée')
@section('heading', 'Merci, votre inscription est enregistrée ✅')
@section('subheading', 'Vos matières ont bien été prises en compte. À bientôt au CFA !')

@section('content')
    <div class="py-6 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>
        <p class="mt-4 text-sm text-gray-600">
            L'équipe pédagogique vous communiquera votre emploi du temps.
        </p>
    </div>
@endsection
