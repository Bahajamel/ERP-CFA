# Sprint Roadmap — ERP CFA

**Sprint = 1 semaine.** Chaque sprint livre un **incrément démontrable**.
Ordre dicté par les dépendances entre modules (un module en aval a besoin de
l'amont : matching a besoin de candidats + besoins, OPCO a besoin de contrats…).

> Objectif de fin de V1 (fin Sprint 8) : démontrer le parcours complet
> `Candidat → Entreprise → Besoin → Matching → Admission → Contrat → OPCO → Dashboard`
> **sans passer par des fichiers externes**.

## V1 — P0 (Sprints 0 → 8)

| Sprint | Goal | Epics | Démontrable à la review |
|---|---|---|---|
| **S0** | Socle technique prêt | EPIC-00 | Connexion à une app Laravel/Filament vide qui tourne |
| **S1** | Authentification & rôles | EPIC-01 | Un utilisateur se connecte et ne voit que ses modules |
| **S2** | Candidats | EPIC-02 | Créer/filtrer/consulter un candidat, règles de statut |
| **S3** | Entreprises + Besoins | EPIC-03, EPIC-04 | Créer une entreprise avec contacts/tuteurs + un besoin |
| **S4** | Documents (GED) + Matching | EPIC-06, EPIC-05 | Upload versionné + proposer un candidat à un besoin |
| **S5** | Admission | EPIC-07 | Checklist pièces, conformité, valider/refuser un dossier |
| **S6** | Contrats | EPIC-08 | Créer un dossier contrat, statut signature, doc signé |
| **S7** | OPCO | EPIC-09 | Suivre dépôt, simuler rejet → correction, dossier bloqué |
| **S8** | Tâches/Alertes + Historique + Dashboard | EPIC-10, EPIC-11, EPIC-12, EPIC-13 | **Démo V1 complète** (voir ci-dessous) |

### Démo cible fin de V1 (parcours de bout en bout)

1. Créer un candidat → 2. Créer une entreprise → 3. Créer un besoin →
4. Proposer le candidat → 5. Marquer accepté → 6. Ouvrir l'admission →
7. Ajouter les pièces obligatoires → 8. Valider l'admission →
9. Créer le contrat → 10. Marquer signé → 11. Créer le dossier OPCO →
12. Simuler un rejet puis une correction →
13. Voir le dossier OPCO (bloqué/accepté) dans le dashboard →
14. Voir les tâches prioritaires et l'historique du dossier.

## V2 — P1 (Sprints 9 → 12)

| Sprint | Goal | Epics |
|---|---|---|
| **S9** | Scolarité & assiduité | EPIC-14 |
| **S10** | Service fait + Finance simple | EPIC-15, EPIC-16 |
| **S11** | Qualité & preuves | EPIC-17 |
| **S12** | Ruptures + Exports + dashboards enrichis | EPIC-18, EPIC-19 |

## V3+ — P2 (backlog, non planifié)

Portails externes, automatisations, génération documentaire avancée,
connecteurs (OPCO/DECA, compta), reporting réglementaire, IA d'aide à la
décision, analyse prédictive, multi-campus, **RH**, **Marketing**.
→ Découpés en stories quand ils entreront dans un sprint.

## Suivi de sprint

Pour chaque sprint actif, créer `sprints/sprint-XX.md` avec :
- Sprint Goal
- Stories engagées (ID, pts, assigné à)
- Burndown / avancement
- Notes de review + rétrospective
