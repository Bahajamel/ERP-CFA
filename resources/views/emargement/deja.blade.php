@extends('candidature.layout')

@section('title', 'Présence déjà signée')
@section('heading', 'Feuille d\'émargement')
@section('subheading', 'Votre présence est déjà enregistrée.')

@section('content')
    <div class="rounded-xl bg-emerald-50 p-4 text-center ring-1 ring-emerald-100">
        <p class="text-sm text-emerald-900">
            Merci <strong>{{ $presence->candidate?->prenom }}</strong>, votre présence a bien été signée
            @if ($presence->signed_at)
                le <strong>{{ $presence->signed_at->format('d/m/Y à H:i') }}</strong>.
            @else
                .
            @endif
        </p>
        <p class="mt-2 text-sm text-emerald-800">Vous pouvez fermer cette page.</p>
    </div>
@endsection