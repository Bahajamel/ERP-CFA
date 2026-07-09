@php
    /**
     * Convention de formation par apprentissage (Annexe n°2 du modèle officiel).
     * Reproduit fidèlement le document réglementaire, pré-rempli depuis le contrat.
     * $v : affiche une valeur ou des pointillés « à compléter » si elle manque.
     */
    $v = fn ($val) => filled($val)
        ? '<span class="val">'.e($val).'</span>'
        : '<span class="fill"></span>';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 90px 55px 70px 55px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #14142b; line-height: 1.5; }
        .doc-title { text-align: center; font-size: 15px; font-weight: bold; text-transform: uppercase; margin: 0 0 4px; letter-spacing: .3px; }
        .doc-sub { text-align: center; font-size: 9px; color: #6b7280; margin: 0 0 16px; }
        p { margin: 0 0 7px; text-align: justify; }
        .art { margin-top: 14px; }
        .art-h { font-weight: bold; font-size: 11.5px; color: #1d4ed8; border-bottom: 1px solid #dbe1f0; padding-bottom: 2px; margin: 0 0 5px; }
        .art-sub { font-weight: bold; font-style: italic; margin: 6px 0 2px; }
        .val { font-weight: bold; }
        .fill { display: inline-block; min-width: 90px; border-bottom: 1px dotted #9aa2b1; height: 10px; }
        .party { background: #f5f7fc; border: 1px solid #e2e7f3; border-radius: 5px; padding: 8px 10px; margin: 6px 0; }
        .party b { color: #1d4ed8; }
        .contact { margin: 2px 0 0 10px; font-size: 9.5px; color: #374151; }
        table.fin { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 8.6px; }
        table.fin th, table.fin td { border: 1px solid #c7cfdf; padding: 3px 4px; text-align: center; vertical-align: middle; }
        table.fin th { background: #eef2fb; font-weight: bold; }
        .muted { color: #6b7280; font-size: 8.6px; }
        .sign-wrap { width: 100%; margin-top: 26px; }
        .sign-wrap td { width: 50%; vertical-align: top; padding: 8px; border: 1px solid #d5dbe8; height: 90px; }
        .sign-h { font-weight: bold; margin-bottom: 4px; }
        .foot-note { font-size: 8px; color: #9aa2b1; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="doc-sub">Annexe n°2 — Modèle de convention de formation (art. L6353-1 &amp; L6211-1 du Code du travail)</div>
    <div class="doc-title">Convention de formation par apprentissage</div>

    <p>Entre les soussignés :</p>

    <div class="party">
        <p><b>Le centre de formation d'apprentis (« CFA »)</b> : {!! $v($d['cfa_designation']) !!},
            {!! $v($d['cfa_adresse']) !!}, SIRET {!! $v($d['cfa_siret']) !!}, UAI {!! $v($d['cfa_uai']) !!},
            organisme de formation enregistré sous le numéro de déclaration d'activité {!! $v($d['cfa_nda']) !!},
            représenté par {!! $v($d['cfa_representant']) !!}.</p>
        <p class="contact">Contact opérationnel : {!! $v($d['cfa_contact_prenom']) !!} {!! $v($d['cfa_contact_nom']) !!}
            — {!! $v($d['cfa_contact_email']) !!} — {!! $v($d['cfa_contact_tel']) !!}</p>
    </div>

    <div class="party">
        <p><b>L'entreprise</b> : {!! $v($d['entreprise_designation']) !!}, {!! $v($d['entreprise_adresse']) !!},
            SIRET {!! $v($d['entreprise_siret']) !!}, représentée par {!! $v($d['entreprise_representant']) !!},
            relevant de l'opérateur de compétences {!! $v($d['entreprise_opco']) !!}.</p>
        <p class="contact">Contact opérationnel : {!! $v($d['entreprise_contact_prenom']) !!} {!! $v($d['entreprise_contact_nom']) !!}
            — {!! $v($d['entreprise_contact_email']) !!} — {!! $v($d['entreprise_contact_tel']) !!}</p>
    </div>

    <p>est conclue la présente convention, en application des dispositions des Livres II et III de la sixième partie
        du Code du travail.</p>

    <div class="art">
        <div class="art-h">Article 1er — Objet de la convention</div>
        <p>Le CFA organise une action de formation par apprentissage au sens de l'article L. 6313-6 du Code du travail.</p>
        <p><span class="art-sub">Intitulé et objectif de l'action :</span> préparer à l'obtention du diplôme ou titre
            {!! $v($d['formation_intitule']) !!} (code RNCP {!! $v($d['formation_rncp']) !!}).</p>
        <p><span class="art-sub">Durée de l'action de formation :</span> du {!! $v($d['date_debut']) !!}
            au {!! $v($d['date_fin']) !!}.</p>
        <p><span class="art-sub">Lieu principal de la formation :</span> {!! $v($d['lieu_formation']) !!}
            (UAI {!! $v($d['cfa_uai']) !!}, SIRET {!! $v($d['cfa_siret']) !!}).</p>
        <p><span class="art-sub">Périodes de réalisation en entreprise et en CFA :</span>
            {!! $v($d['rythme']) !!} (calendrier de l'alternance transmis en annexe ou ultérieurement).</p>
    </div>

    <div class="art">
        <div class="art-h">Article 2 — Modalités de déroulement, de suivi et d'obtention du diplôme ou du titre</div>
        <p><span class="art-sub">Modalités de déroulement :</span> présentiel (mixte / à distance le cas échéant).</p>
        <p><span class="art-sub">Nombre d'heures total :</span> {!! $v($d['nb_heures_total']) !!}
            &nbsp;—&nbsp; dont à distance : <span class="fill"></span></p>
        <p><span class="art-sub">Modalités d'obtention du diplôme ou du titre :</span> examen terminal / contrôle continu
            selon le référentiel de la certification visée.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 3 — Bénéficiaire de l'action de formation en apprentissage</div>
        <p><span class="art-sub">Prénom(s) et nom :</span> {!! $v($d['apprenti_nom_complet']) !!}</p>
        <p><span class="art-sub">Date de naissance :</span> {!! $v($d['apprenti_naissance']) !!}
            &nbsp;—&nbsp; <span class="art-sub">Adresse :</span> {!! $v($d['apprenti_adresse']) !!}</p>
        <p><span class="art-sub">Dates de début et de fin du contrat :</span> du {!! $v($d['date_debut']) !!}
            au {!! $v($d['date_fin']) !!}.</p>
        <p><span class="art-sub">Maître d'apprentissage :</span> {!! $v($d['tuteur']) !!}</p>
    </div>

    <div class="art">
        <div class="art-h">Article 4 — Dispositions financières liées à la convention</div>
        <p>Conformément à l'article L. 6211-1 du Code du travail, la gratuité de la formation est garantie à l'apprenti
            et, le cas échéant, à son représentant légal. Aucune somme ne peut leur être demandée.</p>
        <table class="fin">
            <thead>
                <tr>
                    <th>Année de financement</th>
                    <th>Prix de la prestation, net de taxe [A]</th>
                    <th>Prise en charge OPCO [B]</th>
                    <th>Participation employeur (niv. 6 et supra) [C]</th>
                    <th>À payer par l'OPCO [D = B−C]</th>
                    <th>Total employeur [E = A−D]</th>
                </tr>
            </thead>
            <tbody>
                @for ($n = 1; $n <= $d['annees_financement']; $n++)
                    <tr>
                        <td>{{ $n }}<sup>{{ $n === 1 ? 're' : 'e' }}</sup> année</td>
                        <td>{!! $n === 1 && filled($d['cout_formation']) ? '<b>'.e($d['cout_formation']).'</b>' : '' !!}</td>
                        <td>{!! $n === 1 && filled($d['opco_montant']) ? '<b>'.e(number_format((float) $d['opco_montant'], 2, ',', ' ')).' €</b>' : '' !!}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endfor
            </tbody>
        </table>
        <p class="muted">La première année de financement correspond à la première année d'exécution du contrat
            d'apprentissage. Montant du niveau de prise en charge (NPEC) fixé par la branche / France compétences.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 5 — Frais annexes</div>
        <p>Les frais annexes (hébergement 6 €/nuit, restauration 3 €/repas, premier équipement pédagogique) concernent
            le temps en CFA. Lorsqu'ils sont financés par le CFA, l'OPCO en prend en charge une partie, dans les limites
            réglementaires. Montants et volumes à préciser en annexe selon la durée du contrat.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 6 — Modalités de règlement</div>
        <p>Les modalités de règlement d'un éventuel reste à charge de l'entreprise, ou de la participation obligatoire
            des employeurs pour les certifications de niveaux 6 et 7, sont précisées entre les parties.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 7 — Mandat</div>
        <p>Par la présente convention, l'entreprise signataire peut donner mandat au CFA signataire pour accomplir les
            formalités nécessaires aux opérations prévues à l'article L. 6224-1 du Code du travail. Le cas échéant, ce
            mandat est accompli à titre gratuit, le CFA mandataire ne recevant aucune rémunération à ce titre.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 8 — Clause résolutoire</div>
        <p>La présente convention peut être résolue selon les modalités négociées entre les parties, notamment en cas de
            refus de prise en charge par l'OPCO.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 9 — Fin de la convention</div>
        <p>La présente convention se termine : dès la fin d'exécution du contrat d'apprentissage, à l'échéance mentionnée
            dans le contrat ; en cas de refus de prise en charge par un OPCO, par effet de la clause résolutoire prévue à
            l'article 8 ; en cas de rupture anticipée du contrat, à la date d'effet de celle-ci.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 10 — Différends éventuels</div>
        <p>Si une contestation ou un différend ne peuvent être réglés à l'amiable, le Tribunal de
            {!! $v($d['tribunal']) !!} sera seul compétent pour régler le litige.</p>
    </div>

    <p style="margin-top:14px">Fait en double exemplaire, à {!! $v($d['fait_a']) !!} le {!! $v($d['fait_le']) !!}.</p>

    <table class="sign-wrap">
        <tr>
            <td>
                <div class="sign-h">Pour l'entreprise</div>
                <div class="muted">Nom et qualité du signataire · cachet de l'entreprise</div>
            </td>
            <td>
                <div class="sign-h">Pour l'organisme (CFA)</div>
                <div class="muted">Nom et qualité du signataire · cachet du CFA</div>
            </td>
        </tr>
    </table>

    <div class="foot-note">Document généré automatiquement par l'ERP CFA — à vérifier et signer par les parties.</div>
</body>
</html>
