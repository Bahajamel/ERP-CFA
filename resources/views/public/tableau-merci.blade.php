@extends('public.layout')

@section('title', 'Formulaire envoyé')
@section('heading', 'Merci, c\'est bien envoyé ✅')
@section('subheading', 'Vos informations ont été transmises à l\'organisme.')

@section('content')
    <div class="py-6 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>
        <p class="mt-4 text-slate-600">
            Votre envoi a bien été enregistré.<br>
            Vous pouvez fermer cette page.
        </p>
    </div>
@endsection
