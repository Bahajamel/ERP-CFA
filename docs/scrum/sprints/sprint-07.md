# Sprint 07 — Parcours de l'apprenant & vision CFA

**Durée :** 1 semaine · **Branche :** `feature/parcours-apprenant` → `develop`
**Origine :** retours d'audit (compréhension du cycle de vie + ergonomie).

## 🎯 Sprint Goal

Rendre le **cycle de vie de l'apprenti lisible et réaliste** : une vue 360°
unique, une notion de **classe/promotion**, une checklist d'admission conforme
aux usages CFA, et des listes déroulantes qui tiennent à l'échelle (250+).

## 📦 Livré

### 1. Vue 360° « Parcours de l'apprenant »
- Nouvelle page **Voir** sur la fiche apprenti : un écran qui rassemble les
  **4 phases** du cycle de vie —
  **Identité/scolarité → 1·Admission → 2·Entreprise & contrat → 3·Financement OPCO → Documents & notes**.
- Chaque phase montre l'état réel (statut d'admission, pièces manquantes,
  entreprise, statut contrat, dossier OPCO, montants…).
- Relation manager **Documents** sur l'apprenti (upload direct des pièces).

### 2. Classe / Promotion (nouveau module)
- Table `promotions` (formation, libellé, année scolaire, dates) + rattachement
  `candidates.promotion_id`.
- `PromotionResource` (groupe Référentiels) + relation manager **Apprentis**
  avec **recherche** pour rattacher un apprenti (`AssociateAction`).
- Champ **Classe / Promotion** ajouté au formulaire apprenti.

### 3. Checklist d'admission réaliste (vision CFA)
- Nouveaux types de documents : **Pièce d'identité**, **Diplômes / bulletins**.
- Pièces obligatoires d'admission = **Pièce d'identité + CV candidat +
  Diplômes/bulletins** (le CV du maître d'apprentissage relève du contrat, pas
  de l'admission).

### 4. Ergonomie des listes déroulantes (échelle 250+)
- Retrait de `->preload()` sur les grosses entités (candidat, entreprise,
  contrat, besoin, contacts) → **recherche côté serveur** au lieu de charger
  toute la liste.
- Recherche candidat par **nom + prénom**.
- `preload` conservé uniquement sur les petits référentiels (formation, OPCO,
  utilisateurs, classes).

## 🧪 Tests

`tests/Feature/ParcoursApprenantTest.php` (4 cas) : création d'une classe +
relation formation · rattachement d'apprentis · gating du référentiel classes ·
checklist d'admission réaliste. **Suite complète : 70 tests / 153 assertions.**

## 🗃️ Migration

- `promotions` (nouvelle table) + `candidates.promotion_id` (additive).

## ✅ Définition de "Terminé"

- [x] Vue 360° parcours sur la fiche apprenti
- [x] Module Classe/Promotion + rattachement des apprentis
- [x] Checklist d'admission conforme CFA
- [x] Listes déroulantes scalables (recherche serveur)
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## ➡️ Suite possible

- **Timeline d'activité** (activitylog) dans la vue 360°.
- Notion de **session/assiduité** (sprint Scolarité) reliée à la classe.
- Tableau de bord direction (parcours global, KPI).
