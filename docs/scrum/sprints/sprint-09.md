# Sprint 09 — Tâches & Alertes (EPIC-10)

**Durée :** 1 semaine · **Branche :** `feature/taches-alertes` → `develop`
**Origine :** audit concurrentiel (priorité 2) — l'antidote aux « ruptures de process ».

## 🎯 Sprint Goal

Transformer l'ERP passif en **copilote** : un moteur d'**alertes proactives** qui
détecte les situations à risque, crée des tâches et **notifie in-app** la bonne
personne — avant que ça casse.

## 📦 Livré

### Moteur d'alertes (`AlerteService`)
Génère des tâches automatiques (idempotentes via une **clé unique** — pas de
doublon), avec **notification in-app** à la personne concernée :

| Condition détectée | Alerte |
|---|---|
| Dossier d'admission incomplet (pièces obligatoires manquantes) | → tâche au commercial |
| Contrat envoyé pour signature (non signé) | → tâche de relance |
| Dossier OPCO **sans retour depuis 30 j** | → tâche au responsable |
| **Échéance de versement OPCO à venir** (≤ 7 j) | → tâche au responsable |
| Tâches dont l'échéance est dépassée | → passées **En retard** |

### Notifications in-app
- **Cloche Filament** activée (`databaseNotifications`, polling 30 s) → les
  alertes arrivent en temps quasi réel dans l'interface.

### Centre de tâches (TaskResource)
- Action **« Terminer »** ; colonne échéance **en rouge** si dépassée.
- Filtres **« Mes tâches »** et **« En retard »** ; badge **Origine** (auto/manuelle).
- **Badge de navigation** = mes tâches ouvertes (rouge si retard).

### Planification
- `app:generer-alertes` (quotidien 06:15) + `opco:flag-echeances` (06:00).

## 🧪 Tests

`tests/Feature/AlerteServiceTest.php` (7 cas) : alerte admission incomplète +
idempotence · contrat à signer · OPCO sans retour · échéance à venir · bascule
des tâches en retard · notification in-app · exécution de la commande.
**Suite complète : 83 tests / 175 assertions.**

## 🗃️ Migration

- Table `notifications` (in-app) + `tasks.cle` (clé anti-doublon des alertes).

## ✅ Définition de "Terminé"

- [x] Génération automatique des alertes clés (idempotente)
- [x] Notifications in-app (cloche)
- [x] Centre de tâches (terminer, filtres, badge)
- [x] Planification quotidienne
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## 🏆 Impact concurrentiel

Répond frontalement à la faiblesse d'Ypareo (« ruptures de process entre
administration, formateurs et entreprises ») : l'ERP **anticipe** au lieu de
subir. Prochaine brique intelligence : **Dashboards par rôle** (KPI cliquables)
et **détection du risque de rupture**.
