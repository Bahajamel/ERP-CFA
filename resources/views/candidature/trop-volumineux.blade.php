@extends('candidature.layout')

@section('title', 'Fichiers trop volumineux')
@section('heading', 'Vos fichiers sont trop volumineux')
@section('subheading', 'Le dépôt dépasse la taille autorisée.')

@section('content')
    <div class="py-6 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
        </div>
        <p class="mt-4 text-slate-600">
            L'ensemble de vos fichiers est trop lourd. Merci de déposer des fichiers
            de <strong>moins de 5 Mo chacun</strong> (compressez vos photos ou scans si besoin).
        </p>
        <a href="{{ route('candidature.create') }}"
            class="mt-6 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500">
            Revenir au formulaire
        </a>
    </div>
@endsection
