# Sprint 05 — Contrats & conventions (EPIC-08)

**Durée :** 1 semaine · **Branche :** `feature/contracts` → `develop`

## 🎯 Sprint Goal

Un dossier contrat piloté par la **machine à états** (`statut_contrat`), avec la
règle métier clé : **pas de « Signé » sans preuve de signature** (P0-08-5), et le
**passage automatique au suivi OPCO** à la transmission (P0-08-4).

## 🧩 S'appuie sur l'existant

- **Socle machine à états** : `ContractStatut` déclarait déjà ses transitions
  (Brouillon → … → Actif / Rompu / Archivé) et `Contract` utilise `ManagesState`
  sur la colonne `statut_contrat`. Ce sprint ajoute la **couche métier** + l'UI.
- **Module Documents / GED** (sprint 03) : intégration directe — un document
  contractuel associé débloque la signature.

## 📦 Livré (par user story)

| Story | Livré |
|---|---|
| P0-08-1 Créer un dossier contrat | Formulaire complet (parties, formation, tuteur, dates, RNCP, rythme, lieu) |
| P0-08-2 Vérifier + gérer les documents | **Relation manager Documents** (upload medialibrary) rattaché au contrat |
| P0-08-3 Suivi du statut de signature | `statut_signature` éditable + statut du contrat piloté par le workflow |
| P0-08-4 Envoyer vers le suivi OPCO | Transition **Transmis OPCO** → **création automatique du dossier OPCO** (statut « À préparer ») |
| P0-08-5 Empêcher « Signé » sans preuve | `guardTransition()` : « Signé » exige un document contractuel **ou** une signature marquée signée ; toute évolution est tracée (`LogsActivity`) |

## 🔒 Robustesse

- Le `statut_contrat` n'est **plus modifiable librement** : il évolue via les
  actions **« Marquer signé »** et **« Faire évoluer »** (transitions validées).
- L'action « Marquer signé » reste visible même quand la garde bloque, et
  **affiche le motif** (« associez un document contractuel… ») au lieu de
  disparaître silencieusement.
- Atteindre « Signé » aligne automatiquement `statut_signature` sur *Signé*.

## 🧪 Tests

`tests/Feature/ContractWorkflowTest.php` (6 cas) : blocage « Signé » sans preuve ·
« Signé » autorisé via signature marquée · « Signé » autorisé via document
contractuel (+ alignement signature) · création auto du dossier OPCO à la
transmission · refus des transitions structurellement interdites · gating
d'accès. **Suite complète : 52 tests / 123 assertions.**

## 🗃️ Migration

- Additive : `contracts.commentaire` (nullable) — justification de signature /
  notes de transition. Aucune colonne existante modifiée.

## ✅ Définition de "Terminé"

- [x] Garde métier de signature (modèle)
- [x] Documents rattachés (GED) + upload
- [x] Passage automatique au dossier OPCO
- [x] Statut piloté exclusivement par la machine à états
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## ➡️ Suite

**OPCO (EPIC-09)** : le dossier est déjà créé automatiquement ici. Reste la garde
« pas de *Prêt au dépôt* si contrat non signé », le motif de rejet obligatoire +
action corrective, et le tableau des dossiers bloqués (direction).
