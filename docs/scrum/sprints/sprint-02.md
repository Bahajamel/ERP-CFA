# Sprint 02 — Socle de données partagé

**Durée :** 1 semaine · **Branche :** `feature/data-foundation` → `develop`

## 🎯 Sprint Goal

Poser et **figer** le socle de données commun pour que les modules deviennent
**indépendants** : chacun pourra ensuite être développé en parallèle, sur sa
propre branche, sans attendre l'écran d'un autre.

> C'est le **seul prérequis commun** du nouveau mode de travail (voir
> [sprint-roadmap.md](../sprint-roadmap.md)). Une fois ce sprint mergé sur
> `develop`, le travail parallèle commence.

## 📦 Périmètre livré

| Élément | Détail |
|---|---|
| **Trait `HasFactory`** | Ajouté aux 13 modèles métier (Candidate, Company, CompanyContact, Need, Matching, Document, Admission, AdmissionChecklistItem, Contract, OpcoFile, Task, Formation, Opco) |
| **Factories réalistes (FR)** | Une factory par entité, données crédibles (noms FR, SIRET, formations CFA, OPCO réels, statuts via enums). États utiles : `Candidate::factory()->sansContact()`, `CompanyContact::factory()->principal()/->tuteur()` |
| **Composant partagé `notes`** | Table polymorphe `notes` (notable) + modèle `Note` + factory + relation `notes()` câblée sur Candidate, Company, Need (requis par P0-02-4 et P0-03-5) |
| **Test du socle** | `tests/Feature/DataFoundationTest.php` : chaque factory produit un enregistrement valide ; les relations clés (dont polymorphes) se résolvent |

## ✅ Définition de "Terminé"

- [x] `HasFactory` sur tous les modèles métier
- [x] Une factory fonctionnelle par entité
- [x] Table `notes` + modèle + relations
- [x] Tests verts (`DataFoundationTest` : 20 cas) + suite complète (32 tests)
- [x] Migration `notes` appliquée (dev) ; `migrate` rejouable
- [ ] Mergé sur `develop` et collègue prévenu de repartir de `develop` à jour

## 🧱 État du socle existant (audit d'entrée)

Le socle était déjà **très complet** (travail prototype) : toutes les tables P0,
modèles, relations, enums de statuts, casts, soft-deletes et activitylog étaient
en place et conformes au [modèle de données](../architecture/modele-de-donnees.md).
L'écart portait **uniquement** sur les factories (absentes) et le composant
`notes` (manquant) — d'où le périmètre ciblé de ce sprint.

## 🔭 Hors périmètre (assumé)

- **Table `sessions`** (groupes/promotions) : reportée au sprint **Assiduité**
  (P1) — aucun module P0 n'en dépend.
- **Intégration medialibrary** des documents (stockage réel des fichiers) :
  traitée dans le sprint **Documents / GED** (EPIC-06). Le socle expose
  `nom_fichier` / `chemin` en attendant.

## ➡️ Suite

Le socle figé, on enchaîne sur les **modules indépendants** (ordre libre).
Côté équipe « nous » : cap sur **Matching** ou **Documents (GED)**.
Côté collègue : **Candidats** et **Entreprises + Besoins**.
**Règle d'or** : après ce sprint, on ne modifie plus le schéma sauf migration
**additive** annoncée sur `develop`.
