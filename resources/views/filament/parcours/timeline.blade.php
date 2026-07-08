{{-- Timeline du cycle apprenant : Candidat → Matching → Contrat → OPCO → Admission → Rupture.
     Chaque étape porte un état : terminée / en cours / bloquée / non démarrée. --}}
@php
    $styles = [
        'terminee' => ['dot' => 'background:linear-gradient(90deg,#4f46e5,#06b6d4);color:#fff;', 'symbole' => '✓', 'texte' => ''],
        'en_cours' => ['dot' => 'background:#4f46e5;color:#fff;box-shadow:0 0 0 4px rgba(79,70,229,.25);', 'symbole' => '●', 'texte' => 'font-weight:600;'],
        'bloquee' => ['dot' => 'background:#e11d48;color:#fff;', 'symbole' => '✕', 'texte' => ''],
        'non_demarree' => ['dot' => 'background:rgba(128,138,168,.25);color:#808aa8;', 'symbole' => '○', 'texte' => 'opacity:.55;'],
    ];
@endphp

<div style="display:flex;flex-wrap:wrap;gap:.25rem;align-items:flex-start" class="fi-parcours-timeline">
    @foreach ($etapes as $i => $etape)
        @php $style = $styles[$etape['etat']] ?? $styles['non_demarree']; @endphp

        @if ($i > 0)
            <div style="flex:1 1 1.25rem;min-width:1rem;height:2px;margin-top:.8rem;border-radius:2px;
                background:{{ in_array($etapes[$i - 1]['etat'], ['terminee', 'en_cours'], true) ? 'linear-gradient(90deg,#4f46e5,#06b6d4)' : 'rgba(128,138,168,.25)' }};">
            </div>
        @endif

        <div style="display:flex;flex-direction:column;align-items:center;gap:.3rem;min-width:5.2rem;text-align:center">
            <span style="display:inline-flex;align-items:center;justify-content:center;width:1.65rem;height:1.65rem;
                border-radius:9999px;font-size:.8rem;font-weight:700;{{ $style['dot'] }}">
                {{ $style['symbole'] }}
            </span>
            <span style="font-size:.78rem;{{ $style['texte'] }}">{{ $etape['libelle'] }}</span>
            <span style="font-size:.68rem;opacity:.65">{{ $etape['detail'] }}</span>
        </div>
    @endforeach
</div>
