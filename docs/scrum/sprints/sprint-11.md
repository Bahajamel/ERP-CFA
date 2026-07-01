# Sprint 11 — Détection du risque de rupture (EPIC-12)

**Durée :** 1 semaine · **Branche :** `feature/detection-rupture` → `develop`
**Origine :** audit concurrentiel (priorité 1) — la faiblesse la plus citée
d'Ypareo : « on constate les ruptures, on ne les anticipe pas ».

## 🎯 Sprint Goal

Passer du **constat** à l'**anticipation** : un moteur qui score le risque de
rupture de chaque apprenti engagé, l'explique, alerte l'équipe et le rend
visible — pour intervenir **avant** la rupture.

## 📦 Livré

### Moteur de scoring (`RuptureRiskService`)
Score **0–100** par contrat en cours, somme de facteurs pondérés, traduit en
niveau (`RiskLevel` : Faible / Modéré / Élevé / Critique).

| Signal détecté | Poids | Logique métier |
|---|---|---|
| Période d'essai en cours (≤ 45 j) | 20 | Rupture libre légalement possible |
| Aucun maître d'apprentissage désigné | 25 | Sans tuteur, l'alternance déraille |
| Contrat démarré mais non signé | 30 | Faille administrative majeure |
| Financement OPCO bloqué (rejeté / correction) | 25 | Risque de rupture financière |
| Dossier OPCO non engagé (contrat actif) | 15 | Retard de sécurisation du financement |

**Architecture extensible** : chaque signal est un détecteur indépendant.
Brancher l'absentéisme (futur module assiduité) = ajouter un détecteur, le
reste (score, alertes, dashboard) suit automatiquement.

### Instantané persistant
Colonnes `risk_score` / `risk_level` / `risk_factors` / `risk_evaluated_at` sur
`contracts`, recalculées par `app:evaluer-risques` (planifié à 05:45, avant les
alertes). Permet tri, filtre et affichage sans surcoût par requête.

### Alertes proactives
`AlerteService` crée une tâche (idempotente) dès qu'un contrat passe en risque
**Élevé/Critique**, priorisée selon le niveau, assignée au commercial qui suit
l'apprenti, avec notification in-app.

### Interface
- **Widget dashboard « Apprentis à risque de rupture »** (Direction, Pédagogie,
  Administrateur) : score, niveau, facteurs déclencheurs, lien vers le contrat.
- **Colonne + filtre « Risque rupture »** sur la table des contrats, avec les
  facteurs en infobulle.

## 🧪 Tests

`tests/Feature/RuptureRiskTest.php` (11 cas) : seuils de score · contrat sain ·
chaque détecteur · cumul jusqu'à critique · `evaluerTous` (persistance + compte)
· alerte auto · commande · cadrage du widget par rôle.
**Suite complète : 111 tests / 245 assertions.**

## 🗃️ Migration

- `contracts` : `risk_score`, `risk_level`, `risk_factors` (json), `risk_evaluated_at`.

## ✅ Définition de "Terminé"

- [x] Moteur de scoring pondéré + niveaux
- [x] Instantané persistant + commande planifiée
- [x] Alertes automatiques (élevé / critique)
- [x] Widget dashboard + colonne/filtre contrats
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## 🏆 Impact concurrentiel

Là où les concurrents affichent des listes, l'ERP **anticipe la rupture** et dit
*pourquoi* un dossier est à risque et *qui* doit agir. Prochaine brique :
brancher l'**absentéisme** (module assiduité) sur le moteur, et le module
**Qualiopi (preuves)**.
