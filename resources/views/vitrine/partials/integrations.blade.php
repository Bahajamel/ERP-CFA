{{-- Intégrations, classées par état RÉEL. Rien n'est présenté comme
     fonctionnel s'il ne l'est pas. « Selon votre configuration » car certaines
     dépendent d'un paramétrage propre au CFA. --}}
@php
    $colonnes = [
        ['Disponibles', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', [
            'Annuaire des Entreprises (recherche par SIRET)',
            'France Compétences (détection de l\'OPCO)',
            'La Bonne Alternance (prospection)',
            'Base Adresse Nationale (adresses)',
            'Signature électronique des contrats',
        ]],
        ['En cours', 'bg-amber-50 text-amber-700 ring-amber-600/20', [
            'Envoi d\'e-mails transactionnels',
            'API pour échanges de données',
        ]],
        ['Prévues', 'bg-slate-100 text-slate-600 ring-slate-500/20', [
            'Logiciels comptables',
            'Stockage cloud',
            'Connecteur Monday.com',
        ]],
    ];
@endphp

<section class="bg-white">
    <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Intégrations</p>
            <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Connectée à votre écosystème</h2>
            <p class="mt-4 text-lg text-slate-600">Meridian CFA s'appuie sur des services officiels et s'ouvre progressivement à de nouveaux connecteurs.</p>
        </div>

        <div class="mt-14 grid gap-6 lg:grid-cols-3" data-reveal>
            @foreach ($colonnes as [$etat, $badge, $items])
                <div class="rounded-2xl border border-slate-100 bg-white p-6">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $badge }}">{{ $etat }}</span>
                    <ul class="mt-5 space-y-3">
                        @foreach ($items as $item)
                            <li class="flex items-start gap-2.5 text-sm text-slate-600">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-slate-300"></span>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <p class="mt-8 text-center text-xs text-slate-400" data-reveal>Certaines intégrations dépendent de la configuration propre à chaque CFA.</p>
    </div>
</section>