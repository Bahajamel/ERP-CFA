# Vision intelligente — ERP CFA

Ce document définit ce qui fait de cet ERP une **solution intelligente et
avancée** pour les CFA, et non un simple CRUD. Il complète le cahier des charges
en haussant l'ambition là où se trouve la vraie valeur métier.

> Principe directeur : **construire dès la V1 les fondations qui rendent
> l'intelligence possible, sans sur-coder l'IA tout de suite.** On livre d'abord
> le parcours principal solide ; l'intelligence s'ajoute par couches.

> 🎯 **Positionnement concurrentiel :** ces piliers sont aussi notre réponse aux
> faiblesses des leaders du marché (Ypareo, Digiforma, Dendreo). Détail dans
> [analyse-concurrentielle.md](../strategy/analyse-concurrentielle.md).

---

## 1. Les piliers d'intelligence (axes retenus)

### Pilier A — Protection du financement (OPCO & service fait)
**Douleur réelle :** un CFA perd du financement quand un dossier OPCO est rejeté
puis oublié, ou quand l'assiduité (service fait) n'est pas prouvée — le NPEC est
versé au prorata du service fait réalisé.

**Ce que l'ERP fait :**
- Bloque les transitions dangereuses (ex. OPCO "Prêt au dépôt" impossible si
  contrat non signé).
- Toute anomalie financière a **toujours une "prochaine action" + un responsable**.
- Tableau des **dossiers bloqués** (motif, responsable, montant à risque).
- Alerte sur OPCO sans retour, rejet non traité, service fait non validé,
  facture en retard.

**Indicateur clé :** *cash à risque* = somme des montants attendus sur dossiers
bloqués / en retard.

### Pilier B — Qualiopi automatisé (preuves au fil de l'eau)
**Douleur réelle :** les CFA reconstituent les preuves Qualiopi en panique avant
l'audit, par ressaisie manuelle.

**Ce que l'ERP fait :**
- La **preuve est un sous-produit du travail quotidien** : un contrat signé, une
  présence validée, un test de positionnement, une action de recherche
  d'entreprise → génèrent automatiquement une preuve rattachée à l'indicateur
  qualité / la mission CFA concernée.
- Détection des **preuves manquantes** par indicateur.
- Export d'un **pack de preuves** prêt pour l'audit, à tout moment.

### Pilier C — Détection du risque de rupture (décrochage)
**Douleur réelle :** la rupture coûte cher (financière, humaine, qualité) et se
voit venir.

**Ce que l'ERP fait :**
- **Score de risque** par apprenant, calculé d'abord par **règles** (V1/P1) :
  absences injustifiées répétées, retards, dossier figé, alertes non traitées.
- Remonte les apprenants à risque **avant** la rupture, avec action
  d'accompagnement suggérée.
- En P2 : passage à un modèle **prédictif** entraîné sur l'historique.

### Pilier D — Pilotage actionnable + IA d'aide à la décision
**Douleur réelle :** des dashboards décoratifs que personne n'actionne.

**Ce que l'ERP fait :**
- **Chaque chiffre est cliquable** et mène à la liste filtrée des dossiers
  concernés (règle déjà au CDC).
- Chaque indicateur suggère **la prochaine action prioritaire**.
- En P2 : **assistant IA** (résumé de dossier, suggestion d'action, rédaction
  d'emails de relance, Q&A sur les données — via les modèles Claude).

### Pilier E — Excellence commerciale & placement (CRM + matching)
**Douleur réelle :** la phase amont (sourcing candidats, démarchage entreprises,
mise en relation) se fait dans Excel + emails ; les leaders facturent cher un vrai
CRM (Dendreo/Digiforma) ou un matching assisté (Ypareo, La Bonne Alternance).

**Ce que l'ERP fait :**
- **Pipeline visuel** (Kanban) des candidats et des besoins — le flux, pas une liste.
- **Matching assisté par score** de compatibilité (règles explicites), proposition
  d'un candidat à un besoin **en 1 clic**.
- **Suivi commercial** : timeline d'interactions + **prochaine action** datée qui
  génère une tâche.
- **Ciblage d'entreprises à potentiel** (besoin ouvert compatible / historique de
  recrutement).

