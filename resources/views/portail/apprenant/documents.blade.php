@extends('portail.apprenant.layout')

@section('title', 'Mes documents')

@section('content')
    <div class="mb-5">
        <h2 class="text-2xl font-bold text-slate-900">Mes documents</h2>
        <p class="mt-1 text-slate-500">Convention, contrat, bulletins et calendrier de votre formation.</p>
    </div>

    <div class="space-y-2.5">
        @forelse ($documents as $document)
            @php $media = $document->getFirstMedia('fichier'); @endphp
            <a href="{{ route('portail.apprenant.document', ['token' => $token, 'document' => $document->id]) }}"
               class="group flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-slate-900/10">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-indigo-100 text-indigo-600">
                    @include('portail.apprenant._icon', ['name' => 'document', 'class' => 'h-6 w-6'])
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-slate-800">{{ $document->type->getLabel() }}</p>
                    <p class="truncate text-xs text-slate-500">
                        {{ $media?->file_name }}
                        @if ($media?->created_at) · ajouté le {{ $media->created_at->format('d/m/Y') }} @endif
                    </p>
                </div>
                <span class="pa-accent inline-flex items-center gap-1 text-sm font-semibold transition group-hover:translate-x-0.5">
                    Télécharger
                    @include('portail.apprenant._icon', ['name' => 'arrow-right', 'class' => 'h-4 w-4'])
                </span>
            </a>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-900/5">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                    @include('portail.apprenant._icon', ['name' => 'folder', 'class' => 'h-7 w-7'])
                </span>
                <p class="mt-3 font-medium text-slate-700">Aucun document disponible pour le moment</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                    Vos pièces (convention, contrat, bulletins…) apparaîtront ici dès qu'elles seront ajoutées par le CFA.
                </p>
            </div>
        @endforelse
    </div>
@endsection