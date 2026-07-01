# Sprint 06 — OPCO (EPIC-09)

**Durée :** 1 semaine · **Branche :** `feature/opco` → `develop`

## 🎯 Sprint Goal

Sécuriser le **financement** : suivi du dossier OPCO piloté par la machine à
états, avec les gardes métier (dépôt conditionné au contrat signé, rejet motivé)
et la **création automatique d'une action corrective** en cas de rejet.

## 🧩 S'appuie sur l'existant

- **Socle machine à états** : `OpcoStatut` (Non créé → … → Clôturé) + `OpcoFile`
  utilise `ManagesState`. `OpcoStatut::bloques()` liste déjà Rejeté / En correction.
- **Sprint Contrats (05)** : le dossier OPCO est déjà **créé automatiquement** à
  la transmission d'un contrat signé.

## 📦 Livré (par user story)

| Story | Livré |
|---|---|
| P0-09-1 Créer un dossier OPCO lié au contrat | Formulaire (OPCO, montants, dates) ; création aussi automatique depuis le contrat |
| P0-09-2 Suivre dépôt & retour + montant accepté | Action **« Enregistrer l'acceptation »** (saisie du montant accepté) ; suivi des dates |
| P0-09-3 Motif de rejet + action corrective auto | Action **« Enregistrer un rejet »** (motif obligatoire + responsable) → **tâche corrective créée automatiquement** (`source = auto`, priorité haute, échéance +7 j) |
| P0-09-4 Relances & preuves | Champs de suivi (date de relance, commentaire interne) ; documents rattachables via la GED |
| P0-09-5 Dossiers bloqués (direction) | Filtre **« Dossiers bloqués »** (Rejeté / En correction) dans la liste |
| P0-09-6 Gardes système | `guardTransition()` : pas de **« Prêt au dépôt »** si contrat non signé ; **rejet** impossible sans motif |

## 🔒 Robustesse

- `statut` piloté exclusivement par la machine à états (plus de `Select` libre).
- Actions dédiées **Prêt au dépôt / Acceptation / Rejet** qui affichent le motif
  de blocage si une garde s'y oppose.
- `LogsActivity` (statut, montants, motif de rejet) pour la traçabilité.

## 🧪 Tests

`tests/Feature/OpcoWorkflowTest.php` (7 cas) : blocage dépôt si contrat non
signé · dépôt autorisé si signé · rejet exige un motif · création automatique de
l'action corrective · statuts bloqués · refus des transitions interdites · gating
d'accès. **Suite complète : 66 tests / 143 assertions.**

## ✅ Définition de "Terminé"

- [x] Gardes métier (dépôt / rejet) au niveau modèle
- [x] Action corrective automatique au rejet
- [x] Acceptation avec montant + suivi
- [x] Vue des dossiers bloqués (direction)
- [x] Statut piloté par la machine à états
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## 🎉 Parcours P0 quasi complet

`Candidat → Entreprise → Besoin → Matching → Documents → Admission → Contrat →
OPCO` sont désormais fonctionnels et **enchaînés** (admission validée, contrat
signé, dossier OPCO ouvert et suivi). Reste **Tâches/Alertes**, **Historique** et
**Dashboards** (EPIC-10/11/12) pour la démo V1 complète.