> Spécification détaillée :
> [excellence-commerciale-crm-matching.md](excellence-commerciale-crm-matching.md).

---

## 2. Les 4 fondations techniques (à poser dès la V1)

Ces fondations sont peu coûteuses au départ mais débloquent tout l'avancé ensuite.

| Fondation | Mise en œuvre V1 | Ce qu'elle débloque |
|---|---|---|
| **Machine à états explicite** | enum de statuts + table/config des transitions autorisées par module ; transitions contrôlées (jamais libres) | Workflows automatisés, blocages métier, audit fiable |
| **Relations polymorphes** | `documentable`, `taskable`, `proofable`, `loggable`, `notable` | Preuves qualité auto, traçabilité totale, rattachement universel |
| **Moteur de règles → tâches/alertes** | règles évaluées en job Redis ; produisent tâches + notifications | Alertes proactives, puis scoring de risque |
| **Données propres & historisées** | activitylog systématique, soft delete, valeurs avant/après | BI, prédictif, IA d'aide à la décision |

---

## 3. Le moteur de règles (cœur de l'intelligence)

Plutôt que d'éparpiller des `if` dans le code, on centralise les règles métier
dans un **moteur évaluable** (job planifié + déclencheurs sur transition d'état).

Exemples de règles V1/P1 (déclencheur → effet) :

| Déclencheur | Effet |
|---|---|
| Candidat "Dossier incomplet" depuis > N jours | Tâche "relancer pièces" + alerte admission |
| Contrat "Signé" sans dossier OPCO | Tâche "créer dossier OPCO" |
| OPCO "Déposé" sans retour depuis > N jours | Alerte "OPCO sans retour" |
| OPCO "Rejeté" | Action de correction obligatoire + responsable |
| Absence injustifiée saisie | Tâche de suivi + +score risque rupture |
| Période d'assiduité non validée en fin de mois | Alerte "service fait à valider" (cash à risque) |
| Preuve qualité manquante sur indicateur actif | Alerte qualité |

Cette table de règles est **la même structure** qui portera, en P2, le scoring de
risque et les suggestions de l'assistant IA.

---

## 4. Impact sur le backlog

Ces piliers ne créent pas (encore) de nouveaux sprints V1. Ils **enrichissent**
des epics existants et **précisent** des epics P2 :

- **EPIC-09 (OPCO)** & **EPIC-10 (Tâches/Alertes)** → moteur de règles + "cash à risque".
- **EPIC-12 (Dashboards)** → indicateurs actionnables + "prochaine action".
- **EPIC-17 (Qualité, P1)** → preuves au fil de l'eau (auto-génération).
- **EPIC-26 (Prédictif, P2)** → scoring de risque de rupture (commence en règles dès P1).
- **EPIC-25 (IA, P2)** → assistant IA (Claude) : résumé, suggestion, rédaction, Q&A.
- **EPIC-02/03/04/05 (Candidats/Entreprises/Besoins/Matching, P0)** → Pilier E :
  pipeline Kanban, matching scoré, timeline commerciale + prochaine action
  (détail : [excellence-commerciale-crm-matching.md](excellence-commerciale-crm-matching.md)).

---

## 5. Garde-fous (rester pragmatique)

- On **ne code pas l'IA** avant que le parcours P0 soit solide et que les données
  soient propres et historisées.
- Toute "intelligence" V1 = **règles déterministes** (explicables, testables),
  pas de modèle statistique.
- Chaque fonctionnalité avancée doit rester **utilisable dans l'interface**, même
  simplement (règle du doc d'organisation).
