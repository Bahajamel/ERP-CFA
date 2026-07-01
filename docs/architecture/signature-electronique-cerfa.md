# Signature électronique & génération du CERFA (extension EPIC-08)

> **Statut :** spécification prête à développer. Extension du CDC **EPIC-08
> (Contrats & conventions)**, prélude à **EPIC-23 (Connecteurs externes —
> OPCO, DECA/CERFA)**.
>
> **Origine :** gap n°1 de l'[audit concurrentiel 2026](../strategy/audit-concurrentiel-2026.md).
> Les leaders (Ypareo, Digiforma) **génèrent** le contrat et le font **signer
> électroniquement** ; chez nous le module Contrats gère aujourd'hui le *statut*
> de signature (`Envoyé pour signature → Signé`) et la garde « pas de signé sans
> preuve » (P0-08-5), mais **ne produit ni ne fait signer** le document.
>
> **Principe :** on n'ajoute pas de module hors CDC ; on **complète la façon de
> réaliser** EPIC-08 (P0-08-2 « générer les documents contractuels » et P0-08-3
> « statut de signature »). Prestataire de signature **abstrait** derrière une
> interface (souveraineté + testabilité), aucune dépendance dure en V1.

---

## Constat concurrentiel

| Concurrent | Ce qu'il fait | Notre transposition |
|---|---|---|
| **Ypareo** | Pré-remplit le CERFA (FA13) depuis le dossier, dépôt dématérialisé | G1, G3 |
| **Digiforma / Dendreo** | Envoi du contrat en signature électronique intégrée | G2 |
| **Prestataires eIDAS (Yousign, Docusign)** | Signature multi-parties à valeur probante | G2 |

Le concurrent réel reste **« imprimer → scanner → renvoyer par mail »** : générer
un CERFA pré-rempli et récupérer une signature en ligne supprime plusieurs jours
de délai et sécurise le **financement** (pas de CERFA signé → pas de prise en
charge OPCO).

---

## Périmètre — 3 briques

| Brique | Objet | Story | Priorité |
|---|---|---|---|
| **G1** | Génération du **CERFA 10103 (FA13)** + convention, pré-remplis | P0-08-2 (enrichie) | **P0** |
| **G2** | **Signature électronique** multi-signataires (prestataire eIDAS) | P1-08-6 | **P1** |
| **G3** | Réception signature → statut **« Signé »** auto + archivage probant | P1-08-7 | **P1** |
| *(G4)* | *Télétransmission DECA/OPCO (hors périmètre — EPIC-23)* | EPIC-23 | *V2* |

---

## G1 — Génération du CERFA & de la convention (P0-08-2 enrichie)

**Objectif.** Depuis un dossier contrat complet, produire en 1 clic le **CERFA
d'apprentissage 10103\*XX (FA13)** et la **convention de formation** pré-remplis,
prêts à signer, stockés dans la GED du contrat.

**Fonctionnalités attendues.**
- Action **« Générer le CERFA »** sur la fiche contrat, visible seulement si les
  **champs obligatoires** sont présents (candidat, entreprise, formation, RNCP,
  dates, tuteur, rythme, lieu — cf. P0-08-1).
- Pré-remplissage depuis les données du dossier : parties (apprenti + représentant
  légal si mineur, employeur, CFA), formation (RNCP, dates, durée), rémunération
  (grille légale selon âge/année), maître d'apprentissage.
- Sortie **PDF** rattachée au contrat via la GED (`documents`/medialibrary,
  `DocumentType::Cerfa` et `::Convention`), horodatée, versionnée.
- Régénérer remplace la version **brouillon** (jamais une version signée).

**Règles métier.**
- Génération **interdite** si des champs obligatoires manquent → message listant
  les manques (réutilise la logique de garde P0-08-1/P0-08-5).
- La **rémunération minimale** est calculée par règles (âge de l'apprenti × année
  d'exécution × % SMIC/SMC) — déterministe, dans une **config** (comme
  `business_rules`), ajustable sans redéploiement.
- Un document généré est marqué `brouillon` tant qu'aucune signature n'est lancée.

**Critères d'acceptation.**
- Pour un contrat complet, « Générer le CERFA » produit un PDF pré-rempli
  (parties, formation, dates, rémunération) visible dans l'onglet Documents.
- Sur un contrat incomplet, l'action est masquée/refusée avec la liste des manques.
- La rémunération affichée correspond au barème légal pour l'âge/l'année.

---

## G2 — Envoi en signature électronique (P1-08-6)

**Objectif.** Envoyer le CERFA généré en **signature électronique multi-parties**
(employeur, apprenti, +représentant légal si mineur, CFA) via un prestataire
**eIDAS** (défaut : **Yousign**, souveraineté FR), le tout **abstrait** derrière
une interface.

**Fonctionnalités attendues.**
- Action **« Envoyer en signature »** (visible si un CERFA `brouillon` existe et le
  contrat est au statut `Prêt à vérifier`).
- Création d'un **dossier de signature** listant les signataires (rôle, nom,
  e-mail), envoi via le prestataire, récupération des **liens de signature**.
- Passage du statut contrat à **`Envoyé pour signature`** (transition machine à
  états existante) ; suivi de l'état **par signataire** (en attente / signé / refusé).
- Relance manuelle + relance auto (branchée sur Tâches/Alertes EPIC-10 : « signature
  en attente depuis X jours »).

**Règles métier.**
- Signataires obligatoires : **employeur + apprenti + CFA** ; **représentant légal**
  requis si l'apprenti est **mineur** à la date de début.
- Un seul dossier de signature **actif** par contrat ; en relancer un annule le
  précédent (traçé).
- Aucune donnée personnelle transmise au prestataire au-delà du nécessaire (RGPD :
  nom, e-mail, PDF).

