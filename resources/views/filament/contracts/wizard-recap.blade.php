@php
    use App\Enums\ModaliteSuivi;
    use App\Enums\TypeContrat;
    use App\Models\Formation;
    use App\Models\Promotion;

    /** @var array<string, mixed> $d */
    /** @var array<int, array{label:string, ok:bool, hint:?string}> $checklist */

    $formationLibelle = filled($d['formation_id'] ?? null)
        ? (Formation::find($d['formation_id'])?->libelle ?? null) : null;
    $promotionLibelle = filled($d['promotion_id'] ?? null)
        ? (Promotion::find($d['promotion_id'])?->nom_complet ?? null) : null;

    $typeContrat = TypeContrat::tryFrom($d['type_contrat'] ?? '')?->getLabel();
    $modalite = ModaliteSuivi::tryFrom($d['modalite_suivi'] ?? '')?->getLabel();
    $resteACharge = ($d['reste_a_charge_zero'] ?? false) ? 'Oui' : 'Non';

    $dateFr = function (?string $date): ?string {
        if (blank($date)) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    };

    $anneesCycle = ['1' => '1ère année', '2' => '2ème année', '3' => '3ème année', '4' => '4ème année'];
    $dureesDiplome = ['jusqu_1_an' => "Jusqu'à 1 an", 'jusqu_2_ans' => "Jusqu'à 2 ans", 'jusqu_3_ans' => "Jusqu'à 3 ans", 'jusqu_4_ans' => "Jusqu'à 4 ans"];

    $heures = fn ($v) => filled($v) ? $v . ' h' : null;
    $joinNn = fn (array $parts) => (($s = trim(implode(' ', array_filter($parts)))) !== '' ? $s : null);

    // Carte : en-tête (pastille colorée + titre) puis liste définitions. Une valeur
    // vide s'affiche en gris atténué (« — »), jamais en trou vide.
    $carte = function (string $titre, string $couleur, array $lignes) {
        ob_start(); ?>
        <div style="border:1px solid var(--cfa-card-border);border-radius:var(--cfa-radius-card,12px);background:var(--cfa-card-2);box-shadow:var(--cfa-shadow-card);overflow:hidden">
            <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--cfa-line)">
                <span style="width:8px;height:8px;border-radius:3px;background:<?= $couleur ?>;flex:none"></span>
                <span style="font-size:.72rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--cfa-ink-soft)"><?= e($titre) ?></span>
            </div>
            <dl style="display:grid;grid-template-columns:max-content 1fr;gap:7px 16px;margin:0;padding:12px 14px;font-size:.84rem;line-height:1.35">
                <?php foreach ($lignes as $label => $valeur): ?>
                    <dt style="color:var(--cfa-ink-soft);white-space:nowrap"><?= e($label) ?></dt>
                    <?php if (filled($valeur)): ?>
                        <dd style="margin:0;color:var(--cfa-ink);font-weight:500;overflow-wrap:anywhere"><?= e($valeur) ?></dd>
                    <?php else: ?>
                        <dd style="margin:0;color:var(--cfa-muted,var(--cfa-ink-soft));opacity:.6">—</dd>
                    <?php endif; ?>
                <?php endforeach; ?>
            </dl>
        </div>
        <?php return ob_get_clean();
    };

    $manquants = collect($checklist)->where('ok', false);
@endphp

