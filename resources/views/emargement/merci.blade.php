@extends('candidature.layout')

@section('title', 'Présence signée')
@section('heading', 'Merci !')
@section('subheading', 'Votre présence a été enregistrée.')

@section('content')
    <div class="rounded-xl bg-emerald-50 p-5 text-center ring-1 ring-emerald-100">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="mx-auto h-10 w-10 text-emerald-500">
            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
        </svg>
        <p class="mt-3 text-sm font-semibold text-emerald-900">Votre présence est signée.</p>
        <p class="mt-1 text-sm text-emerald-800">Vous pouvez fermer cette page.</p>
    </div>
@endsection