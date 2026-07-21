@php
    /** @var \App\Models\Contract $contract */

    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : null;

    $jalons = collect([
        ['Début du contrat', $fmt($contract->date_debut), '#3b82f6'],
        ['Signature', $fmt($contract->date_signature), '#8b5cf6'],
        ['Fin du contrat', $fmt($contract->date_fin), '#16a34a'],
    ]);

    $duree = null;
    if ($contract->date_debut && $contract->date_fin) {
        $mois = \Illuminate\Support\Carbon::parse($contract->date_debut)->diffInMonths(\Illuminate\Support\Carbon::parse($contract->date_fin));
        $duree = $mois > 0 ? $mois . ' mois' : null;
    }
@endphp

<div style="display:grid;gap:12px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
        @foreach ($jalons as [$label, $valeur, $couleur])
            <div style="border:1px solid var(--cfa-card-border);border-radius:10px;background:var(--cfa-card-2);padding:10px 12px">
                <div style="display:flex;align-items:center;gap:6px;font-size:.72rem;color:var(--cfa-ink-soft);text-transform:uppercase;letter-spacing:.04em">
                    <span style="width:7px;height:7px;border-radius:2px;background:{{ $couleur }}"></span>{{ $label }}
                </div>
                <div style="font-size:.95rem;font-weight:600;color:var(--cfa-ink);margin-top:4px">{{ $valeur ?? '—' }}</div>
            </div>
        @endforeach
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:.82rem;color:var(--cfa-ink-soft)">
        <span>Durée : <b style="color:var(--cfa-ink)">{{ $duree ?? '—' }}</b></span>
    </div>

    <div style="font-size:.78rem;color:var(--cfa-ink-soft);border-left:3px solid color-mix(in srgb, var(--cfa-accent,#6366f1) 40%, transparent);padding:6px 12px;background:color-mix(in srgb, var(--cfa-accent,#6366f1) 8%, transparent);border-radius:4px">
        Structure évolutive : le planning détaillé des périodes en CFA / en entreprise et les échéances viendront enrichir cet onglet.
    </div>
</div>
