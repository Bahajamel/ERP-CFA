# Sprint 03 — Documents / GED (EPIC-06)

**Durée :** 1 semaine · **Branche :** `feature/documents` → `develop`
**Équipe :** nous (module indépendant, aucune dépendance de développement)

## 🎯 Sprint Goal

Une **gestion électronique de documents** robuste : importer un fichier, le
rattacher à n'importe quel dossier, le **versionner**, le retrouver, le
télécharger — avec **traçabilité** et **protection des documents critiques**.

## 🏗️ Choix techniques (chemin robuste)

- **Stockage : `spatie/medialibrary`** (plugin Filament officiel installé) — API
  propre, fichier unique par document, disque `public`.
- **Versioning par chaînage** : « remplacer » ne modifie jamais le fichier
  existant ; il crée une **nouvelle ligne** (`version = n+1`,
  `previous_version_id = ancien`). La version courante = celle qu'aucune autre ne
  remplace (scope `versionsCourantes`).
- **Traçabilité** : `LogsActivity` sur chaque dépôt / remplacement / archivage.
- **Anti-suppression sans trace** : garde au niveau **modèle** (event `deleting`)
  — un document critique (Contrat, CERFA, Convention) ne peut pas être supprimé
  définitivement, seulement archivé (soft delete).

## 📦 Livré (par user story)

| Story | Livré |
|---|---|
| P0-06-1 Import + rattachement polymorphe | `DocumentResource` (GED globale) : formulaire avec choix du **type de dossier** (Candidat / Entreprise / Contrat / Dossier OPCO) + du **dossier concerné** (liste dépendante) + upload medialibrary |
| P0-06-2 Type & statut | `type` (12 types) et `statut` (En attente / Reçu / Expiré) en badges, éditables |
| P0-06-3 Versioning | Action **« Remplacer »** → nouvelle version chaînée ; filtre **« Versions courantes »** (par défaut) vs toutes ; `historiqueVersions()` |
| P0-06-4 Recherche / téléchargement / traçabilité | Table filtrable (type, statut, type de dossier), recherche, action **Télécharger**, colonnes **déposé par** + **date**, journalisation |
| P0-06-5 Anti-suppression | Blocage `forceDelete` des types critiques (modèle) + archivage soft delete |

## 🧪 Tests

`tests/Feature/DocumentsTest.php` (8 cas) : gating d'accès `access_documents` ·
versioning + bascule de version courante · scope versions courantes · historique ·
interdiction force-delete critique · archivage autorisé · force-delete non
critique · attachement medialibrary. **Suite complète : 40 tests / 92 assertions.**

## ✅ Définition de "Terminé"

- [x] Upload réel des fichiers (medialibrary) + téléchargement
- [x] Rattachement polymorphe (4 types de dossiers)
- [x] Versioning fonctionnel + vue versions courantes
- [x] Traçabilité (activitylog) + garde anti-suppression
- [x] Accès limité au rôle (`access_documents` : Admission, Administratif, Qualité, Direction)
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## 🔭 Suite possible (hors périmètre assumé)

- **`DocumentsRelationManager` générique** à brancher sur les fiches
  Candidat / Entreprise / Contrat (vue 360°) — prêt à poser une fois ces
  modules avancés, sans toucher au travail en cours du collègue.
- Colonne « aperçu » / conversions medialibrary (miniatures) si besoin.
