# ERP CFA — Organisation Scrum

Ce dossier contient le pilotage du projet en **méthode Scrum**, sous forme de
fichiers Markdown versionnés dans le dépôt (source unique de vérité, à côté du
code).

## Vision produit

Construire une **plateforme unique** (ERP) pour le CFA, qui remplace la multitude
de logiciels coûteux et de fichiers Excel dispersés. Tous les départements
(commercial, admission, administratif, scolarité, pédagogie, finance, qualité,
direction) travaillent **au même endroit**, autour d'un objet central : le
**cycle de vie de l'apprenant**, du premier contact jusqu'à la sortie de
formation.

> Objectif V1 : ne pas livrer tout le cahier des charges d'un coup, mais rendre
> **solide le parcours principal** :
> `Candidat → Entreprise → Besoin → Matching → Admission → Contrat → OPCO → Dashboard`.

## Documents de référence

- `../../CDC_ERP_CFA_v2.pdf` — Cahier des charges fonctionnel et technique.
- `../../Organisation_travail_ERP_CFA.pdf` — Organisation Git et répartition.
- `product-backlog.md` — **Le backlog produit complet** (epics + user stories).
- `sprint-roadmap.md` — Découpage en sprints d'une semaine.
- `epics/` — Détail par epic (au fur et à mesure des sprints).

## Stack technique (imposée par le CDC)

- **Backend** : Laravel (PHP)
- **UI métier / admin** : Filament (Livewire)
- **Base de données** : PostgreSQL
- **RBAC** : `spatie/laravel-permission`
- **Journalisation** : `spatie/laravel-activitylog`
- **GED versionnée** : `spatie/laravel-medialibrary`
- **Fichiers** : object storage S3 (UE) · **Queues** : Redis · **Erreurs** : Sentry
- **Tests** : Pest · **CI/CD** : GitHub Actions · 3 environnements (dev / recette / prod)

**Principes transversaux** : relations **polymorphes** (documents, tâches,
preuves, journal) + **machine à états explicite** par module.

## Rôles Scrum

| Rôle | Qui | Responsabilité |
|---|---|---|
| **Product Owner** | *(à définir)* | Priorise le backlog, valide les incréments |
| **Scrum Master** | *(à définir)* | Garant du process, lève les blocages |
| **Dev 1** | *(à définir)* | Plutôt modèle / règles métier / statuts / validations |
| **Dev 2** | *(à définir)* | Plutôt interface / écrans / filtres / affichage |

> Répartition Dev1/Dev2 indicative (voir `Organisation_travail_ERP_CFA.pdf` §5),
> elle doit rester souple sans bloquer l'autre.

## Cadence et cérémonies (sprint = 1 semaine)

| Cérémonie | Quand | Durée cible | But |
|---|---|---|---|
| **Sprint Planning** | Lundi matin | 1 h | Choisir le Sprint Goal et les stories |
| **Daily** | Chaque jour | 10 min | Synchroniser, signaler les blocages |
| **Sprint Review** | Vendredi | 30 min | Démontrer l'incrément |
| **Rétrospective** | Vendredi | 20 min | Améliorer la méthode |

## Git (rappel — voir doc d'organisation)

- `main` : stable / démontrable uniquement.
- `develop` : intégration des features validées.
- `feature/*` : une branche par story/feature, PR relue par le coéquipier.
- **Jamais de merge direct dans `main`.**

## Estimation

User stories estimées en **points (suite de Fibonacci)** : 1, 2, 3, 5, 8, 13.
Au-delà de 8 → la story est trop grosse, il faut la découper.

## Definition of Done (DoD)

Une story est **terminée** seulement si :

- [ ] La feature fonctionne localement.
- [ ] Les migrations / changements de base de données sont inclus.
- [ ] Les **règles métier** principales sont respectées (cf. CDC).
- [ ] Les **statuts** et transitions autorisés sont contrôlés (machine à états).
- [ ] Les **rôles et permissions** sont vérifiés si la donnée est sensible.
- [ ] Les **actions importantes sont journalisées** si concerné.
- [ ] La **page liste** et la **page détail** fonctionnent (si la story a une UI).
- [ ] Les erreurs utilisateur sont compréhensibles.
- [ ] Test(s) Pest sur la règle métier critique (si applicable).
- [ ] Aucune donnée de test inutile laissée dans le code.
- [ ] Nom de commit clair + PR expliquant quoi a été ajouté et comment tester.
- [ ] PR relue et mergée dans `develop`, `develop` testé après merge.
