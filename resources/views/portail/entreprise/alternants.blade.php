@extends('portail.entreprise.layout')

@section('title', 'Mes alternants')

@section('content')
    <div class="mb-5">
        <h2 class="text-2xl font-bold text-slate-900">Mes alternants</h2>
        <p class="mt-1 text-slate-500">{{ $alternants->count() }} alternant(s) · assiduité et suivi de contrat.</p>
    </div>

    <div class="space-y-2.5">
        @forelse ($alternants as $row)
            @include('portail.entreprise._alternant', ['row' => $row, 'detaille' => true])
        @empty
            <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-900/5">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                    @include('portail.apprenant._icon', ['name' => 'users', 'class' => 'h-7 w-7'])
                </span>
                <p class="mt-3 font-medium text-slate-700">Aucun alternant enregistré</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Vos alternants apparaîtront ici dès qu'un contrat sera enregistré par le CFA.</p>
            </div>
        @endforelse
    </div>
@endsection