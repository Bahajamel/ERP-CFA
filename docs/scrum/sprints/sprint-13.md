# Sprint 13 — Dossier de rupture (EPIC-18)

**Durée :** 1 semaine · **Branche :** `feature/dossier-rupture` → `develop`
**Origine :** compléter le différenciateur phare. On **détectait** déjà le risque
de rupture (Sprint 11) ; il manquait la **gestion du cas** une fois la rupture
engagée. Les concurrents s'arrêtent au constat — on va jusqu'au reclassement.

## 🎯 Sprint Goal

Passer de l'**anticipation** à l'**action** : quand une rupture survient, ouvrir
un dossier qui trace le motif, propage la rupture au financement (OPCO + Finance),
pilote l'accompagnement de l'apprenti vers un **nouvel employeur** (reclassement,
art. L6222-18-2) et conserve les preuves — au lieu d'une rupture qui se perd en
Excel et fait fuir le financement.

## 📦 Livré

### Modèle & machine à états
`RuptureCase` (table `rupture_cases`, 1—1 avec le contrat, soft delete, activity
log). Cycle de vie piloté par `RuptureStatut` : **Ouvert → Accompagnement →
Reclassé → Clos**. La clôture est horodatée automatiquement ; l'accompagnement
active la recherche d'un nouvel employeur. Motif via `RuptureMotif` (10 cas
réglementaires), origine via `RuptureInitiateur`.

### Orchestration de la rupture (`RuptureService`)
Ouvrir un dossier n'est pas un simple insert — c'est le point de **propagation** :
- le **contrat** passe à « Rompu » (machine à états) ;
- une **tâche de régularisation OPCO** est créée si un dossier OPCO est en cours
  (hors clôturé) ;
- une **tâche de régularisation Finance** est créée si des lignes financières
  existent (arrêt de facturation, blocage des montants avec motif « rupture »).

Idempotent (un dossier par contrat, tâches à clé unique `rupture:opco:{id}` /
`rupture:finance:{id}`). Un passage direct du contrat à « Rompu » crée aussi un
**dossier brouillon** (`Contract::afterTransition`), pour qu'aucune rupture ne
reste sans trace.

### Interface
- **Resource « Dossiers de rupture »** (nav *Contrats & OPCO*, gated
  `access_ruptures`, badge rouge = dossiers ouverts) : formulaire (contrat, motif,
  initiative, accompagnement, reclassement), table filtrable, actions de workflow
  (*Faire évoluer*, *Acter le reclassement*, *Clore*), preuves via la GED.
- **Action « Ouvrir un dossier de rupture »** directement sur le contrat (table +
  édition), visible pour un contrat engagé sans dossier.
- **Widget « Ruptures & reclassement »** (Direction, Pédagogie, Administratif,
  Administrateur) : dossiers ouverts, accompagnements en cours, **taux de
  reclassement** (argument Qualiopi critère 6).

### Accès
`ruptures` accordé à **Administratif** (cascade OPCO/Finance) en plus de
Pédagogie / Direction / Administrateur.

## 🧪 Tests

`tests/Feature/RuptureCaseTest.php` (13 cas) : ouverture + passage à Rompu ·
idempotence · cascade OPCO (et non-cascade si clôturé) · cascade Finance ·
dossier brouillon sur transition directe · machine à états complète + horodatage ·
journal d'accompagnement · transition invalide refusée · taux de reclassement ·
gating resource & widget par rôle · **rendu Livewire des 3 pages**.
**Suite complète : 278 tests verts.**

## 🗃️ Migration

- Table `rupture_cases` (contrat, date, motif, initiateur, statut, accompagnement,
  recherche_employeur, nouvelle_company_id, responsable_id, date_cloture).

## ✅ Définition de "Terminé"

- [x] Modèle + machine à états du dossier
- [x] Service d'orchestration (contrat → Rompu, cascade OPCO + Finance)
- [x] Resource Filament + action sur le contrat + widget dashboard
- [x] Preuves rattachées via la GED
- [x] Tests verts (module + suite complète + rendu des pages)
- [ ] Mergé sur `develop`

## 🏆 Impact concurrentiel

Là où Ypareo constate la rupture et laisse les équipes régulariser à la main,
l'ERP **propage la rupture** (financement sécurisé), **accompagne** l'apprenti et
**mesure le reclassement**. La rupture devient un processus piloté, pas un trou
dans le dossier. Prochaine brique : brancher l'ouverture d'un dossier rupture sur
le moteur d'alertes (risque Critique persistant → suggestion d'ouverture).