**Critères d'acceptation.**
- Depuis un contrat « Prêt à vérifier » avec CERFA généré, l'envoi crée un dossier
  chez le prestataire (mode **sandbox** en dev), passe le contrat à « Envoyé pour
  signature » et affiche l'état de chaque signataire.
- Un apprenti mineur impose la présence du représentant légal, sinon refus explicite.

---

## G3 — Réception de la signature & valeur probante (P1-08-7)

**Objectif.** Clore la boucle sans ressaisie : à la signature de **toutes** les
parties, passer le contrat à **`Signé`** automatiquement et archiver le PDF signé
à valeur probante.

**Fonctionnalités attendues.**
- **Webhook** prestataire → maj de l'état des signataires ; quand tous ont signé,
  **transition automatique** du contrat vers `Signé` (via la machine à états, donc
  la garde P0-08-5 est satisfaite par le **document signé** rapatrié).
- Rapatriement du **PDF signé + dossier de preuve** (certificat/piste d'audit
  eIDAS) dans la GED (`DocumentType::Cerfa`, marqué `signé`, non supprimable).
- En cas de **refus** d'un signataire → statut signature `Refusé`, notification +
  tâche de reprise ; le contrat **ne** passe **pas** à `Signé`.
- Journalisation (`activity_log`) de chaque événement (envoi, signé par X, refusé,
  finalisé).

**Règles métier.**
- La transition auto vers `Signé` **ne s'appuie que** sur l'événement prestataire
  « tous signés » + présence du PDF signé (jamais un clic manuel qui contournerait
  la preuve).
- Toute modification **post-signature** reste tracée (P0-08-5 déjà en place).

**Critères d'acceptation.**
- Quand toutes les parties ont signé, le contrat passe à « Signé » sans action
  manuelle, et le PDF signé + la preuve d'audit sont dans la GED.
- Un refus laisse le contrat en « Envoyé pour signature » et crée une tâche.

---

## Impact sur le modèle de données

| Élément | Nature | Détail |
|---|---|---|
| `contracts.cerfa_genere_at` | colonne | horodatage de génération G1 |
| `contracts.signature_dossier_ref` | colonne (nullable) | id de transaction prestataire (G2) |
| `contract_signatures` | **nouvelle table** | 1 ligne par signataire : `contract_id`, `role` (employeur/apprenti/representant_legal/cfa), `nom`, `email`, `statut` (en_attente/signe/refuse), `signed_at`, `provider_ref` |
| Documents générés/signés | **existant** | via `documents`/medialibrary + `DocumentType::Cerfa`/`::Convention` (aucune table nouvelle) |
| Barème de rémunération | **config** | grille % SMIC par âge × année (comme `business_rules`) |

**Enums.** `SignataireRole` (employeur, apprenti, representant_legal, cfa) ;
réutiliser `ContractSignatureStatut` au niveau contrat, ajouter un statut
par-signataire si besoin (`en_attente/signe/refuse`).

**Statuts contrat.** Aucun nouveau : on réutilise `Prêt à vérifier → Envoyé pour
signature → Signé` (déjà dans EPIC-08). G2/G3 **pilotent** ces transitions.

---

## Choix techniques

- **Génération PDF** : template CERFA FA13 pré-rempli (`barryvdh/laravel-dompdf`
  ou remplissage de PDF AcroForm officiel). Sortie stockée en medialibrary.
- **Signature** : interface `ElectronicSignatureProvider` (méthodes
  `createSignatureRequest()`, `getStatus()`, `downloadSignedDocument()`), impl.
  **Yousign** (eIDAS, FR) ; **fake** en dev/tests (pas d'appel réseau, webhook
  simulé). Clés en `config/services.php` + `.env` (jamais en dur).
- **Webhook** : route signée + vérification de signature du prestataire ; idempotent
  (comme les alertes auto).
- **Garde-fous vision** : déterministe et traçable ; aucune donnée décorative ; la
  preuve prime sur l'action manuelle.

---

## Sécurité & conformité

- **eIDAS** : signature électronique **avancée** (a minima) à valeur probante ;
  conserver le **dossier de preuve** (piste d'audit) avec le PDF.
- **RGPD** : minimiser les données transmises (nom, e-mail, PDF) ; base légale =
  exécution du contrat ; durée de conservation alignée sur l'obligation légale
  d'archivage du contrat d'apprentissage.
- **Traçabilité** : chaque étape journalisée (`activity_log`), documents signés
  **non supprimables**.

---

## Découpage & priorisation

| Story | Intitulé | Pts | Priorité |
|---|---|---|---|
| **P0-08-2** *(enrichie)* | Générer le CERFA (FA13) + convention pré-remplis depuis le dossier | 5 | **P0** |
| **P1-08-6** | Envoyer le dossier en signature électronique multi-parties (eIDAS) | 5 | **P1** |
| **P1-08-7** | Réception signature → « Signé » auto + archivage à valeur probante | 3 | **P1** |
| *(EPIC-23)* | Télétransmission DECA/OPCO sans ressaisie | — | *V2* |

> ⚠️ **À arbitrer par le PO.** G1 (P0) apporte déjà l'effet « waouh » démo et
> sécurise le CERFA ; G2/G3 (P1) ferment la boucle signature. G4 (DECA) dépend de
> l'accès API OPCO → V2 (EPIC-23).

---

## Dépendances

- **EPIC-08** (dossier contrat, champs obligatoires, garde P0-08-5) — ✅ en place.
- **EPIC-06 GED** (medialibrary) — ✅ en place.
- **EPIC-10 Tâches/Alertes** — ✅ en place (relances de signature).
- **Prestataire e-signature** (compte Yousign sandbox) — à provisionner pour G2.
