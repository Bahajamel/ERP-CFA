# Product Backlog — ERP CFA

Backlog produit complet. Source unique des epics et user stories.

- **Format story** : `En tant que <rôle>, je veux <action> afin de <bénéfice>.`
- **Estimation** : points Fibonacci (1, 2, 3, 5, 8, 13).
- **Priorité** : `P0` (V1 indispensable) · `P1` (après V1) · `P2` (version avancée).
- **Statut** : `À faire` · `En cours` · `En revue` · `Terminé`.

> Les **critères d'acceptation détaillés** et les **règles métier** par module
> sont dans le CDC. Ce backlog les résume et les rend estimables. Le détail story
> par story arrivera dans `epics/EPIC-xx-*.md` au fil des sprints.

---

## Légende des epics

| Epic | Module | Priorité | Branche Git |
|---|---|---|---|
| EPIC-00 | Socle technique & DevOps | P0 | `feature/setup` |
| EPIC-01 | Authentification, rôles & permissions | P0 | `feature/auth-roles` |
| EPIC-02 | Candidats | P0 | `feature/candidates` |
| EPIC-03 | Entreprises | P0 | `feature/companies` |
| EPIC-04 | Besoins entreprises | P0 | `feature/needs` |
| EPIC-05 | Matching candidat / besoin | P0 | `feature/matching` |
| EPIC-06 | Documents (GED) | P0 | `feature/documents` |
| EPIC-07 | Admission | P0 | `feature/admission` |
| EPIC-08 | Contrats & conventions | P0 | `feature/contracts` |
| EPIC-09 | OPCO | P0 | `feature/opco` |
| EPIC-10 | Tâches, alertes & notifications | P0 | `feature/tasks-alerts` |
| EPIC-11 | Historique & journalisation | P0 | `feature/history` |
| EPIC-12 | Dashboards par rôle | P0 | `feature/dashboard` |
| EPIC-13 | Recherche & filtres transversaux | P0 | `feature/search` |
| EPIC-14 | Scolarité & assiduité | P1 | `feature/attendance` |
| EPIC-15 | Service fait | P1 | `feature/service-fait` |
| EPIC-16 | Finance simple | P1 | `feature/finance` |
| EPIC-17 | Qualité & preuves | P1 | `feature/quality` |
| EPIC-18 | Ruptures | P1 | `feature/ruptures` |
| EPIC-19 | Exports | P1 | `feature/exports` |
| EPIC-20+ | Vision avancée (P2) | P2 | *(à découper plus tard)* |

---

# P0 — Parcours principal (V1)

## EPIC-00 — Socle technique & DevOps
*Branche : `feature/setup` · Sprint 0*

| ID | User story | Pts |
|---|---|---|
| P0-00-1 | En tant que dev, je veux un projet Laravel + Filament + PostgreSQL initialisé afin de pouvoir développer les modules. | 5 |
| P0-00-2 | En tant que dev, je veux les packages spatie (permission, activitylog, medialibrary) installés et configurés. | 3 |
| P0-00-3 | En tant que dev, je veux un layout/navigation Filament de base avec menu par module. | 3 |
| P0-00-4 | En tant que dev, je veux Pest + un pipeline CI GitHub Actions (lint + tests) afin de sécuriser les merges. | 5 |
| P0-00-5 | En tant que dev, je veux les 3 environnements (dev/recette/prod) et la config S3 + Redis documentés. | 3 |

## EPIC-01 — Authentification, rôles & permissions
*Branche : `feature/auth-roles` · CDC §20, §25*

