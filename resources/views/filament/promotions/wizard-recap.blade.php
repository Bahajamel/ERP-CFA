@php
    use App\Enums\ModaliteSuivi;
    use App\Enums\TypeContrat;
    use App\Models\Formation;
    use App\Models\User;

    /** @var array<string, mixed> $d */

    $formationLibelle = filled($d['formation_id'] ?? null)
        ? (Formation::find($d['formation_id'])?->libelle ?? null) : null;
    $responsable = filled($d['responsable_id'] ?? null)
        ? (User::find($d['responsable_id'])?->name ?? null) : null;
    $typeContrat = TypeContrat::tryFrom($d['type_contrat'] ?? '')?->getLabel();
    $modalite = ModaliteSuivi::tryFrom($d['modalite_suivi'] ?? '')?->getLabel();
    $oui = fn (string $cle) => ($d[$cle] ?? false) ? 'Oui' : 'Non';
    $heures = fn ($v) => filled($v) ? $v . ' h' : null;
    $joinNn = fn (array $parts) => (($s = trim(implode(' ', array_filter($parts)))) !== '' ? $s : null);

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
@endphp

<div style="display:grid;gap:16px">

    {{-- Bandeau d'intro --}}
    <div style="display:flex;align-items:flex-start;gap:10px;font-size:.85rem;line-height:1.45;color:var(--cfa-ink-soft)">
        <span style="font-size:1.1rem;line-height:1;flex:none">🎓</span>
        <span>
            Vérifiez les informations ci-dessous. <b style="color:var(--cfa-ink)">La promotion n'est pas encore créée.</b>
            Pour corriger, cliquez sur l'étape correspondante en haut. La création se fait au bouton
            <b style="color:var(--cfa-ink)">« Créer la promotion »</b>.
        </span>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;align-items:start">

        {!! $carte('Promotion', 'var(--cfa-accent,#6366f1)', [
            'Nom' => $d['nom'] ?? null,
            'Formation' => $formationLibelle,
            'Niveau' => $d['libelle'] ?? null,
            'Année scolaire' => $d['annee_scolaire'] ?? null,
            'Responsable' => $responsable,
            'Adresse' => $joinNn([$d['lieu_formation_numero'] ?? null, $d['lieu_formation'] ?? null]),
            'Complément' => $d['lieu_formation_complement'] ?? null,
            'Code postal / Ville' => $joinNn([$d['lieu_formation_code_postal'] ?? null, $d['lieu_formation_ville'] ?? null]),
        ]) !!}

        {!! $carte('Configuration', 'var(--cfa-cyan,#06b6d4)', [
            'Type de contrat' => $typeContrat,
            'Modalité de suivi' => $modalite,
            'Durée totale' => $heures($d['duree_formation_heures'] ?? null),
            'E-learning' => $heures($d['heures_elearning'] ?? null),
            'Classe virtuelle' => $heures($d['heures_classe_virtuelle'] ?? null),
            'Reste à charge 0 €' => $oui('reste_a_charge_zero'),
            'Début' => $dateFr($d['date_debut'] ?? null),
            'Fin' => $dateFr($d['date_fin'] ?? null),
        ]) !!}

        {!! $carte('Frais annexes', 'var(--cfa-indigo,#4f46e5)', [
            'Hébergement' => $oui('frais_hebergement'),
            'Restauration' => $oui('frais_restauration'),
            'Équipement' => $oui('frais_equipement'),
            'Type d\'équipement' => $d['type_equipement'] ?? null,
            'Mobilité internationale' => $oui('frais_mobilite'),
        ]) !!}

    </div>
</div>
