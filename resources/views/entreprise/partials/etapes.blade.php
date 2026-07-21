{{--
    Fil d'étapes du parcours entreprise partenaire.

    @param int $etape  Étape en cours (1 = coordonnées, 2 = fiche besoin)
--}}
@php
    $etapes = [
        1 => 'Votre entreprise',
        2 => 'Votre besoin',
    ];
@endphp

<ol class="mb-8 flex items-center gap-3" aria-label="Progression du formulaire">
    @foreach ($etapes as $numero => $titre)
        @php
            $faite = $numero < $etape;
            $courante = $numero === $etape;
        @endphp

        <li class="flex flex-1 items-center gap-2.5" @if ($courante) aria-current="step" @endif>
            <span @class([
                'flex h-7 w-7 flex-none items-center justify-center rounded-full text-xs font-bold',
                'bg-emerald-500 text-white' => $faite,
                'bg-indigo-600 text-white' => $courante,
                'bg-slate-200 text-slate-500' => ! $faite && ! $courante,
            ])>
                @if ($faite)
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-3.5 w-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                    </svg>
                    <span class="sr-only">Étape terminée :</span>
                @else
                    {{ $numero }}
                @endif
            </span>

            <span @class([
                'text-sm',
                'font-semibold text-slate-900' => $courante,
                'text-slate-500' => ! $courante,
            ])>{{ $titre }}</span>

            @unless ($loop->last)
                <span class="ml-1 hidden h-px flex-1 bg-slate-200 sm:block" aria-hidden="true"></span>
            @endunless
        </li>
    @endforeach
</ol>