| ID | User story | Pts |
|---|---|---|
| P0-01-1 | En tant qu'utilisateur, je veux me connecter par identifiant + mot de passe afin d'accéder à l'ERP. | 3 |
| P0-01-2 | En tant qu'utilisateur, je veux activer la MFA afin de sécuriser mon compte. | 5 |
| P0-01-3 | En tant qu'admin, je veux créer/modifier/désactiver un utilisateur (sans supprimer l'historique). | 3 |
| P0-01-4 | En tant qu'admin, je veux attribuer un rôle parmi les 10 rôles et définir les permissions par module. | 5 |
| P0-01-5 | En tant qu'utilisateur, je veux ne voir que les modules autorisés par mon rôle. | 5 |
| P0-01-6 | En tant qu'admin, je veux consulter les connexions et actions sensibles. | 3 |

**Rôles** : Administrateur, Direction, Commercial, Admission, Administratif, Scolarité, Pédagogie, Finance, Qualité, Formateur.
**Règles** : accès limité aux modules du rôle ; commercial ≠ données financières ; formateur ≠ finance ; finance ≠ évaluations pédagogiques.

## EPIC-02 — Candidats
*Branche : `feature/candidates` · CDC §5*

| ID | User story | Pts |
|---|---|---|
| P0-02-1 | En tant que commercial, je veux créer/modifier un candidat afin de centraliser ses infos. | 3 |
| P0-02-2 | En tant qu'utilisateur, je veux rechercher et filtrer les candidats (statut, formation, source, commercial, période). | 3 |
| P0-02-3 | En tant qu'utilisateur, je veux consulter une fiche candidat complète (entreprise, admission, contrat, docs manquants). | 5 |
| P0-02-4 | En tant que commercial, je veux ajouter des notes internes et créer des tâches de relance. | 3 |
| P0-02-5 | En tant qu'utilisateur, je veux suivre l'historique des échanges du candidat. | 2 |
| P0-02-6 | En tant que système, je veux empêcher un candidat sans email **ni** téléphone, et bloquer "Dossier complet" si pièces obligatoires manquantes. | 3 |
| P0-02-7 | En tant que commercial, je veux une **vue Kanban** des candidats par statut (drag = transition contrôlée) afin de piloter mon flux comme un CRM. *(Pilier E / F1)* | 3 |
| P0-02-8 | En tant que commercial, je veux une **timeline d'interactions** (notes/appels/RDV) + une **prochaine action** datée qui crée une tâche. *(Pilier E / F3)* | 3 |

**Statuts** : Dossier incomplet · Dossier complet · En recherche d'entreprise · Contrat signé · Rupture.
**Enrichissement Pilier E** (CRM commercial) → [excellence-commerciale-crm-matching.md](../architecture/excellence-commerciale-crm-matching.md).

## EPIC-03 — Entreprises
*Branche : `feature/companies` · CDC §6*

| ID | User story | Pts |
|---|---|---|
| P0-03-1 | En tant que commercial, je veux créer/modifier une entreprise (raison sociale, nom commercial, SIRET, adresse, secteur). | 3 |
| P0-03-2 | En tant que commercial, je veux ajouter un ou plusieurs contacts à une entreprise. | 2 |
| P0-03-3 | En tant que commercial, je veux ajouter un maître d'apprentissage / tuteur rattaché à l'entreprise. | 2 |
| P0-03-4 | En tant qu'utilisateur, je veux consulter les contrats, candidats proposés et historique d'une entreprise. | 5 |
| P0-03-5 | En tant que commercial, je veux ajouter notes / comptes rendus et suivre satisfaction / incidents. | 2 |
| P0-03-6 | En tant que commercial, je veux une **timeline d'interactions** entreprise + une **prochaine action** datée (relance). *(Pilier E / F3)* | 2 |
| P1-03-7 | En tant que commercial, je veux une liste **« entreprises à cibler »** pour un candidat (besoin ouvert compatible / a déjà recruté). *(Pilier E / F4)* | 3 |

**Règles** : pas de contrat actif sans contact principal ; un tuteur est rattaché à une entreprise.
**Enrichissement Pilier E** → [excellence-commerciale-crm-matching.md](../architecture/excellence-commerciale-crm-matching.md).

