@extends('candidature.layout')

@section('title', 'Inscription déjà enregistrée')
@section('heading', 'Vous avez déjà choisi vos matières ✅')
@section('subheading', 'Votre inscription est complète.')

@section('content')
    <div class="py-4">
        <p class="text-sm text-gray-700">
            Classe : <strong>{{ $inscription->promotion?->nom_complet }}</strong>
        </p>
        @if (! empty($inscription->matieres))
            <p class="mt-2 text-sm text-gray-600">Matières choisies :</p>
            <ul class="mt-1 flex flex-wrap gap-2">
                @foreach ($inscription->matieres as $matiere)
                    <li class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700 ring-1 ring-indigo-100">{{ $matiere }}</li>
                @endforeach
            </ul>
        @endif
        <p class="mt-4 text-sm text-gray-500">Pour toute modification, contactez le CFA.</p>
    </div>
@endsection
