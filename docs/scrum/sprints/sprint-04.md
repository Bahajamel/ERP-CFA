# Sprint 04 — Admission (EPIC-07)

**Durée :** 1 semaine · **Branche :** `feature/admission` → `develop`

## 🎯 Sprint Goal

Un dossier d'admission piloté par une **machine à états** avec la règle métier
clé du CDC : **impossible de valider tant qu'une pièce obligatoire manque ou est
non conforme** (P0-07-4).

## 🧩 S'appuie sur l'existant

- **Socle machine à états** du collègue (`App\StateMachine\ManagesState` +
  `DefinesTransitions`) : `AdmissionStatut` déclarait déjà ses transitions
  structurelles. Ce sprint ajoute la **couche métier** (gardes) et l'**UI de
  workflow**, jusqu'ici absentes.
- **Module Documents / GED** (sprint 03) : les pièces de la checklist référencent
  les mêmes `DocumentType`.

## 📦 Livré (par user story)

| Story | Livré |
|---|---|
| P0-07-1 Ouvrir un dossier rattaché à un candidat | Création avec **génération automatique de la checklist** des pièces obligatoires (`afterCreate`) ; candidat non modifiable ensuite |
| P0-07-2 Checklist des pièces avec état | `ItemsRelationManager` (présente / manquante / non conforme) + indicateur **conformité** dans la liste ; `genererChecklistObligatoire()` idempotent |
| P0-07-3 Valider / refuser + préparer le contrat | Actions **« Valider le dossier »** et **« Faire évoluer »** pilotées par `allowedTransitions()` ; métadonnées de validation (validé par / le) posées automatiquement |
| P0-07-4 Blocage validation si pièces manquantes | `guardTransition()` : la transition vers **Validé** est refusée tant que `estComplet()` est faux — vérifié au niveau **modèle** (donc aussi hors UI) |

## 🔒 Robustesse

- Le statut n'est **plus modifiable librement** via le formulaire : il évolue
  uniquement par des transitions validées (structure + gardes métier).
- Transitions refusées → exception `InvalidTransitionException` transformée en
  notification claire côté UI.
- `LogsActivity` sur l'admission (statut, validation, commentaire).

## 🧪 Tests

`tests/Feature/AdmissionWorkflowTest.php` (6 cas) : génération idempotente de la
checklist · blocage de validation si incomplet · validation autorisée quand
complet (+ métadonnées) · exclusion de « Validé » des transitions tant
qu'incomplet · refus des transitions structurellement interdites · gating
d'accès. **Suite complète : 46 tests / 110 assertions.**

## ✅ Définition de "Terminé"

- [x] Règle métier de validation (garde) au niveau modèle
- [x] Checklist obligatoire auto + relation manager
- [x] Actions de workflow (valider / faire évoluer / générer checklist)
- [x] Statut piloté exclusivement par la machine à états
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## ➡️ Suite

Enchaînement prévu : **Contrats (EPIC-08)** — même approche (machine à états
`ContractStatut` déjà présente) : garde « pas de *signé* sans document signé »,
transitions, et passage `Admission validée → Contrat`.