## EPIC-04 — Besoins entreprises
*Branche : `feature/needs` · CDC §7*

| ID | User story | Pts |
|---|---|---|
| P0-04-1 | En tant que commercial, je veux créer un besoin rattaché à une entreprise (poste, formation, localisation, nb postes, rythme, prérequis, contact, tuteur). | 5 |
| P0-04-2 | En tant que commercial, je veux rattacher des candidats à un besoin et suivre ceux proposés. | 3 |
| P0-04-3 | En tant que commercial, je veux faire évoluer le statut du besoin et le clôturer quand pourvu. | 3 |
| P0-04-4 | En tant que commercial, je veux voir tous les besoins ouverts avec formation cible et nb de postes restants. | 3 |
| P0-04-5 | En tant que commercial, je veux une **vue Kanban (entonnoir)** des besoins par statut afin de suivre le recrutement comme un pipeline. *(Pilier E / F1)* | 2 |

**Statuts** : Besoin créé · En qualification · Profils recherchés · Profils envoyés · Entretien entreprise prévu · Candidat retenu · Besoin pourvu · Annulé · Archivé.
**Enrichissement Pilier E** → [excellence-commerciale-crm-matching.md](../architecture/excellence-commerciale-crm-matching.md).

## EPIC-05 — Matching candidat / besoin
*Branche : `feature/matching` · CDC §8*

| ID | User story | Pts |
|---|---|---|
| P0-05-1 | En tant que commercial, je veux rechercher les candidats compatibles avec un besoin (formation, dispo, mobilité, niveau…). | 5 |
| P0-05-2 | En tant que commercial, je veux proposer un candidat à une entreprise et le rattacher au besoin. | 3 |
| P0-05-3 | En tant que commercial, je veux suivre CV envoyé, entretiens, retours et résultat (accepté/refusé). | 3 |
| P0-05-4 | En tant qu'utilisateur, je veux conserver l'historique des propositions et voir, par besoin, tous les candidats et leur statut. | 3 |
| P0-05-5 | En tant que système, j'empêche "Accepté" sur un besoin déjà clôturé. | 2 |
| P0-05-6 | En tant que commercial, je veux un **matching assisté par score** : depuis un besoin, candidats classés par compatibilité (règles) + **proposition en 1 clic**. *(Pilier E / F2)* | 5 |

**Statuts** : Proposé · CV envoyé · Entretien prévu · En attente de retour · Accepté · Refusé par l'entreprise · Refusé par le candidat · Abandonné.
**Enrichissement Pilier E** → [excellence-commerciale-crm-matching.md](../architecture/excellence-commerciale-crm-matching.md).

## EPIC-06 — Documents (GED)
*Branche : `feature/documents` · CDC §10 · medialibrary*

| ID | User story | Pts |
|---|---|---|
| P0-06-1 | En tant qu'utilisateur, je veux importer un document et le rattacher (polymorphe) à un candidat, entreprise, contrat ou dossier. | 5 |
| P0-06-2 | En tant qu'utilisateur, je veux indiquer le type et le statut d'un document. | 2 |
| P0-06-3 | En tant qu'utilisateur, je veux remplacer un document tout en conservant l'historique des versions. | 5 |
| P0-06-4 | En tant qu'utilisateur, je veux rechercher, télécharger un document et voir qui l'a ajouté et quand. | 3 |
| P0-06-5 | En tant que système, j'empêche la suppression définitive sans trace d'un document critique. | 3 |

**Types** : CV candidat, CV maître d'apprentissage, test de positionnement, contrat, CERFA, convention, calendrier, justificatif d'absence, preuve de service fait, facture, document qualité, autre.
**Statuts** : En attente · Reçu · Expiré.

## EPIC-07 — Admission
*Branche : `feature/admission` · CDC (dossier admission, checklist)*

