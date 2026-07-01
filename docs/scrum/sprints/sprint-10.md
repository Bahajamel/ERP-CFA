# Sprint 10 — Dashboard Direction / Pilotage (EPIC-11)

**Durée :** 1 semaine · **Branche :** `feature/dashboard-direction` → `develop`
**Origine :** audit concurrentiel (priorité 1) — « KPI cliquables » et lecture
directionnelle instantanée, là où Ypareo/Dendreo restent des listes brutes.

## 🎯 Sprint Goal

Donner à la **direction** une lecture du CFA **en un coup d'œil** : les 6
indicateurs clés cliquables, l'entonnoir de conversion et la trésorerie OPCO —
pour piloter par la donnée, pas par le ressenti.

## 📦 Livré

### KPI cliquables (`DirectionStatsOverview`)
6 cartes, chacune **cliquable** → renvoie vers la liste filtrée correspondante :

| Indicateur | Cible du clic |
|---|---|
| Candidats actifs (hors ruptures) | Liste candidats |
| Besoins ouverts | Liste besoins |
| Contrats signés / transmis / actifs | Liste contrats |
| Dossiers OPCO bloqués (rouge si > 0) | Dossiers OPCO |
| **Financement OPCO encaissé** (+ total attendu) | Dossiers OPCO |
| Tâches ouvertes | Centre de tâches |

### Entonnoir de conversion (`ConversionFunnelChart`)
Bar chart **Candidats → Admissions validées → Contrats signés → OPCO acceptés** :
montre instantanément **où fuit le tunnel** (quelle étape perd le plus de dossiers).

### Trésorerie OPCO (`FinancementChart`)
Doughnut de la répartition des échéances de versement : **encaissé / en retard /
à venir** — la vision cash-flow du financement de l'alternance.

### Tables opérationnelles (déjà en place, désormais cadrées Direction)
- **Dossiers OPCO bloqués** (rejetés / en correction) avec motif et responsable.
- **Tâches prioritaires** (tri urgence puis échéance).

### Cadrage par rôle
Tous les widgets de pilotage sont **réservés à `Direction` et `Administrateur`**
(`canView()`), pour que le tableau de bord reste une vue de direction et non un
fourre-tout affiché à tous les métiers.

## 🧪 Tests

`tests/Feature/DashboardDirectionTest.php` (12 cas) : cadrage `canView` pour les
5 widgets (Direction/Admin voient, Commercial/Formateur non) · rendu du
StatsOverview (indicateurs visibles) · rendu funnel + trésorerie sans erreur.
**Suite complète : 95 tests / 200 assertions.**

## 🗃️ Migration

Aucune — widgets de lecture uniquement, sur le socle de données existant.

## ✅ Définition de "Terminé"

- [x] KPI cliquables (6 cartes → listes filtrées)
- [x] Entonnoir de conversion
- [x] Trésorerie OPCO
- [x] Widgets réservés à la Direction
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## 🏆 Impact concurrentiel

Répond à la faiblesse commune des concurrents (« des listes, pas de pilotage ») :
la direction ouvre l'ERP et **voit** l'état du CFA — pipeline, financement,
blocages — sans construire un seul rapport. Prochaine brique intelligence :
**détection du risque de rupture** et **Qualiopi (preuves)**.
