@php
    /**
     * Convention de formation par apprentissage — structure reprise du modèle
     * Filiz (10 articles), pré-remplie depuis le contrat de l'ERP.
     * $v : valeur ou pointillés « à compléter » si absente.
     */
    $v = fn ($val) => filled($val)
        ? '<span class="val">'.e($val).'</span>'
        : '<span class="fill"></span>';
    $box = fn ($on) => $on ? '☒' : '☐';
    $oui = fn ($val) => $val === true || $val === 1;
    $non = fn ($val) => $val === false || $val === 0;
    $eur = fn ($m) => ($m === null || $m === '')
        ? '—'
        : number_format((float) $m, 2, ',', ' ').' €';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 70px 55px 60px 55px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #14142b; line-height: 1.45; }
        .doc-title { text-align: center; font-size: 15px; font-weight: bold; text-transform: uppercase; margin: 0 0 8px; letter-spacing: .3px; }
        p { margin: 0 0 6px; text-align: justify; }
        .intro { font-size: 8.6px; color: #4b5563; font-style: italic; margin-bottom: 10px; }
        .art { margin-top: 12px; }
        .art-h { font-weight: bold; font-size: 11px; color: #1d4ed8; border-bottom: 1px solid #dbe1f0; padding-bottom: 2px; margin: 0 0 5px; }
        .val { font-weight: bold; }
        .fill { display: inline-block; min-width: 80px; border-bottom: 1px dotted #9aa2b1; height: 10px; }
        .party { background: #f5f7fc; border: 1px solid #e2e7f3; border-radius: 5px; padding: 8px 10px; margin: 6px 0; }
        .party b { color: #1d4ed8; }
        .line { margin: 1px 0; }
        .lbl { color: #4b5563; }
        table.fin { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 9px; }
        table.fin th, table.fin td { border: 1px solid #c7cfdf; padding: 4px 5px; text-align: center; vertical-align: middle; }
        table.fin th { background: #eef2fb; font-weight: bold; }
        .muted { color: #6b7280; font-size: 8.4px; }
        .frais { margin: 3px 0; }
        .frais .box { font-size: 12px; }
        .opt { margin: 4px 0; padding-left: 14px; text-indent: -14px; }
        .sign-wrap { width: 100%; margin-top: 22px; border-collapse: collapse; }
        .sign-wrap td { width: 50%; vertical-align: top; padding: 10px; border: 1px solid #d5dbe8; height: 95px; }
        .sign-h { font-weight: bold; margin-bottom: 4px; }
        .sign-qui { font-size: 9.5px; margin-bottom: 2px; }
        .sign-img img { max-height: 48px; max-width: 120px; vertical-align: bottom; }
        .sign-img img + img { margin-left: 8px; }
        .foot-note { font-size: 8px; color: #9aa2b1; margin-top: 6px; }
    </style>
</head>
<body>
    <div class="doc-title">Convention de formation par apprentissage</div>

    <p class="intro">Au plus tard dans les cinq jours ouvrables qui suivent le début de l'exécution du contrat
        d'apprentissage, l'employeur transmet le contrat, accompagné de la convention mentionnée à l'article L.6353-1
        et, le cas échéant, de la convention tripartite prévue au troisième alinéa de l'article L.6222-7-1, à
        l'opérateur de compétences (art. D. 6224-1 du Code du travail).</p>

    <p>Entre les soussignés :</p>

    <div class="party">
        <p><b>Le Centre de Formation d'Apprentis</b></p>
        <div class="line"><span class="lbl">Dénomination sociale :</span> {!! $v($d['cfa_designation']) !!}</div>
        <div class="line"><span class="lbl">Adresse :</span> {!! $v($d['cfa_adresse']) !!}</div>
        <div class="line"><span class="lbl">SIRET :</span> {!! $v($d['cfa_siret']) !!}
            &nbsp;·&nbsp; <span class="lbl">Code UAI :</span> {!! $v($d['cfa_uai']) !!}</div>
        <div class="line"><span class="lbl">Enregistré sous le numéro de déclaration d'activité</span> {!! $v($d['cfa_nda']) !!}</div>
        <div class="line muted">Cet enregistrement ne vaut pas agrément de l'État (article L.6352-12 du Code du travail).</div>
        <div class="line"><span class="lbl">Représenté par</span> {!! $v($d['cfa_representant']) !!}
            <span class="lbl">en sa qualité de</span> {!! $v($d['cfa_representant_qualite']) !!}</div>
    </div>

    <div class="party">
        <p><b>L'entreprise</b></p>
        <div class="line"><span class="lbl">Dénomination sociale :</span> {!! $v($d['entreprise_designation']) !!}</div>
        <div class="line"><span class="lbl">Adresse :</span> {!! $v($d['entreprise_adresse']) !!}</div>
        <div class="line"><span class="lbl">SIRET :</span> {!! $v($d['entreprise_siret']) !!}</div>
        <div class="line"><span class="lbl">Adhérent de l'opérateur de compétences (OPCO) :</span> {!! $v($d['entreprise_opco']) !!}</div>
        <div class="line"><span class="lbl">Code IDCC :</span> {!! $v($d['entreprise_idcc']) !!}
            &nbsp;·&nbsp; <span class="lbl">Convention collective :</span> {!! $v($d['entreprise_convention_collective']) !!}</div>
        <div class="line"><span class="lbl">Représenté par</span> {!! $v($d['entreprise_representant']) !!}
            <span class="lbl">en sa qualité de</span> {!! $v($d['entreprise_representant_qualite']) !!}</div>
        <div class="line" style="margin-top:4px"><b>Correspondant administratif</b> —
            {!! $v(trim(($d['corr_prenom'] ?? '').' '.($d['corr_nom'] ?? ''))) !!}
            &nbsp;·&nbsp; {!! $v($d['corr_tel']) !!} &nbsp;·&nbsp; {!! $v($d['corr_mail']) !!}</div>
    </div>

    <p>Il est conclu la convention suivante, en application des dispositions des Livres II et III de la sixième partie
        du Code du travail.</p>

    <div class="art">
        <div class="art-h">Article 1<sup>er</sup> — Objet de la convention</div>
        <p>En exécution de la présente convention, le CFA organise une action de formation par apprentissage au sens de
            l'article L. 6313-6 du Code du travail, dans les conditions fixées par les articles suivants.</p>
        <div class="line"><span class="lbl">Intitulé et objectif de l'action :</span> préparer à l'obtention de
            {!! $v($d['formation_intitule']) !!}</div>
        <div class="line"><span class="lbl">RNCP :</span> {!! $v($d['formation_rncp']) !!}
            &nbsp;·&nbsp; <span class="lbl">Niveau :</span> {!! $v($d['formation_niveau']) !!}
            &nbsp;·&nbsp; <span class="lbl">Code diplôme :</span> {!! $v($d['formation_code_diplome']) !!}</div>
        <div class="line muted">Périodes de réalisation en entreprise et en CFA : le planning est présent en annexe.</div>
    </div>

    <div class="art">
        <div class="art-h">Article 2 — Modalités de déroulement, de suivi et d'obtention du diplôme ou du titre</div>
        <div class="line"><span class="lbl">Lieu principal de la formation :</span> {!! $v($d['lieu_formation']) !!}</div>
        <div class="line"><span class="lbl">Modalités de déroulement :</span> {!! $v($d['modalites']) !!}</div>
        <div class="line"><span class="lbl">Durée de l'action de formation :</span> {!! $v($d['duree_heures']) !!}</div>
        <div class="line"><span class="lbl">Date de formation en CFA :</span> du {!! $v($d['date_cfa_debut']) !!}
            au {!! $v($d['date_cfa_fin']) !!}.</div>
        <p style="margin-top:4px"><span class="lbl">Modalités d'obtention du diplôme ou du titre :</span> présentation
            de l'apprenti à l'examen de {!! $v($d['formation_intitule']) !!}, dans le centre de formation de la région
            de son lieu d'exercice. Périodes de réalisation en entreprise et en CFA : voir calendrier en annexe.</p>
        <p class="muted">La présentation à l'examen final est obligatoire. En cas d'absence non justifiée, le centre de
            formation pourra réclamer à l'apprenant le remboursement des frais liés à l'organisation de la session
            d'examen, soit un montant de {!! $v($d['frais_examen']) !!} €.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 3 — Modalités de suivi et d'encadrement de la formation</div>
        <p>L'apprenti s'engage à respecter un temps de travail de {!! $v($d['temps_travail']) !!} par semaine selon le
            planning de formation. Un suivi hebdomadaire du temps de présence en CFA est effectué ; toute absence est
            notifiée à l'entreprise ainsi qu'à l'opérateur de compétences (OPCO) qui finance la formation, et doit être
            justifiée auprès du CFA.</p>
        <div class="line"><span class="lbl">Le suivi est effectué par</span> {!! $v($d['resp_peda_nom']) !!}
            <span class="lbl">en qualité de responsable pédagogique</span> — {!! $v($d['resp_peda_email']) !!}</div>
        <p style="margin-top:4px">Le suivi en entreprise est effectué par le maître d'apprentissage désigné ci-dessous :</p>
        <div class="line"><span class="lbl">Nom :</span> {!! $v($d['maitre_nom']) !!}
            &nbsp;·&nbsp; <span class="lbl">Prénom :</span> {!! $v($d['maitre_prenom']) !!}</div>
        <div class="line"><span class="lbl">Mail :</span> {!! $v($d['maitre_mail']) !!}
            &nbsp;·&nbsp; <span class="lbl">Téléphone :</span> {!! $v($d['maitre_tel']) !!}
            &nbsp;·&nbsp; <span class="lbl">Poste occupé :</span> {!! $v($d['maitre_poste']) !!}</div>
    </div>

    <div class="art">
        <div class="art-h">Article 4 — Bénéficiaire de l'action de formation en apprentissage</div>
        <div class="line"><span class="lbl">Nom :</span> {!! $v($d['apprenti_nom']) !!}
            &nbsp;·&nbsp; <span class="lbl">Prénom :</span> {!! $v($d['apprenti_prenom']) !!}</div>
        <div class="line"><span class="lbl">Adresse :</span> {!! $v($d['apprenti_adresse']) !!}</div>
        <div class="line"><span class="lbl">Date de naissance :</span> {!! $v($d['apprenti_naissance']) !!}
            &nbsp;·&nbsp; <span class="lbl">Email :</span> {!! $v($d['apprenti_email']) !!}</div>
        <div class="line"><span class="lbl">Date de début d'exécution du contrat :</span> {!! $v($d['date_debut']) !!}
            &nbsp;·&nbsp; <span class="lbl">Date de fin :</span> {!! $v($d['date_fin']) !!}</div>
    </div>

    <div class="art">
        <div class="art-h">Article 5 — Dispositions financières</div>
        <p>Rappel : gratuité de la formation pour l'apprenti et son représentant légal ; le cas échéant, aucune somme
            ne peut leur être demandée.</p>
        <table class="fin">
            <thead>
                <tr>
                    <th style="width:34%"></th>
                    <th>Montant de la prestation, net de taxe</th>
                    <th>Niveau de prise en charge — OPCO <sup>(2)</sup></th>
                    <th>Reste à charge éventuel de l'entreprise, net de taxe <sup>(3)</sup></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($d['financement'] as $f)
                    <tr>
                        <td style="text-align:left">{{ $f['annee'] }}<sup>{{ $f['annee'] === 1 ? 're' : 'e' }}</sup> année d'exécution du contrat</td>
                        <td>{{ $eur($f['prestation']) }}</td>
                        <td>{{ $eur($f['opco']) }}</td>
                        <td>{{ $eur($f['reste']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p>Le coût total de la formation est de {!! $v($d['cout_total']) !!}. Le coût de la formation sera pris en
            charge par l'OPCO, suivant les conditions générales de gestion de cet organisme.</p>
        <p class="opt">{!! $box(true) !!} <b>Subrogation acceptée</b> : le coût de la formation est pris en charge par
            l'OPCO, pour lequel une subrogation de paiement a été établie.</p>
        <p class="opt">{!! $box(false) !!} <b>Subrogation refusée</b> : le règlement de la formation s'effectue par
            virement bancaire à réception de la facture, dont l'échéance est fixée à 60 jours fin de mois.</p>
        <p class="muted">(2) Tous les montants sont nets de taxe (art. 261-4,4° du Code général des impôts) ; niveau de
            prise en charge défini par la branche, versé par l'OPCO dans la limite du coût réel de la prestation.
            (3) À défaut de reste à charge, indiquer « 0 € ».</p>
    </div>

    <div class="art">
        <div class="art-h">Article 6 — Frais annexes (pendant le temps en CFA uniquement)</div>
        <p>Lorsqu'ils sont financés par le CFA, l'OPCO prend en charge une partie de ces frais sur présentation d'une
            facture nette de taxes, des frais consommés par trimestre.</p>
        <div class="frais"><span class="box">{!! $box($oui($d['frais_hebergement'])) !!}</span> Oui
            &nbsp;&nbsp; <span class="box">{!! $box($non($d['frais_hebergement'])) !!}</span> Non
            &nbsp;— Frais d'hébergement</div>
        <div class="frais"><span class="box">{!! $box($oui($d['frais_restauration'])) !!}</span> Oui
            &nbsp;&nbsp; <span class="box">{!! $box($non($d['frais_restauration'])) !!}</span> Non
            &nbsp;— Frais de restauration</div>
        <div class="frais"><span class="box">{!! $box($oui($d['frais_equipement'])) !!}</span> Oui
            &nbsp;&nbsp; <span class="box">{!! $box($non($d['frais_equipement'])) !!}</span> Non
            &nbsp;— Frais de premier équipement pédagogique <span class="muted">(prise en charge OPCO plafonnée à {{ $d['plafond_equipement'] }} €)</span></div>
        <div class="frais"><span class="box">{!! $box($oui($d['frais_mobilite'])) !!}</span> Oui
            &nbsp;&nbsp; <span class="box">{!! $box($non($d['frais_mobilite'])) !!}</span> Non
            &nbsp;— Frais liés à la mobilité internationale</div>
        <div class="frais"><span class="box">{!! $box($oui($d['majoration_rqth'])) !!}</span> Oui
            &nbsp;&nbsp; <span class="box">{!! $box($non($d['majoration_rqth'])) !!}</span> Non
            &nbsp;— Majoration forfaitaire annuelle RQTH</div>
    </div>

    <div class="art">
        <div class="art-h">Article 7 — Modalités de règlement (en cas de reste à charge de l'entreprise)</div>
        <p>En contrepartie des prestations fournies par le CFA, l'OPCO auquel adhère l'entreprise verse à cet organisme
            un montant annuel constitué de la somme du niveau de prise en charge (1° du I de l'article L. 6332-14 du
            Code du travail) et des frais annexes (3° du même article), selon les modalités de versement prévues à
            l'article R. 6332-25 III. Dans l'hypothèse où l'OPCO ne prendrait pas en charge la totalité du financement,
            quel qu'en soit le motif, l'entreprise resterait tenue du paiement du coût total de la formation envers le
            CFA ; une facture du montant non pris en charge lui serait alors adressée.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 8 — Clause suspensive</div>
        <p>L'exécution de la présente convention est soumise au dépôt du contrat par l'OPCO ou la DREETS/DDETS
            (art. L. 6224-1 du Code du travail). Le contrat d'apprentissage sera transmis par l'employeur à l'OPCO dont
            il relève, pour prise en charge financière.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 9 — Différends éventuels</div>
        <p>Si une contestation ou un différend ne peuvent être réglés à l'amiable, le Tribunal de Commerce sera seul
            compétent pour régler le litige.</p>
    </div>

    <div class="art">
        <div class="art-h">Article 10 — Mandat</div>
        <p class="opt">{!! $box(false) !!} L'entreprise signataire <b>ne souhaite pas</b> donner mandat au CFA pour
            accomplir les formalités nécessaires aux opérations prévues à l'article L. 6224-1 du Code du travail ; elle
            demeure seule responsable de l'accomplissement de ces opérations.</p>
        <p class="opt">{!! $box(true) !!} Par la présente convention, l'entreprise signataire <b>donne mandat</b> au CFA
            signataire, qui l'accepte, pour accomplir toutes formalités nécessaires aux opérations prévues à l'article
            L. 6224-1 du Code du travail. Ce mandat est accompli à titre gratuit, le CFA mandataire ne recevant aucune
            rémunération. Le mandataire s'engage à l'exécuter personnellement, dans le meilleur intérêt du mandant. En
            cas de différend, l'article 9 de la présente convention s'applique.</p>
    </div>

    <p style="margin-top:14px">Fait en double exemplaire, à {!! $v($d['fait_a']) !!} le {!! $v($d['fait_le']) !!}.</p>

    <table class="sign-wrap">
        <tr>
            <td>
                <div class="sign-h">Pour l'entreprise {{ $d['entreprise_designation'] ? '— '.$d['entreprise_designation'] : '' }}</div>
                @if (filled($d['entreprise_representant']))
                    <div class="sign-qui">{{ $d['entreprise_representant'] }}{{ $d['entreprise_representant_qualite'] ? ', '.$d['entreprise_representant_qualite'] : '' }}</div>
                @endif
                <div class="muted">Nom et qualité du signataire · cachet de l'entreprise</div>
            </td>
            <td>
                <div class="sign-h">Pour le CFA {{ $d['cfa_designation'] ? '— '.$d['cfa_designation'] : '' }}</div>
                @php($cfaSigne = filled($d['cfa_signature_image']) || filled($d['cfa_cachet_image']))
                @if (filled($d['cfa_representant']))
                    <div class="sign-qui">{{ $d['cfa_representant'] }}{{ $d['cfa_representant_qualite'] ? ', '.$d['cfa_representant_qualite'] : '' }}</div>
                @endif
                @if ($cfaSigne)
                    <div class="sign-img">
                        @if (filled($d['cfa_signature_image']))
                            <img src="{{ $d['cfa_signature_image'] }}" alt="Signature du représentant du CFA">
                        @endif
                        @if (filled($d['cfa_cachet_image']))
                            <img src="{{ $d['cfa_cachet_image'] }}" alt="Cachet du CFA">
                        @endif
                    </div>
                @else
                    <div class="muted">Nom et qualité du signataire · cachet du CFA</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="foot-note">
        @if ($cfaSigne)
            Document généré automatiquement par l'ERP CFA, signé par le CFA — reste la signature de l'entreprise.
        @else
            Document généré automatiquement par l'ERP CFA — à vérifier et signer par les parties.
        @endif
    </div>
</body>
</html>
