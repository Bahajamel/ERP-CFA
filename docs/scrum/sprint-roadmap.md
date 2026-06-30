# Sprint Roadmap — ERP CFA

**Sprint = 1 semaine.** Chaque sprint livre un **incrément démontrable**.

> 🎯 **Nouveau mode de travail : modules indépendants.**
> Les sprints ne s'enchaînent **plus** en dépendances strictes. Après le **socle
> de données partagé**, chacun prend **n'importe quel module, dans n'importe quel
> ordre**, sur sa propre branche, et on combine sur `develop`.

## ✅ Déjà livré

| Sprint | Goal | Epics | État |
|---|---|---|---|
| **S0** | Socle technique (Laravel/Filament/PostgreSQL, CI, packages) | EPIC-00 | ✅ Terminé |
| **S1** | Authentification, rôles & permissions | EPIC-01 | ✅ Terminé |

---

## 🔑 Comment les modules deviennent indépendants

On ne supprime pas les dépendances **fonctionnelles** (le matching utilisera
toujours les données candidat). On supprime les dépendances **de
développement** : personne n'attend l'écran d'un autre pour coder. Trois leviers :

1. **Socle de données posé une seule fois** — toutes les tables, colonnes,
   relations et statuts du [modèle de données](../architecture/modele-de-donnees.md)
   sont migrés sur `develop` **avant** de répartir les modules. Chacun code
   ensuite contre une base déjà complète et figée.
2. **Factories + seeders = fausses données** — le module Matching n'a pas besoin
   que l'écran Candidats soit fini ; il a besoin de candidats **en base**,
   fournis par les factories. Chaque module se teste isolément.
3. **Lecture via les relations Eloquent, jamais via le code en cours de l'autre**
   — couplage faible : un module lit `$need->candidates`, il n'appelle pas le
   contrôleur/écran de l'autre.

> **Règle anti-conflits n°1** : on ne modifie le schéma (`migrations`, modèles)
> **que** pendant la phase Socle, validée ensemble. Après, les sprints modules
> ne **lisent** plus que le schéma — ils ne le changent plus. Tout besoin de
> nouveau champ = petite migration additive dédiée, annoncée sur `develop`.

---

## 🧱 S2 — Socle de données partagé *(le seul prérequis commun)*

*Branche : `feature/data-foundation` · à merger sur `develop` en premier*

| Tâche | Détail |
|---|---|
| Migrations complètes | Toutes les tables du modèle de données (candidates, companies, company_contacts, needs, matchings, documents, admissions, contracts, opco_files, tasks, formations, opcos…) avec **tous** les champs et statuts |
| Relations Eloquent | 1—n, n—n (pivots), polymorphes (documents, tâches) posées sur les modèles |
| Factories | Une factory réaliste par entité (données crédibles : noms FR, SIRET, formations…) |
| Seeders de démo | Jeu de données cohérent de bout en bout pour tester chaque module isolément |
| Enums de statuts | Les machines à états figées (un enum/const par module) |

**Démontrable :** la base contient toutes les tables + un jeu de démo complet ;
`php artisan migrate:fresh --seed` repart proprement. **À partir d'ici, tout le
monde travaille en parallèle.**

> ⚠️ Une partie de ce socle existe déjà (la plateforme prototype du collègue a
> créé plusieurs tables/modèles). S2 consiste à **compléter et figer** ce socle
> selon le modèle de données, pas à repartir de zéro.

---

## 🚀 Sprints modules — indépendants & parallélisables

Chacun ne dépend **que du Socle**. Ordre ci-dessous purement indicatif : prends
celui que tu veux, quand tu veux. Plusieurs peuvent avancer en parallèle.

| Module | Epic | Branche | Dépend de | Démontrable |
|---|---|---|---|---|
| **Candidats** | EPIC-02 | `feature/candidates` | Socle | Créer/filtrer/consulter, règles de statut |
| **Entreprises + Besoins** | EPIC-03, EPIC-04 | `feature/companies`, `feature/needs` | Socle | Entreprise + contacts/tuteurs + besoin |
| **Matching** | EPIC-05 | `feature/matching` | Socle | Proposer un candidat à un besoin (lit candidats/besoins via relations) |
| **Documents (GED)** | EPIC-06 | `feature/documents` | Socle | Upload versionné, rattachement polymorphe |
| **Admission** | EPIC-07 | `feature/admission` | Socle | Checklist pièces, conformité, valider/refuser |
| **Contrats** | EPIC-08 | `feature/contracts` | Socle | Dossier contrat, statut signature, doc signé |
| **OPCO** | EPIC-09 | `feature/opco` | Socle | Dépôt, rejet → correction, dossier bloqué |
| **Tâches & alertes** | EPIC-10 | `feature/tasks-alerts` | Socle | Tâche assignée + alertes auto (polymorphe) |
| **Historique** | EPIC-11 | `feature/history` | Socle | Journal des actions par dossier |
| **Recherche & filtres** | EPIC-13 | `feature/search` | Socle | Recherche globale + filtres transversaux |
| **Dashboards par rôle** | EPIC-12 | `feature/dashboard` | Socle (+ modules pour des KPI réels) | KPI cliquables → listes filtrées |

> 💡 **Dashboards (EPIC-12)** : techniquement indépendant (lit la base), mais ses
> chiffres ne sont riches qu'une fois plusieurs modules remplis. À construire
> **incrémentalement** ou en fin de cycle — chaque KPI s'ajoute quand son module
> existe.

---

## 🔗 Phase d'intégration — Démo V1

Quand les modules P0 sont sur `develop`, on valide le **parcours complet de bout
en bout** (aucun fichier externe) :

1. Créer un candidat → 2. Créer une entreprise → 3. Créer un besoin →
4. Proposer le candidat → 5. Marquer accepté → 6. Ouvrir l'admission →
7. Ajouter les pièces obligatoires → 8. Valider l'admission →
9. Créer le contrat → 10. Marquer signé → 11. Créer le dossier OPCO →
12. Simuler un rejet puis une correction →
13. Voir le dossier OPCO dans le dashboard →
14. Voir les tâches prioritaires et l'historique du dossier.

---

## 🤝 Règles de collaboration (travail parallèle → `develop`)

1. **Une branche `feature/*` par module**, partant toujours de `develop` à jour.
2. **Intégration fréquente** : `git pull` / rebase sur `develop` souvent pour
   limiter les conflits ; PR vers `develop` (jamais directement sur `main`).
3. **Un seul propriétaire par fichier de modèle/migration** pendant un sprint :
   un module qui a besoin d'un champ supplémentaire crée une **migration
   additive** dédiée (jamais réécrire une migration existante d'un autre).
4. **Le schéma ne bouge plus hors phase Socle** — sinon ça casse les autres.
5. **`develop` = hors-prod** (intégration), **`main` = prod** (intouchée pour
   l'instant), branches `feature/*` = travail/backup.

---

## V2 — P1 (après la V1, mêmes règles d'indépendance)

| Module | Epic | Branche |
|---|---|---|
| Scolarité & assiduité | EPIC-14 | `feature/attendance` |
| Service fait | EPIC-15 | `feature/service-fait` |
| Finance simple | EPIC-16 | `feature/finance` |
| Qualité & preuves | EPIC-17 | `feature/quality` |
| Ruptures | EPIC-18 | `feature/ruptures` |
| Exports | EPIC-19 | `feature/exports` |

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
</content>
</invoke>