| ID | User story | Pts |
|---|---|---|
| P0-07-1 | En tant qu'admission/pédagogie, je veux ouvrir un dossier admission rattaché à un candidat. | 3 |
| P0-07-2 | En tant qu'admission, je veux une checklist des pièces obligatoires avec état (présente/manquante/non conforme). | 5 |
| P0-07-3 | En tant qu'admission, je veux valider ou refuser un dossier et préparer le passage au contrat. | 5 |
| P0-07-4 | En tant que système, j'empêche la validation tant que des pièces obligatoires manquent ou sont non conformes. | 3 |

## EPIC-08 — Contrats & conventions
*Branche : `feature/contracts` · CDC §11*

| ID | User story | Pts |
|---|---|---|
| P0-08-1 | En tant qu'administratif, je veux créer un dossier contrat (candidat, entreprise, formation, RNCP, dates, tuteur, rythme, lieu). | 5 |
| P0-08-2 | En tant qu'administratif, je veux vérifier les champs obligatoires et générer/importer les documents contractuels. | 5 |
| P0-08-3 | En tant qu'administratif, je veux suivre le statut de signature et conserver les versions signées. | 3 |
| P0-08-4 | En tant qu'administratif, je veux envoyer le dossier vers le suivi OPCO. | 2 |
| P0-08-5 | En tant que système, j'empêche "Signé" sans document signé associé ou justification, et je trace toute modif post-signature. | 3 |
| P1-08-6 | En tant qu'administratif, je veux envoyer le dossier en **signature électronique** multi-parties (employeur, apprenti, +représentant légal si mineur, CFA) via un prestataire eIDAS. *(gap concurrentiel #1 / G2)* | 5 |
| P1-08-7 | En tant que système, à la signature de toutes les parties je veux passer le contrat à **« Signé » automatiquement** et archiver le PDF signé à valeur probante. *(gap concurrentiel #1 / G3)* | 3 |

**Statuts** : Brouillon · Informations manquantes · Prêt à vérifier · Envoyé pour signature · Signé · Transmis OPCO · Actif · Rompu · Archivé.
**Enrichissement (gap concurrentiel #1)** — génération CERFA (FA13) + signature électronique → [signature-electronique-cerfa.md](../architecture/signature-electronique-cerfa.md). P0-08-2 est enrichie de la **génération du CERFA pré-rempli** (brique G1).

## EPIC-09 — OPCO
*Branche : `feature/opco` · CDC §12*

| ID | User story | Pts |
|---|---|---|
| P0-09-1 | En tant qu'administratif, je veux créer un dossier OPCO lié à un contrat (OPCO, montant prévu, dates). | 3 |
| P0-09-2 | En tant qu'administratif, je veux suivre le statut de dépôt et le retour OPCO, montant accepté inclus. | 3 |
| P0-09-3 | En tant qu'administratif, je veux saisir un motif de rejet et créer automatiquement une action de correction. | 5 |
| P0-09-4 | En tant qu'administratif, je veux suivre relances et archiver les preuves de dépôt/réponse. | 3 |
| P0-09-5 | En tant que direction, je veux voir tous les dossiers OPCO bloqués (motif, responsable, prochaine action). | 3 |
| P0-09-6 | En tant que système, j'empêche "Prêt au dépôt" si le contrat n'est pas signé ; un rejet exige un motif. | 3 |

**Statuts** : Non créé · À préparer · Prêt au dépôt · Déposé · En attente retour OPCO · Accepté · Rejeté · En correction · Corrigé · Clôturé.

## EPIC-10 — Tâches, alertes & notifications
*Branche : `feature/tasks-alerts` · CDC §19*

| ID | User story | Pts |
|---|---|---|
| P0-10-1 | En tant qu'utilisateur, je veux créer une tâche, l'assigner, lui donner une date limite et la relier (polymorphe) à un objet. | 5 |
| P0-10-2 | En tant qu'utilisateur, je veux marquer une tâche terminée et voir mes tâches en retard. | 3 |
| P0-10-3 | En tant qu'utilisateur, je veux recevoir une alerte in-app (et email si nécessaire) sur les événements clés. | 5 |
| P0-10-4 | En tant que système, je veux générer des alertes automatiques (dossier incomplet, contrat à signer, OPCO sans retour, rejet OPCO…). | 5 |
| P0-10-5 | En tant que système, quand une pièce obligatoire d'admission repasse à *manquante/non conforme* et que le candidat est « Dossier complet », je le **rétrograde en « Incomplet »** (via la machine à états, journalisé) et je crée une **tâche de relance**. | 3 |

**Statuts** : À faire · En cours · En attente · Terminée · Annulée · En retard.

> **Note** : P0-10-5 est l'**auto-synchronisation** volontairement reportée depuis la
> story P0-02-6 (règles candidats). La garde de P0-02-6 empêche déjà d'*aller* vers
> « Complet » avec des pièces manquantes ; P0-10-5 gère le sens inverse (rétrogradation
> automatique) via le moteur de règles / `afterTransition` (cf. `vision-intelligente.md`).

## EPIC-11 — Historique & journalisation
*Branche : `feature/history` · CDC §21 · activitylog*

| ID | User story | Pts |
|---|---|---|
| P0-11-1 | En tant que système, je veux journaliser les actions importantes (création/modif candidat, validation admission, doc, statut contrat/OPCO, motif rejet, etc.). | 5 |
| P0-11-2 | En tant que responsable, je veux consulter l'historique d'un dossier (utilisateur, date/heure, action, objet, ancienne/nouvelle valeur). | 3 |

## EPIC-12 — Dashboards par rôle
*Branche : `feature/dashboard` · CDC §18*

| ID | User story | Pts |
|---|---|---|
| P0-12-1 | En tant que direction, je veux un dashboard avec les KPI globaux (candidats, contrats, OPCO bloqués, pièces manquantes, ruptures, alertes…). | 8 |
| P0-12-2 | En tant que commercial, je veux mon dashboard (candidats à relancer, leads chauds, besoins, matchs, RDV, actions en retard). | 5 |
| P0-12-3 | En tant qu'admission/administratif, je veux mes dashboards (dossiers incomplets, pièces manquantes, contrats/OPCO à traiter). | 5 |
| P0-12-4 | En tant qu'utilisateur, je veux que chaque indicateur soit cliquable et mène à la liste filtrée des dossiers concernés. | 5 |

**Règle** : aucun chiffre décoratif — tout indicateur est relié à des données réelles.

## EPIC-13 — Recherche & filtres transversaux
*Branche : `feature/search` · CDC §22*

| ID | User story | Pts |
|---|---|---|
| P0-13-1 | En tant qu'utilisateur, je veux une recherche globale (candidat, entreprise, contrat, OPCO, document, tâche). | 5 |
| P0-13-2 | En tant qu'utilisateur, je veux des filtres transversaux (statut, formation, responsable, période, entreprise, type, priorité, anomalie). | 3 |

---

# P1 — Après validation de la V1

## EPIC-14 — Scolarité & assiduité
*Branche : `feature/attendance` · CDC §13*

| ID | User story | Pts |
|---|---|---|
| P1-14-1 | Créer une session de formation et y rattacher des apprenants. | 5 |
| P1-14-2 | Saisir présences/absences/retards et qualifier une absence (justifiée/injustifiée). | 5 |
| P1-14-3 | Ajouter un justificatif rattaché à une absence. | 3 |
| P1-14-4 | Consulter l'assiduité par apprenant et par groupe sur une période. | 5 |
| P1-14-5 | Système : période non validable si présences non renseignées ; absence injustifiée → alerte/tâche. | 3 |

**Statuts présence** : Présent · Absent justifié · Absent injustifié · Retard · Départ anticipé · Non renseigné.

## EPIC-15 — Service fait
*Branche : `feature/service-fait` · CDC §13*

| ID | User story | Pts |
|---|---|---|
| P1-15-1 | Valider une période mensuelle d'assiduité. | 5 |
| P1-15-2 | Générer une preuve liée au service fait. | 3 |

## EPIC-16 — Finance simple
*Branche : `feature/finance` · CDC §15*

| ID | User story | Pts |
|---|---|---|
| P1-16-1 | Créer une ligne financière liée à un contrat (montants attendu/accepté/facturé/encaissé/bloqué). | 5 |
| P1-16-2 | Suivre échéances, paiements et retards. | 5 |
| P1-16-3 | Générer une facture brouillon ou importer une facture. | 5 |
| P1-16-4 | Système : facture non émise sans montant + destinataire ; montant bloqué exige un motif ; paiement relié à une facture/ligne. | 3 |

**Statuts** : Prévisionnel · À facturer · Facture préparée · Facturé · Partiellement payé · Payé · En retard · Bloqué · Annulé.

## EPIC-17 — Qualité & preuves
*Branche : `feature/quality` · CDC §16*

| ID | User story | Pts |
|---|---|---|
| P1-17-1 | Créer une preuve et la rattacher (polymorphe) à un candidat/apprenant/contrat/entreprise/session. | 5 |
| P1-17-2 | Rattacher une preuve à un indicateur qualité / une mission CFA. | 5 |
| P1-17-3 | Signaler une preuve manquante et créer une action corrective (responsable + date limite). | 5 |
| P1-17-4 | Exporter un pack de preuves simple. | 5 |

**Statuts preuve** : Validée · Refusée · Manquante.

## EPIC-18 — Ruptures
*Branche : `feature/ruptures` · CDC §17*

| ID | User story | Pts |
|---|---|---|
| P1-18-1 | Ouvrir un dossier rupture (date, motif, documents). | 5 |
| P1-18-2 | Suivre accompagnement et recherche d'un nouvel employeur. | 5 |
| P1-18-3 | Système : une rupture crée une trace dans contrat, OPCO et finance ; actions conservées comme preuves. | 5 |

**Statuts** : Rupture suspectée · Rupture confirmée · Documents en attente · Accompagnement en cours · Recherche nouvel employeur · Nouveau contrat trouvé · Sortie définitive · Clôturé.

## EPIC-19 — Exports
*Branche : `feature/exports` · CDC §23*

| ID | User story | Pts |
|---|---|---|
| P1-19-1 | Exporter les listes clés (candidats, entreprises, besoins, contrats, OPCO, docs manquants, absences, services faits, tableau financier, pack preuves). | 8 |
| P1-19-2 | Système : export sensible réservé aux autorisés ; conserver date de génération + auteur. | 3 |

---

# P2 — Version avancée (vision long terme)

Non découpé en stories pour l'instant (specs à préciser le moment venu). Inclut
aussi les domaines de la **vision élargie** évoquée (RH, marketing) au-delà du CDC actuel.

| Epic | Périmètre |
|---|---|
| EPIC-20 | Portails externes (entreprises, apprenants, financeurs) |
| EPIC-21 | Automatisations avancées (workflows, relances auto) |
| EPIC-22 | Génération documentaire avancée (modèles, publipostage) |
| EPIC-23 | Connecteurs externes (OPCO, DECA/CERFA, comptabilité) |
| EPIC-24 | Reporting réglementaire complet |
| EPIC-25 | IA d'aide à la décision |
| EPIC-26 | Analyse prédictive (risque rupture, churn) |
| EPIC-27 | Multi-campus avancé |
| EPIC-28 | **RH** (salariés, formateurs, congés, paie) — *hors CDC v2, vision élargie* |
| EPIC-29 | **Marketing** (campagnes, acquisition, sources de leads) — *hors CDC v2, vision élargie* |

> Ces epics seront détaillés en user stories lorsqu'ils entreront dans un sprint.
