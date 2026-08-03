@extends('candidature.layout')

@section('title', 'Lien invalide')
@section('heading', 'Feuille d\'émargement')
@section('subheading', 'Ce lien de signature n\'est pas valable.')

@section('content')
    <div class="rounded-xl bg-amber-50 p-4 text-center ring-1 ring-amber-100">
        <p class="text-sm text-amber-900">
            Ce lien de signature est invalide ou expiré. Rapprochez-vous de votre CFA
            pour obtenir un nouveau lien.
        </p>
    </div>
@endsection