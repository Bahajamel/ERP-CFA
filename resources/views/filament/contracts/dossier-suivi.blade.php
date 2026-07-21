@php
    use App\Enums\ContractSignatureStatut;

    /** @var \App\Models\Contract $contract */
    /** @var array{global:int, sections:array} $completion */
    /** @var array $etat */

    $couleurScore = fn (int $s) => $s >= 80 ? '#16a34a' : ($s >= 50 ? '#d97706' : '#dc2626');

    // Alertes : informations manquantes bloquantes pour le CERFA / la convention.
    $alertes = collect($completion['sections'])
        ->flatMap(fn ($s) => collect($s['manquants'])->map(fn ($m) => $s['label'] . ' — ' . $m))
        ->values();

    $sigStatut = $contract->statut_signature;
    $sigColor = match ($sigStatut) {
        ContractSignatureStatut::Signe => '#16a34a',
        ContractSignatureStatut::Envoye => '#d97706',
        default => 'var(--cfa-ink-soft)',
    };
    $signataires = $contract->signatureRequests()->latest('id')->first()?->signataires ?? null;
@endphp

<div style="display:grid;gap:16px">

    {{-- Avancée du dossier : progression par onglet --}}
    <div style="border:1px solid var(--cfa-card-border);border-radius:var(--cfa-radius-card,12px);background:var(--cfa-card-2);box-shadow:var(--cfa-shadow-card);padding:14px 16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <span style="font-weight:700;font-size:.9rem;color:var(--cfa-ink)">Avancée du dossier</span>
            <span style="font-size:.78rem;font-weight:700;color:{{ $couleurScore($completion['global']) }}">
                {{ $completion['global'] }}% complété
                <span style="font-weight:500;color:var(--cfa-ink-soft)">({{ $completion['global_remplis'] }}/{{ $completion['global_total'] }} champs)</span>
            </span>
        </div>
        <div style="display:grid;gap:10px">
            @foreach ($completion['sections'] as $s)
                @php $c = $couleurScore($s['score']); @endphp
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:.82rem;margin-bottom:3px">
                        <span style="color:var(--cfa-ink)">
                            {{ $s['score'] === 100 ? '✅' : '◔' }} {{ $s['label'] }}
                            <span style="color:var(--cfa-ink-soft);font-size:.75rem">— {{ $s['remplis'] }}/{{ $s['total'] }} champs</span>
                        </span>
                        <span style="color:{{ $c }};font-weight:600">{{ $s['score'] }}%</span>
                    </div>
                    <div style="height:6px;background:color-mix(in srgb, var(--cfa-ink-soft) 16%, transparent);border-radius:999px;overflow:hidden">
                        <div style="width:{{ $s['score'] }}%;height:100%;background:{{ $c }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Documents du dossier : réutilise le tour de contrôle documentaire existant --}}
    <div style="border:1px solid var(--cfa-card-border);border-radius:var(--cfa-radius-card,12px);background:var(--cfa-card-2);box-shadow:var(--cfa-shadow-card);padding:14px 16px">
        <div style="font-weight:700;font-size:.9rem;color:var(--cfa-ink);margin-bottom:10px">Documents du dossier</div>
        @include('filament.contracts.completude', ['etat' => $etat])
    </div>

    {{-- Signatures --}}
    <div style="border:1px solid var(--cfa-card-border);border-radius:var(--cfa-radius-card,12px);background:var(--cfa-card-2);box-shadow:var(--cfa-shadow-card);padding:14px 16px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <span style="font-weight:700;font-size:.9rem;color:var(--cfa-ink)">Signatures</span>
            <span style="font-size:.75rem;font-weight:600;color:{{ $sigColor }};background:color-mix(in srgb, {{ $sigColor }} 15%, transparent);padding:2px 10px;border-radius:999px">{{ $sigStatut?->getLabel() }}</span>
        </div>
        @if ($signataires)
            <div style="display:grid;gap:5px">
                @foreach ($signataires as $sig)
                    <div style="display:flex;justify-content:space-between;font-size:.82rem;color:var(--cfa-ink)">
                        <span>{{ $sig['libelle'] ?? $sig['nom'] ?? 'Signataire' }}</span>
                        <span style="color:var(--cfa-ink-soft)">{{ $sig['statut'] ?? 'À signer' }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div style="font-size:.82rem;color:var(--cfa-ink-soft)">
                Le circuit de signature démarre depuis le menu « Signature » en haut de la page, une fois les documents générés.
            </div>
        @endif
    </div>

    {{-- Alertes intelligentes --}}
    <div style="border:1px solid var(--cfa-card-border);border-radius:var(--cfa-radius-card,12px);background:var(--cfa-card-2);box-shadow:var(--cfa-shadow-card);padding:14px 16px">
        <div style="font-weight:700;font-size:.9rem;color:var(--cfa-ink);margin-bottom:8px">Alertes & informations manquantes</div>
        @if ($alertes->isEmpty())
            <div style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:#16a34a">
                <span>✓</span> Toutes les informations suivies sont renseignées.
            </div>
        @else
            <div style="display:grid;gap:6px">
                @foreach ($alertes as $a)
                    <div style="display:flex;align-items:flex-start;gap:8px;font-size:.82rem;color:var(--cfa-ink)">
                        <span style="color:#d97706;flex:none">⚠️</span> {{ $a }}
                    </div>
                @endforeach
            </div>
            <div style="margin-top:10px;font-size:.78rem;color:var(--cfa-ink-soft)">
                La génération du CERFA et de la convention reste possible, mais les documents seront incomplets tant que ces informations manquent.
            </div>
        @endif
    </div>

</div>
