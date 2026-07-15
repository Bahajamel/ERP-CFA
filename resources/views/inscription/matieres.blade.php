@extends('candidature.layout')

@section('title', 'Choisissez vos matières')
@section('heading', 'Finalisez votre inscription')
@section('subheading', 'Sélectionnez les matières que vous souhaitez suivre au sein de votre formation.')

@section('content')
    <div class="mb-6 rounded-xl bg-indigo-50 p-4 ring-1 ring-indigo-100">
        <p class="text-sm text-indigo-900">
            Bonjour <strong>{{ $inscription->candidate?->prenom }} {{ $inscription->candidate?->nom }}</strong>,
            vous êtes inscrit(e) dans la classe
            <strong>{{ $inscription->promotion?->nom_complet }}</strong>.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-700 ring-1 ring-rose-200">
            {{ $errors->first() }}
        </div>
    @endif

    @if (count($programme) === 0)
        <p class="text-sm text-gray-600">
            Le programme de votre formation n'est pas encore renseigné. Contactez le CFA.
        </p>
    @else
        <form method="POST" action="{{ route('inscription.matieres.store', $token) }}">
            @csrf

            <fieldset>
                <legend class="mb-3 text-sm font-semibold text-gray-800">Matières du programme</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($programme as $matiere)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 hover:bg-gray-50">
                            <input type="checkbox" name="matieres[]" value="{{ $matiere }}"
                                   @checked(in_array($matiere, old('matieres', []), true))
                                   class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm text-gray-800">{{ $matiere }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button type="submit"
                    class="mt-6 w-full rounded-xl bg-indigo-600 px-4 py-3 text-center text-sm font-semibold text-white hover:bg-indigo-700">
                Valider mon inscription
            </button>
        </form>
    @endif
@endsection