<div style="display:grid;gap:16px">

    {{-- Bandeau d'intro --}}
    <div style="display:flex;align-items:flex-start;gap:10px;font-size:.85rem;line-height:1.45;color:var(--cfa-ink-soft)">
        <span style="font-size:1.1rem;line-height:1;flex:none">📋</span>
        <span>
            Vérifiez les informations ci-dessous. <b style="color:var(--cfa-ink)">Le dossier n'est pas encore créé.</b>
            Pour corriger, cliquez sur l'étape correspondante en haut de l'assistant. La création se fait au bouton
            <b style="color:var(--cfa-ink)">« Créer le dossier »</b>.
        </span>
    </div>

    {{-- Contrôle de complétude --}}
    <div style="border:1px solid var(--cfa-card-border);border-radius:var(--cfa-radius-card,12px);background:var(--cfa-card);box-shadow:var(--cfa-shadow-card);padding:14px 16px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px">
            <span style="font-size:.8rem;font-weight:700;color:var(--cfa-ink)">Contrôle de complétude</span>
            @php $ok = collect($checklist)->where('ok', true)->count(); @endphp
            <span style="font-size:.72rem;font-weight:600;color:var(--cfa-ink-soft)">{{ $ok }}/{{ count($checklist) }}</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:8px 18px">
            @foreach ($checklist as $item)
                @php
                    $c = $item['ok'] ? '#16a34a' : '#d97706';
                    $bg = $item['ok'] ? 'color-mix(in srgb, #16a34a 15%, transparent)' : 'color-mix(in srgb, #d97706 16%, transparent)';
                @endphp
                <div style="display:flex;align-items:flex-start;gap:9px;font-size:.83rem;line-height:1.3">
                    <span style="flex:none;width:18px;height:18px;border-radius:50%;background:{{ $bg }};color:{{ $c }};display:inline-flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;margin-top:1px">{{ $item['ok'] ? '✓' : '!' }}</span>
                    <span style="color:var(--cfa-ink)">
                        {{ $item['label'] }}
                        @if (! $item['ok'] && $item['hint'])
                            <span style="display:block;color:var(--cfa-ink-soft);font-size:.78rem">{{ $item['hint'] }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Deux colonnes équilibrées : à gauche l'apprenant + la formation, à droite
         l'entreprise et ses interlocuteurs. Repli en une colonne si étroit. --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start">

        <div style="display:grid;gap:16px;align-content:start">
            {!! $carte('Étudiant', 'var(--cfa-accent,#6366f1)', [
                'Nom complet' => $joinNn([$d['etudiant_prenom'] ?? null, $d['etudiant_nom'] ?? null]),
                'Email' => $d['etudiant_email'] ?? null,
                'Formation' => $formationLibelle,
                'Promotion' => $promotionLibelle,
                'Type de dossier' => ucfirst($d['mode_dossier'] ?? 'autonome'),
            ]) !!}

            {!! $carte('Formation', 'var(--cfa-cyan,#06b6d4)', [
                'Type de contrat' => $typeContrat,
                'Durée totale' => $heures($d['duree_formation_heures'] ?? null),
                'Modalité de suivi' => $modalite,
                'E-learning' => $heures($d['heures_elearning'] ?? null),
                'Classe virtuelle' => $heures($d['heures_classe_virtuelle'] ?? null),
                'Organismes' => $d['nombre_organismes_formation'] ?? null,
                'Prix total' => filled($d['cout_formation'] ?? null) ? $d['cout_formation'] . ' €' : null,
                'Reste à charge 0 €' => $resteACharge,
                'Durée diplôme' => $dureesDiplome[$d['duree_diplome'] ?? ''] ?? null,
                'Année de cycle' => $anneesCycle[$d['annee_cycle'] ?? ''] ?? null,
                'Début' => $dateFr($d['date_debut'] ?? null),
                'Fin' => $dateFr($d['date_fin'] ?? null),
            ]) !!}
        </div>

        <div style="display:grid;gap:16px;align-content:start">
            {!! $carte('Entreprise', 'var(--cfa-indigo,#4f46e5)', [
                'Nom légal' => $d['entreprise_raison_sociale'] ?? null,
                'Nom commercial' => $d['entreprise_nom_commercial'] ?? null,
                'Forme juridique' => $d['entreprise_forme_juridique'] ?? null,
                'SIRET' => $d['entreprise_siret'] ?? null,
                'SIREN' => $d['entreprise_siren'] ?? null,
                'SIRET établissement' => $d['entreprise_siret_etablissement'] ?? null,
                'Ville RCS' => $d['entreprise_ville_rcs'] ?? null,
                'Adresse' => $joinNn([$d['entreprise_numero_siege'] ?? null, $d['entreprise_adresse'] ?? null]),
                'Complément' => $d['entreprise_complement_adresse'] ?? null,
                'Code postal / Ville' => $joinNn([$d['entreprise_code_postal'] ?? null, $d['entreprise_ville'] ?? null]),
            ]) !!}

            {!! $carte('Contact entreprise', 'var(--cfa-blue,#3b82f6)', [
                'Nom' => $joinNn([$d['contact_prenom'] ?? null, $d['contact_nom'] ?? null]),
                'Email' => $d['contact_email'] ?? null,
            ]) !!}

            {!! $carte('Représentant légal', 'var(--cfa-accent-2,#8b5cf6)', [
                'Nom' => $joinNn([$d['representant_prenom'] ?? null, $d['representant_nom'] ?? null]),
                'Email' => $d['representant_email'] ?? null,
                'Poste' => $d['representant_poste'] ?? null,
            ]) !!}
        </div>

    </div>

    @if ($manquants->isNotEmpty())
        <div style="display:flex;align-items:flex-start;gap:9px;font-size:.83rem;line-height:1.4;color:var(--cfa-ink);background:color-mix(in srgb, #d97706 12%, transparent);border:1px solid color-mix(in srgb, #d97706 32%, transparent);border-radius:10px;padding:10px 14px">
            <span style="flex:none">⚠️</span>
            <span>{{ $manquants->count() }} point(s) à vérifier ci-dessus. Vous pouvez tout de même créer le dossier :
                les informations optionnelles se complètent ensuite sur sa fiche.</span>
        </div>
    @endif

</div>
