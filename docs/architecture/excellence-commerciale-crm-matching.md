# Excellence commerciale — CRM & Matching intelligent (extension CDC)

> **Statut :** extension du cahier des charges (CDC §5 Candidats, §6 Entreprises,
> §7 Besoins, §8 Matching). Matérialise le **Pilier E** de la
> [vision intelligente](vision-intelligente.md) — le volet *commercial / placement*
> absent des piliers A→D.
>
> **Origine :** benchmark des solutions concurrentes (Ypareo, Dendreo, Digiforma)
> et des plateformes de placement (La Bonne Alternance, Walt). Voir
> [analyse concurrentielle](../strategy/analyse-concurrentielle.md).
>
> **Principe :** on n'ajoute pas de modules hors CDC ; on **enrichit la façon de
> réaliser** les modules Candidats / Entreprises / Besoins / Matching, en
> s'inspirant de ce que les leaders font payer cher — tout en restant sur des
> **règles déterministes** (pas d'IA en V1, cf. garde-fous de la vision).

---

## Pourquoi (le constat concurrentiel)

| Concurrent | Ce qu'il fait bien | Notre transposition |
|---|---|---|
| **Ypareo** | Trace chaque prospect dès le 1er contact ; workflow de mise en relation candidat↔entreprise | F2, F3 |
| **Dendreo / Digiforma** | Pipeline commercial (prospect→opportunité→relance), suivi des actions/relances | F1, F3 |
| **La Bonne Alternance** | Algorithme qui repère les entreprises à fort potentiel d'embauche ; candidature en 1 clic | F2, F4 |
| **Walt** | Candidat visible des entreprises, mise en relation en 1 clic | F2 |

Le concurrent quotidien réel reste **Excel + emails** : un flux visuel et un
matching assisté suffisent à créer un écart net.

---

## F1 — Pipeline visuel (vue Kanban) des candidats et des besoins

**Objectif.** Visualiser le **flux** (et non une liste figée), à la manière d'un
CRM commercial (Dendreo/Digiforma), pour répondre à « mes candidats à relancer /
leads chauds » et au suivi d'un besoin comme un entonnoir de recrutement.

**Fonctionnalités attendues.**
- Vue **tableau Kanban** des **candidats** par statut : `Dossier incomplet →
  Dossier complet → En recherche d'entreprise → Contrat signé` (+ colonne
  `Rupture` à part).
- Vue **Kanban des besoins** par statut (entonnoir) : `Créé → En qualification →
  Profils recherchés → Profils envoyés → Entretien prévu → Candidat retenu →
  Pourvu`.
- **Glisser-déposer** une carte pour changer de statut **uniquement si la
  transition est autorisée** (cf. machine à états) ; sinon refus explicite.
- Filtres conservés (commercial, formation, période) ; carte cliquable → fiche.

**Règles métier.**
- Un changement de colonne = une transition d'état **contrôlée** (jamais libre).
- La colonne `Rupture` n'est jamais une cible de drag manuel (passage via dossier rupture, P1).

**Critères d'acceptation.**
- Le commercial voit ses candidats répartis par statut et déplace une carte d'un
  statut autorisé à un autre, avec mise à jour persistée + journalisée.
- Une transition interdite est refusée avec un message compréhensible.

**Priorité proposée : P0** (enrichit EPIC-02, EPIC-04 et le dashboard commercial
EPIC-12). Faible coût, fort effet « waouh » en review.

---

## F2 — Matching assisté par score de compatibilité

**Objectif.** Passer du matching **manuel** au matching **assisté** : proposer au
commercial les meilleurs candidats pour un besoin (et réciproquement), comme le
workflow de mise en relation d'Ypareo et l'algorithme de ciblage de La Bonne
Alternance — mais par **règles explicites et testables**.

**Fonctionnalités attendues.**
- Depuis un **besoin** : action **« Trouver des candidats »** → liste des
  candidats **classés par score de compatibilité**, avec **proposition en 1 clic**
  (crée le `matching` au statut `Proposé`).
- Depuis un **candidat** : action **« Besoins compatibles »** → liste des besoins
  ouverts classés par score.
- Le score est **affiché et explicable** (ex. « Formation ✓ · En recherche ✓ ·
  Mobilité ✓ »).

**Calcul du score (règles déterministes, V1).** Somme pondérée, ex. :

| Critère | Condition | Points |
|---|---|---|
| Formation | `candidate.formation_visee_id == need.formation_id` | +50 |
| Disponibilité au placement | `candidate.statut == en_recherche_entreprise` | +25 |
| Mobilité / localisation | localisation candidat compatible besoin | +15 |
| Niveau | niveau requis atteint | +10 |
| Déjà proposé sur ce besoin | exclusion | −∞ (filtré) |

> Les pondérations vivent dans une **config** (proche de `business_rules`), pas en
> dur, pour rester ajustables sans redéploiement.

**Règles métier.**
- Un candidat **déjà proposé** sur le besoin n'est pas re-suggéré.
- La proposition en 1 clic respecte les contraintes du module Matching (CDC §8).

**Critères d'acceptation.**
- Pour un besoin « Développeur web » (formation Dév web), les candidats de cette
  formation en recherche d'entreprise apparaissent **en tête**, score à l'appui.
- « Proposer » crée le matching et le rend visible côté candidat **et** côté besoin.

**Priorité proposée : P0 (version règles)** pour la story P0-05-1, **affinage P1**
(pondérations avancées, historique d'acceptation). Cohérent avec « l'intelligence
s'ajoute par couches » de la vision.

---

## F3 — Suivi commercial : timeline d'interactions & prochaine action

**Objectif.** Ce qui distingue un **CRM** d'un annuaire : l'historique vivant des
échanges et la **prochaine action** à mener (cœur de Dendreo : « les relances à
effectuer »).

**Fonctionnalités attendues.**
- Sur **candidat** et **entreprise** : un fil chronologique d'**interactions**
  (note, appel, e-mail, compte rendu de RDV) via la table polymorphe `notes`
  (`notable`), complété par le journal (`activity_log`).
- Champ **« prochaine action » + date de relance** → alimente automatiquement une
  **tâche** assignée (lien avec EPIC-10).
- Affichage de la prochaine action sur la fiche et dans le **dashboard commercial**
  (« actions en retard »).

**Règles métier.**
- Une interaction conserve **auteur + date/heure** (traçabilité).
- Une « prochaine action » datée crée/maj une tâche reliée (polymorphe) à l'objet.

**Critères d'acceptation.**
- Depuis une fiche candidat, l'utilisateur ajoute une note et planifie une relance ;
  la tâche apparaît dans ses tâches à faire et l'historique affiche l'échange.

**Priorité proposée : P0** (la table `notes`/`notable` est une **fondation** déjà
prévue par la vision intelligente). Enrichit EPIC-02 et EPIC-03.

---

## F4 — Détection d'entreprises à potentiel (ciblage)

**Objectif.** Mini-version du ciblage prédictif de La Bonne Alternance, par règles :
aider le commercial à savoir **quelle entreprise démarcher** pour un candidat.

**Fonctionnalités attendues.**
- Depuis un **candidat** : liste **« Entreprises à cibler »** = entreprises ayant
  un **besoin ouvert compatible** (formation) **ou** ayant **déjà recruté** dans
  cette formation (historique de contrats).
- Marqueur visuel « entreprise déjà partenaire / a déjà recruté ».

**Règles métier.**
- Le ciblage s'appuie sur des **données réelles** (besoins ouverts, contrats
  passés), jamais sur un chiffre décoratif (cf. règle CDC §18).

**Critères d'acceptation.**
- Pour un candidat en formation X, l'ERP propose les entreprises avec un besoin
  ouvert en X et/ou un historique de contrat en X, triées par pertinence.

**Priorité proposée : P1** (dépend de données historisées suffisantes ; plus de
valeur une fois le parcours P0 peuplé). Préfigure EPIC-26 (prédictif).

---

## Impact sur le modèle de données

| Élément | Nature | Détail |
|---|---|---|
| `notes` (`notable`) | **nouvelle table** (déjà prévue au modèle global) | F3 — interactions polymorphes |
| `business_rules` (pondérations) | config | F2 — pondérations du score |
| Aucune table nouvelle pour F1 / F4 | — | F1 = vue ; F4 = requêtes sur l'existant |

> Conforme à [modele-de-donnees.md](modele-de-donnees.md) : `notes/notable` y est
> déjà listé. Pas de nouveau statut introduit ; F1 réutilise les statuts existants.

---

## Synthèse priorisation

| Feature | Module(s) CDC | Epic(s) | Priorité proposée |
|---|---|---|---|
| F1 — Pipeline Kanban | Candidats, Besoins, Dashboard | EPIC-02, 04, 12 | **P0** |
| F2 — Matching scoré | Matching | EPIC-05 | **P0** (règles) → P1 (affinage) |
| F3 — Timeline & prochaine action | Candidats, Entreprises | EPIC-02, 03, 10 | **P0** |
| F4 — Détection entreprises à potentiel | Entreprises, Matching | EPIC-03, 05 | **P1** |

> ⚠️ **À arbitrer par le PO** : ces priorités sont une proposition. Si la tenue du
> plan 8 semaines prime, F2-affinage et F4 basculent en P1 (V2) sans rien casser.
