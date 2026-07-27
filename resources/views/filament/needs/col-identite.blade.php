@php $r = $getRecord(); @endphp
{{-- Pas de pastille d'initiales ici : l'entreprise est déjà nommée sous
     l'intitulé du poste, la pastille n'ajoutait aucune information. --}}
<div class="cfa-cand-identite">
    <span class="cfa-cand-identite-txt">
        <span class="cfa-cand-name">
            {{ $r->intitule_poste }}
            {{-- Déposée par l'entreprise et pas encore relue : ne compte dans
                 aucun indicateur de recrutement tant qu'elle n'est pas validée. --}}
            @if ($r->attendValidation())
                <span title="Besoin déposé par l'entreprise, en attente de votre validation"
                    style="display:inline-flex;align-items:center;gap:.2rem;margin-left:.35rem;padding:.05rem .4rem;
                           border-radius:9999px;background:#8b5cf61a;color:#7c3aed;
                           font-size:.68rem;font-weight:700;vertical-align:middle;">
                    À valider
                </span>
            @endif
        </span>
        @if ($r->company)
            <span class="cfa-cand-formation">{{ $r->company->raison_sociale }}</span>
        @endif
    </span>
</div>
