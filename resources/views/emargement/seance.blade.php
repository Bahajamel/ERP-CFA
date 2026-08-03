@extends('candidature.layout')

@section('title', 'Signer ma présence')
@section('heading', 'Feuille d\'émargement')
@section('subheading', 'Choisissez votre nom pour signer votre présence.')

@section('content')
    <div class="mb-6 rounded-xl bg-indigo-50 p-4 ring-1 ring-indigo-100">
        <dl class="grid gap-x-4 gap-y-1 text-sm text-indigo-900 sm:grid-cols-2">
            <div><dt class="inline text-indigo-500">Formation :</dt> <dd class="inline font-semibold">{{ $seance->promotion?->formation?->libelle ?? '—' }}</dd></div>
            <div><dt class="inline text-indigo-500">Séance :</dt> <dd class="inline font-semibold">{{ $seance->libelle ?? '—' }}</dd></div>
            <div><dt class="inline text-indigo-500">Date :</dt> <dd class="inline font-semibold">{{ $seance->date?->format('d/m/Y') ?? '—' }}</dd></div>
            <div><dt class="inline text-indigo-500">Formateur :</dt> <dd class="inline font-semibold">{{ $seance->formateur?->name ?? '—' }}</dd></div>
        </dl>
        <p class="mt-3 text-xs font-medium text-indigo-700">{{ $signes }}/{{ $total }} apprenant(s) ont signé</p>
    </div>

    @if ($nonSignes->isEmpty())
        <div class="rounded-xl bg-emerald-50 p-5 text-center ring-1 ring-emerald-100">
            <p class="text-sm font-semibold text-emerald-900">Tout le monde a signé 🎉</p>
            <p class="mt-1 text-sm text-emerald-800">Il n'y a plus de présence à signer pour cette séance.</p>
        </div>
    @else
        <p class="mb-3 text-sm font-semibold text-gray-800">Trouvez votre nom :</p>
        <div class="space-y-2">
            @foreach ($nonSignes as $apprenant)
                <a href="{{ $apprenant['lien'] }}"
                   class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 p-4 hover:border-indigo-300 hover:bg-indigo-50">
                    <span class="text-sm font-medium text-gray-900">{{ $apprenant['nom'] }}</span>
                    <span class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600">
                        Signer
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                    </span>
                </a>
            @endforeach
        </div>
        <p class="mt-4 text-xs text-slate-400">
            Votre nom n'est pas dans la liste ? Vous avez peut-être déjà signé, ou vous n'êtes pas
            rattaché à cette séance — voyez avec votre formateur.
        </p>
    @endif
@endsection