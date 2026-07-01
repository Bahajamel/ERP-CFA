# Sprint 08 — Suivi des versements OPCO (décret 2025-585)

**Durée :** 1 semaine · **Branche :** `feature/opco-echeancier` → `develop`
**Origine :** audit concurrentiel — pilier « protection du financement ».

## 🎯 Sprint Goal

Suivre finement les **versements OPCO** selon le **décret n° 2025-585
(27/06/2025)** — un différenciateur rare face aux concurrents — et **alerter**
automatiquement en cas d'échéance en retard.

## 🎓 Logique métier (expert CFA)

Le financement OPCO d'un contrat ≥ 12 mois est versé en **4 fois** :
**40 %** (dans les 30 jours), **30 %** (7e mois), **20 %** (10e mois),
**10 %** (solde). Pour un contrat < 12 mois : **50 % + solde**. L'échéancier est
calculé à partir du **montant accepté** et des **dates du contrat**.

## 📦 Livré

| Élément | Détail |
|---|---|
| **Échéancier** | Table `opco_payments` + enum `PaymentStatut` (Attendu / Versé / En retard) + modèle + factory |
| **Génération légale** | `OpcoFile::genererEcheancier()` (40/30/20/10 ou 50/50), idempotente, **auto à l'acceptation** du dossier + action manuelle « Générer l'échéancier » |
| **Suivi UI** | Relation manager **Échéancier de versement** (voir + **« Marquer versé »**) ; colonnes **Versé / Reste à verser** dans la liste OPCO |
| **Alertes** | Commande `opco:flag-echeances` (planifiée quotidiennement) : passe les échéances dépassées en **En retard** et crée une **tâche de relance** (source auto) |
| **Totaux** | `montantVerse()` / `resteAVerser()` sur le dossier |

## 🧪 Tests

`tests/Feature/OpcoEcheancierTest.php` (6 cas) : génération 40/30/20/10 (≥12 mois,
somme = montant accepté) · échéancier 50/50 (<12 mois) · idempotence · génération
auto à l'acceptation · suivi versé / reste · bascule en retard + relance.
**Suite complète : 76 tests / 167 assertions.**

## 🗃️ Migration

- `opco_payments` (nouvelle table).

## ✅ Définition de "Terminé"

- [x] Échéancier conforme décret 2025-585
- [x] Génération auto à l'acceptation + action manuelle
- [x] Marquage des versements + suivi versé/reste
- [x] Alerte automatique des retards (commande planifiée)
- [x] Tests verts (module + suite complète)
- [ ] Mergé sur `develop`

## 🏆 Impact concurrentiel

Répond directement à une faiblesse du marché (transmission/suivi OPCO manuel,
peu d'outils suivent l'échéancier légal). Renforce le pilier **protection du
financement**. Prochaine brique du même pilier : **module Tâches & Alertes**
(EPIC-10) pour centraliser toutes les alertes proactives.
