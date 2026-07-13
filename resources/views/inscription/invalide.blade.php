@extends('candidature.layout')

@section('title', 'Lien invalide')
@section('heading', 'Ce lien n\'est plus valable')
@section('subheading', 'Votre invitation a peut-être expiré ou déjà été utilisée.')

@section('content')
    <div class="py-6 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="h-7 w-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.34 3.94l-7.02 12.14A1.5 1.5 0 004.62 18.5h14.76a1.5 1.5 0 001.3-2.42L13.66 3.94a1.5 1.5 0 00-2.6 0z" />
            </svg>
        </div>
        <p class="mt-4 text-sm text-gray-600">
            Rapprochez-vous du CFA pour recevoir une nouvelle invitation.
        </p>
    </div>
@endsection
