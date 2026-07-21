{{--
    Avatar illustré d'un assistant FAQ, dessiné en SVG dans le projet : aucun
    fichier à héberger, aucune requête réseau, net à toutes les tailles et
    lisible en thème clair comme sombre.

    Un personnage distinct par assistant (coupe de cheveux, lunettes, barbe,
    chignon) pour qu'on le reconnaisse d'un coup d'œil, les épaules reprenant
    la couleur de sa section.

    Si le CFA téléverse un avatar depuis l'administration, c'est lui qui prime :
    cet SVG n'est que la valeur par défaut.

    @param \App\Models\FaqBot $bot
--}}
@php
    /** Teintes de peau, pour que les cinq personnages ne se ressemblent pas. */
    $peau = match ($bot->module) {
        'commercial' => '#F1C9A5',
        'contrats' => '#E8B98C',
        'finance' => '#D2996B',
        'scolarite' => '#F5D2B0',
        default => '#C98D5F',
    };
    $cheveux = match ($bot->module) {
        'commercial' => '#3B2F2A',
        'contrats' => '#4A3B32',
        'finance' => '#2F2A26',
        'scolarite' => '#6B4226',
        default => '#241E1A',
    };
@endphp

<svg viewBox="0 0 64 64" role="img" aria-label="{{ $bot->name }}"
     style="width:100%;height:100%;display:block;">
    {{-- Fond clair : détache le personnage aussi bien sur la couleur vive
         du bouton que sur le fond sombre du thème nuit. --}}
    <circle cx="32" cy="32" r="32" fill="#F8FAFC"/>

    {{-- Buste, à la couleur de l'assistant --}}
    <path d="M8 64c0-13 10-21 24-21s24 8 24 21z" fill="{{ $bot->color }}"/>
    {{-- Col de chemise --}}
    <path d="M26 44l6 7 6-7 -3-2h-6z" fill="#FFFFFF" opacity=".9"/>

    {{-- Cou et tête --}}
    <path d="M27 34h10v9a5 5 0 0 1-10 0z" fill="{{ $peau }}"/>
    <circle cx="32" cy="26" r="13" fill="{{ $peau }}"/>

    @switch($bot->module)
        {{-- Commercial : coupe courte --}}
        @case('commercial')
            <path d="M19 25c0-8 6-12 13-12s13 4 13 12c-1-5-6-7-13-7s-12 2-13 7z" fill="{{ $cheveux }}"/>
            @break

        {{-- Contrats & OPCO : coupe courte + lunettes --}}
        @case('contrats')
            <path d="M19 25c0-8 6-12 13-12s13 4 13 12c-1-5-6-7-13-7s-12 2-13 7z" fill="{{ $cheveux }}"/>
            <g fill="none" stroke="#1F2937" stroke-width="1.5" opacity=".85">
                <circle cx="26.5" cy="26" r="4.2"/>
                <circle cx="37.5" cy="26" r="4.2"/>
                <path d="M30.7 26h2.6" stroke-linecap="round"/>
            </g>
            @break

        {{-- Finance : coupe courte + barbe --}}
        @case('finance')
            <path d="M19 25c0-8 6-12 13-12s13 4 13 12c-1-5-6-7-13-7s-12 2-13 7z" fill="{{ $cheveux }}"/>
            <path d="M20 27c0 9 5 14 12 14s12-5 12-14c0 6-5 8-12 8s-12-2-12-8z" fill="{{ $cheveux }}" opacity=".95"/>
            @break

        {{-- Scolarité : cheveux longs --}}
        @case('scolarite')
            <path d="M18 27c0-9 6-14 14-14s14 5 14 14v12c-2 0-3.5-2-3.5-6V25c-3 3-18 3-21 0v14c0 4-1.5 6-3.5 6z" fill="{{ $cheveux }}"/>
            @break

        {{-- Pilotage : chignon --}}
        @default
            <circle cx="32" cy="10" r="5" fill="{{ $cheveux }}"/>
            <path d="M19 26c0-9 6-13 13-13s13 4 13 13c-1-6-6-8-13-8s-12 2-13 8z" fill="{{ $cheveux }}"/>
    @endswitch

    {{-- Visage : deux yeux et un sourire, volontairement minimalistes --}}
    <circle cx="27.5" cy="26.5" r="1.5" fill="#1F2937"/>
    <circle cx="36.5" cy="26.5" r="1.5" fill="#1F2937"/>
    <path d="M28.5 32.2c1.8 1.8 5.2 1.8 7 0" fill="none" stroke="#1F2937"
          stroke-width="1.5" stroke-linecap="round" opacity=".8"/>
</svg>
