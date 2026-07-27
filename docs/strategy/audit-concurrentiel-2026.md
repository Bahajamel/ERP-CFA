# Audit concurrentiel 2026 — points faibles des concurrents → notre avantage

Veille ciblée sur **Ypareo, Digiforma, Dendreo** (avis utilisateurs, limites
connues) et sur le contexte CFA/apprentissage. Objectif : exploiter leurs
faiblesses pour renforcer notre ERP.

> Rappel de positionnement : gagner par **UX + unification + intelligence** —
> pas par le nombre de fonctionnalités (là-dessus ils sont déjà énormes).

---

## 1. Points faibles par concurrent

### Ypareo (leader CFA)
- **Complexité / prise en main difficile** au quotidien (richesse = lourdeur).
- **Lenteurs** quand la base contient beaucoup de données.
- **SaaS peu flexible** pour des besoins spécifiques.
- Échecs de déploiement liés aux **données incomplètes**, à l'**adoption inégale**
  et aux **ruptures de processus** entre administration, formateurs et entreprises ;
  rattachements groupe/parcours peu fiables → perception dégradée immédiate.

### Digiforma (OF / Qualiopi)
- **Tarification opaque** (pas de prix en ligne, contact obligatoire).
- **Interface dense**, débutants perdus sans accompagnement.
- **LMS intégré basique** ; **CRM limité**.
- **Support client** critiqué ; limitations du forfait Starter peu claires.

### Dendreo (OF à forte activité commerciale)
- **Sur-dimensionné et complexe** pour les petites/moyennes structures.
- Orienté OF commercial : **moins « CFA apprentissage »** dans l'ADN.

### Constat transversal (contexte CFA)
- **Transmission OPCO manuelle** (portails web) → erreurs, retards, oublis.
- Pression sur le financement + exigences réglementaires croissantes.
- **Décret n° 2025-585 (27/06/2025)** : nouvel échéancier de versement pour les
  contrats ≥ 12 mois → **40 %** (J+30), **30 %** (7e mois), **20 %** (10e mois),
  **10 %** (solde). Peu d'outils suivent finement ces versements.

Sources en fin de document.

---

## 2. Faiblesse → notre opportunité → statut chez nous

| Faiblesse concurrente | Notre réponse | Statut |
|---|---|---|
| Complexité / prise en main | UX simple, **rôles = ne voir que son métier**, actions guidées | ✅ Fait (gating, workflow) |
| Lenteurs sur gros volumes | Listes **recherche serveur** (pas de preload massif) | ✅ Fait (sprint 07) |
| Ruptures de process entre acteurs | **Parcours 360°** unifié + machine à états qui enchaîne les étapes | ✅ Fait (vue 360°, transitions) |
| Données incomplètes | **Gardes métier** (email/tél obligatoire, pièces obligatoires, pas de signé sans preuve) | ✅ Fait |
| Transmission OPCO manuelle / erreurs | Dossier OPCO **créé automatiquement**, gardes de dépôt, action corrective au rejet | ✅ Fait (partiel) |
| Suivi des versements OPCO (décret 2025-585) | **Échéancier 40/30/20/10 + alertes** de versement | 🔜 À faire (priorité) |
| Qualiopi = preuves laborieuses | **Preuves au fil de l'eau** (GED en place, module Preuves à venir) | 🟡 GED fait, preuves à faire |
| Manque d'anticipation (rupture, retard) | **Alertes proactives** + détection risque de rupture | 🔜 À faire |
| CRM/relation entreprise faible (Digiforma) | CRM entreprises + besoins + matching (collègue) | 🟡 En cours |
| Tarification opaque (Digiforma) | **Transparence** = argument commercial (hors logiciel) | 💬 Positionnement |

---

## 3. Recommandations priorisées (ce que je propose de construire)

### 🥇 Priorité 1 — Protection du financement (notre pilier n°1)
1. **Échéancier de versement OPCO** conforme décret 2025-585 (40/30/20/10) :
   génération des 4 échéances à l'acceptation, suivi payé/attendu/en retard,
   **alertes automatiques** avant chaque échéance. → différenciateur fort, peu
   présent ailleurs.
2. **Alertes OPCO proactives** : dossier sans retour depuis X jours, rejet à
   corriger, échéance de versement à venir.

### 🥈 Priorité 2 — Le module Tâches & Alertes (EPIC-10)
Le vrai antidote aux « ruptures de process » d'Ypareo : alertes in-app +
tâches auto sur les événements clés (dossier incomplet, contrat à signer, OPCO
sans retour…). Transforme l'ERP passif en **copilote**.

### 🥉 Priorité 3 — Qualiopi au fil de l'eau (EPIC-17)
Module **Preuves** (polymorphe) rattaché aux événements du parcours + **pack de
preuves exportable** → l'audit Qualiopi devient un clic, pas un projet.

### Ensuite
- **Dashboard direction actionnable** (KPI cliquables → listes filtrées).
- **Détection du risque de rupture** (score par règles : absences, dossier bloqué…).
- **Portail entreprise/tuteur léger** (signature, suivi) → supprime les
  ruptures de process avec l'externe.
- **API OPCO** (à terme) : transmission sans ressaisie, standard 2025.

---

## 4. Message différenciateur à retenir

> Les concurrents sont **complets mais lourds, passifs et opaques**.
> Nous visons **simple, unifié et intelligent** : un ERP qui **protège le
> financement**, **prépare Qualiopi tout seul** et **alerte avant que ça casse**.

---

## Sources
- [Ypareo — guide & avis 2026 (rank-studio)](https://rank-studio.com/ypareo-logiciel-gestion-cfa/)
- [Ypareo — 7 flux clés / déploiement (capital-pedagogique)](https://capital-pedagogique.fr/ypareo-flux-roles-checklist-deploiement-net/)
- [Ypareo — avis (appvizer)](https://www.appvizer.fr/services/centre-formation/ymag-ypareo)
- [Digiforma — test forces & limites (Saask)](https://saask.fr/softwares/digiforma/test/)
- [Digiforma — avis & alternatives (logiciels.pro)](https://www.logiciels.pro/digiforma/)
- [Dendreo — comparatif logiciels formation (formapro)](https://www.formapro.com/articles/ammon-dendreo-digiforma-oryzea-comparatif-de-4-logiciels-de-gestion-de-la-formation-1)
- [API CFA OPCO — guide 2025 (Linkpick)](https://linkpick.fr/blog/api-cfa-opco-guide-complet-2025)
- [Facturation OPCO apprentissage & décret 2025-585 (filiz.io)](https://www.filiz.io/blog/facturation-opco-apprentissage-processus-erreurs)
