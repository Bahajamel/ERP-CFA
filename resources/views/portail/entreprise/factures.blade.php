@extends('portail.entreprise.layout')

@section('title', 'Factures')

@section('content')
    @php
        $badge = function ($facture) {
            if ($facture->estEnRetard()) {
                return ['En retard', 'bg-rose-100 text-rose-700'];
            }

            return match ($facture->statut->value) {
                'payee' => ['Payée', 'bg-emerald-100 text-emerald-700'],
                'emise' => ['Émise', 'bg-sky-100 text-sky-700'],
                'annulee' => ['Annulée', 'bg-slate-100 text-slate-500'],
                default => ['Brouillon', 'bg-slate-100 text-slate-600'],
            };
        };
    @endphp

    <div class="mb-5">
        <h2 class="text-2xl font-bold text-slate-900">Factures</h2>
        <p class="mt-1 text-slate-500">{{ $factures->count() }} facture(s) liée(s) à vos alternances.</p>
    </div>

    <div class="space-y-2.5">
        @forelse ($factures as $facture)
            @php
                [$libelle, $classes] = $badge($facture);
                $piece = $facture->documents->first(fn ($d) => $d->getFirstMedia('fichier') !== null);
                $alternant = $facture->financeLine?->contract?->candidate?->nom_complet;
            @endphp
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-slate-800">
                            {{ $facture->numero ?: 'Facture #'.$facture->id }}
                            <span class="ml-1.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $classes }}">{{ $libelle }}</span>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            @if ($alternant) {{ $alternant }} · @endif
                            @if ($facture->date_emission) émise le {{ $facture->date_emission->format('d/m/Y') }} @endif
                            @if ($facture->date_echeance) · échéance {{ $facture->date_echeance->format('d/m/Y') }} @endif
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-slate-800">{{ number_format((float) $facture->montant, 2, ',', ' ') }} €</p>
                        @if ($facture->resteAPayer() > 0 && $facture->statut->value !== 'annulee')
                            <p class="text-xs text-slate-500">reste {{ number_format($facture->resteAPayer(), 2, ',', ' ') }} €</p>
                        @endif
                    </div>
                </div>
                @if ($piece)
                    <a href="{{ route('portail.entreprise.document', ['token' => $token, 'document' => $piece->id]) }}"
                       class="pa-accent mt-2 inline-flex items-center gap-1 text-sm font-semibold">
                        Télécharger la facture
                        @include('portail.apprenant._icon', ['name' => 'arrow-right', 'class' => 'h-4 w-4'])
                    </a>
                @endif
            </div>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-900/5">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                    @include('portail.apprenant._icon', ['name' => 'banknotes', 'class' => 'h-7 w-7'])
                </span>
                <p class="mt-3 font-medium text-slate-700">Aucune facture pour le moment</p>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Les factures liées à vos alternances apparaîtront ici.</p>
            </div>
        @endforelse
    </div>
@endsection