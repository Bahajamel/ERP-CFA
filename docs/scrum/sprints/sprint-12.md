# Sprint 12 — Registre Qualiopi (preuves) (EPIC-13)

**Durée :** 1 semaine · **Branche :** `feature/qualiopi` → `develop`
**Origine :** argument commercial fort — la conformité Qualiopi est obligatoire
pour tout CFA financé ; nos concurrents la traitent hors-outil (Excel + dossiers).

## 🎯 Sprint Goal

Rendre le CFA **audit-ready en continu** : un registre vivant des 7 critères /
32 indicateurs du RNQ, où chaque exigence porte son statut de conformité, son
responsable et ses **preuves** — au lieu du sprint de panique avant l'audit.

## 📦 Livré

### Référentiel RNQ (seedé)
Les **32 indicateurs** répartis sur les **7 critères** du Référentiel National
Qualité, dont **11 portant une obligation spécifique CFA** (à valider en interne).
Seeder **idempotent** : met à jour les libellés sans écraser l'état saisi.

### Registre de conformité (`QualiopiIndicatorResource`)
- Liste **groupée par critère**, statut de conformité (Conforme / Non conforme /
  À vérifier / Non applicable), responsable, nombre de preuves, dernière revue.
- Filtres statut / critère / spécifique CFA.
- Édition : statut, responsable, date de revue, plan d'action.
- **Badge de navigation** = nombre d'indicateurs non conformes (rouge).
- **Historique journalisé** (activity log) — traçabilité en cas d'audit.

### Preuves via la GED
`PreuvesRelationManager` : rattache des documents (medialibrary) à chaque
indicateur — réutilise la GED existante, aucune duplication.

### Pilotage & proactivité
- **Widget « Conformité Qualiopi »** (Direction, Qualité, Administrateur) :
  taux de conformité (hors non-applicables), conformes, non conformes, à vérifier
  — cartes cliquables.
- **Alerte automatique** : une tâche par indicateur **non conforme**, assignée à
  son responsable (préparation de l'audit en continu).

## 🧪 Tests

`tests/Feature/QualiopiTest.php` (7 cas) : chargement des 32 indicateurs /
7 critères · idempotence + préservation de l'état · calcul du taux (hors N/A) ·
cas 0 % · alerte non-conformité · cadrage resource et widget par rôle.
**Suite complète : 124 tests / 265 assertions.**

## 🗃️ Migration & seeder

- Table `qualiopi_indicators` (référence + état de conformité + responsable).
- `QualiopiIndicatorSeeder` branché dans `DatabaseSeeder`.

## ✅ Définition de "Terminé"

- [x] Référentiel des 32 indicateurs (seedé, idempotent)
- [x] Registre de conformité (statut, responsable, revue, plan d'action)
- [x] Preuves rattachées via la GED
- [x] Widget taux de conformité + alerte non-conformités
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## 🏆 Impact concurrentiel

Là où les concurrents laissent la conformité hors-outil, l'ERP en fait un
**processus vivant** : conformité mesurée en temps réel, preuves centralisées,
non-conformités transformées en tâches. Argument de vente direct auprès des
directions de CFA. Prochaine brique : brancher les **enquêtes de satisfaction**
(critère 7) et l'**assiduité** (critère 3) sur les indicateurs.
