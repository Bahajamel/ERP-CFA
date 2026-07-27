@php
    use App\Enums\DocumentType;

    /** @var \App\Models\Contract $contract */
    /** @var array{global:int, sections:array} $completion */

    $cand = $contract->candidate;
    $co = $contract->company;
    $contact = $co?->contactPrincipal()->first();
    $representant = $co?->representantsLegaux()->first();
    $cfa = \App\Models\Organisation::courante();

    $global = $completion['global'];
    $scoreColor = $global >= 80 ? '#16a34a' : ($global >= 50 ? '#d97706' : '#dc2626');

    $statutColor = match ($contract->statut_contrat?->getColor()) {
        'success' => '#16a34a', 'warning' => '#d97706', 'danger' => '#dc2626', 'info' => '#3b82f6', default => 'var(--cfa-ink-soft)',
    };

    $docs = $contract->documents()->get();
    $docPresent = fn (DocumentType $t) => $docs->firstWhere('type', $t) !== null;

    $ligne = function (string $label, ?string $valeur, ?string $couleur = null) {
        $v = filled($valeur) ? e($valeur) : '<span style="opacity:.5">—</span>';
        $style = $couleur ? "color:{$couleur};font-weight:600" : 'color:var(--cfa-ink)';
        return '<div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline;font-size:.82rem;padding:3px 0">'
            . '<span style="color:var(--cfa-ink-soft)">' . e($label) . '</span>'
            . '<span style="text-align:right;' . $style . ';overflow-wrap:anywhere">' . $v . '</span></div>';
    };

    $bloc = function (string $emoji, string $titre, ?string $sous, string $corps) {
        return '<div style="border:1px solid var(--cfa-card-border);border-radius:var(--cfa-radius-card,12px);background:var(--cfa-card-2);box-shadow:var(--cfa-shadow-card);padding:12px 14px">'
            . '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">'
            . '<span style="font-size:1rem;flex:none">' . $emoji . '</span>'
            . '<div style="line-height:1.15"><div style="font-weight:700;font-size:.85rem;color:var(--cfa-ink)">' . e($titre) . '</div>'
            . ($sous ? '<div style="font-size:.72rem;color:var(--cfa-ink-soft)">' . e($sous) . '</div>' : '') . '</div></div>'
            . $corps . '</div>';
    };
@endphp

<div style="display:grid;gap:12px">

    {{-- Complétude globale --}}
    <div style="border:1px solid var(--cfa-card-border);border-radius:var(--cfa-radius-card,12px);background:var(--cfa-card);box-shadow:var(--cfa-shadow-card);padding:12px 14px">
        <div style="display:flex;align-items:center;gap:12px">
            <div style="font-size:1.5rem;font-weight:800;color:{{ $scoreColor }}">{{ $global }}%</div>
            <div style="flex:1">
                <div style="font-size:.78rem;color:var(--cfa-ink-soft);margin-bottom:4px">Complétude du dossier</div>
                <div style="height:8px;background:color-mix(in srgb, var(--cfa-ink-soft) 18%, transparent);border-radius:999px;overflow:hidden">
                    <div style="width:{{ $global }}%;height:100%;background:{{ $scoreColor }}"></div>
                </div>
            </div>
        </div>
    </div>

    {!! $bloc('🏫', $cfa->designation() ?? 'CFA', 'Centre de formation',
        $ligne('ID dossier', '#'.\Illuminate\Support\Str::padLeft((string) $contract->id, 5, '0'))
        . $ligne('Type de contrat', $contract->type_contrat?->getLabel())
        . $ligne('Statut', $contract->statut_contrat?->getLabel(), $statutColor)
        . $ligne('SIRET CFA', $cfa->siret)
    ) !!}

    {!! $bloc('💼', $co?->raison_sociale ?? 'Entreprise', $co?->ville,
        $ligne('SIRET', $co?->siret)
        . $ligne('Contact', $contact?->nom_complet)
        . $ligne('Signataire', $representant?->nom_complet)
        . $ligne('Tuteur', $contract->tuteur?->nom_complet)
    ) !!}

    {!! $bloc('🎓', $cand?->nom_complet ?? 'Apprenant', $cand?->formationVisee?->libelle,
        $ligne('Email', $cand?->email)
        . $ligne('Téléphone', $cand?->telephone)
        . $ligne('Formation', $contract->formation?->libelle)
    ) !!}

    {{-- Documents disponibles --}}
    <div style="border:1px solid var(--cfa-card-border);border-radius:var(--cfa-radius-card,12px);background:var(--cfa-card-2);box-shadow:var(--cfa-shadow-card);padding:12px 14px">
        <div style="font-weight:700;font-size:.85rem;color:var(--cfa-ink);margin-bottom:8px">Documents disponibles</div>
        @php
            $dispo = collect([
                ['CERFA', $docPresent(DocumentType::Cerfa)],
                ['Convention de formation', $docPresent(DocumentType::Convention)],
                ['Contrat signé', $docPresent(DocumentType::Contrat)],
            ])->filter(fn ($d) => $d[1]);
        @endphp
        @if ($dispo->isEmpty())
            <div style="font-size:.8rem;color:var(--cfa-ink-soft);text-align:center;padding:10px 0">Aucun document généré pour l'instant.</div>
        @else
            <div style="display:grid;gap:6px">
                @foreach ($dispo as $d)
                    <div style="display:flex;align-items:center;gap:8px;font-size:.82rem;color:var(--cfa-ink)">
                        <span style="color:#16a34a">✓</span> {{ $d[0] }}
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>
