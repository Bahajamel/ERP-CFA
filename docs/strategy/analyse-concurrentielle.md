# Analyse concurrentielle & différenciation — ERP CFA

> **But :** se positionner face aux ERP/SI de formation existants (Ypareo,
> Digiforma, Dendreo…), identifier leurs **points faibles** et en faire nos
> **points forts**, pour construire le modèle le plus solide.
>
> ⚠️ **À valider :** ce document s'appuie sur la connaissance générale du marché
> et les retours utilisateurs récurrents. Avant tout usage commercial, confronter
> ces points à des **avis récents et des utilisateurs réels** (les éditeurs évoluent).

---

## 1. Thèse de différenciation (en une phrase)

> Le marché oppose des outils **profonds mais datés/complexes** (Ypareo) à des
> outils **modernes mais orientés organismes de formation continue** (Digiforma,
> Dendreo). Le créneau gagnant : un ERP **nativement CFA / apprentissage**,
> **moderne et simple**, **unifié** (une seule source de vérité), et surtout
> **intelligent** (protection du financement, Qualiopi au fil de l'eau, détection
> de rupture, pilotage actionnable).

---

## 2. Cartographie des concurrents

### Ypareo (Ymag) — le poids lourd historique du CFA
- **Forces :** très complet sur l'apprentissage (contrats, CERFA, OPCO, scolarité,
  facturation), profondément implanté dans les gros CFA, riche fonctionnellement.
- **Faiblesses fréquemment citées :** UX/ergonomie **datée** et dense, **courbe
  d'apprentissage raide**, navigation lourde, personnalisation difficile,
  reporting peu souple, évolutions lentes, ressenti « usine à gaz », support et
  montée de version parfois critiqués.
- **Lecture :** la profondeur métier est là, mais l'expérience est le talon d'Achille.

### Digiforma — moderne, orienté OF & Qualiopi
- **Forces :** **simplicité**, interface agréable, **Qualiopi** bien outillé,
  LMS intégré, bon pour les petits/moyens organismes de formation, mise en route rapide.
- **Faiblesses fréquemment citées :** moins taillé pour la **complexité du CFA /
  apprentissage** (suivi OPCO apprentissage, CERFA, alternance, ruptures),
  profondeur des workflows limitée, personnalisation/échelle bornées, tarification
  qui grimpe avec le volume.
- **Lecture :** belle UX mais pas un vrai SI d'apprentissage.

### Dendreo — moderne, modulaire, orienté formation continue
- **Forces :** **CRM** solide, modulaire, interface moderne, bonne gestion
  administrative et Qualiopi pour les centres de formation continue.
- **Faiblesses fréquemment citées :** orientation **formation continue** plus que
  **CFA/alternance**, fonctionnalités apprentissage moins profondes, coût par
  modules qui s'additionne, complexité à mesure qu'on empile les modules.
- **Lecture :** excellent en continue, moins « apprentissage-natif ».

### Autres (contexte)
- Tableurs Excel + emails + dossiers partagés : encore la réalité de beaucoup de
  CFA — **notre vrai concurrent quotidien** (et le plus facile à battre).

---

## 3. Faiblesses transversales = nos opportunités

| Faiblesse récurrente du marché | Notre réponse (différenciateur) | Où dans le projet |
|---|---|---|
| UX datée / complexe / formation longue | **UX moderne, épurée, par rôle** : chacun arrive sur son espace, ses tâches, ses alertes | Filament + dashboards par rôle (EPIC-12) |
| Données en silos, doubles saisies | **Source unique de vérité**, relations polymorphes, tout relié | Architecture (EPIC-00/06/10/11) |
| Suivi OPCO manuel, financements perdus | **Protection du financement** : blocages, « cash à risque », prochaine action | Vision pilier A (EPIC-09/10) |
| Qualiopi reconstitué en panique | **Preuves au fil de l'eau** : la preuve est un sous-produit du travail | Vision pilier B (EPIC-17) |
| Pas d'anticipation des ruptures | **Score de risque de décrochage** (règles puis prédictif) | Vision pilier C (EPIC-26) |
| Dashboards décoratifs, non actionnables | **Tout indicateur cliquable + prochaine action** | Vision pilier D (EPIC-12) |
| Personnalisation rigide | **Machine à états & règles configurables** (table de règles) | Architecture (`business_rules`) |
| Tarification par module/apprenant qui explose | **Tout-en-un**, périmètre unifié | Positionnement produit |
| Mise en route longue | **Onboarding rapide** (interne d'abord, données réelles ensuite) | Mise en service (CDC §26) |
| Support/évolutions lents | **Cycles courts (Scrum 1 semaine)**, proximité | Organisation Scrum |

---

## 4. Nos différenciateurs clés (« le plus fort modèle »)

1. **CFA-natif ET moderne** — la profondeur d'Ypareo avec l'UX de Digiforma/Dendreo.
2. **Plateforme unique réellement unifiée** — fin des silos et des doubles saisies.
3. **Protection active du financement** — l'ERP qui *défend le cash* (OPCO + service fait).
4. **Qualiopi sans douleur** — preuves collectées automatiquement, pack d'audit à tout moment.
5. **Intelligence proactive** — moteur de règles → alertes → score de risque de rupture.
6. **Pilotage actionnable** — des chiffres qui mènent à l'action, pas à la décoration.
7. **Adaptable sans dev** — statuts/règles configurables (machine à états).
8. **Évolution rapide & proche du terrain** — petits incréments, retours intégrés vite.

---

## 5. Ce qu'on n'attaque PAS en V1 (forces des incumbents à adresser plus tard)

Rester lucide : les leaders ont des atouts qu'on ne battra pas tout de suite.

- **Connecteurs réglementaires** (DECA/DPAE, EDOF, API OPCO, télétransmission CERFA)
  → puissants chez Ypareo ; chez nous **P2** (EPIC-23 connecteurs).
- **LMS complet** (contenus, e-learning) → fort chez Digiforma ; **P2** ou intégration tierce.
- **Comptabilité avancée / exports réglementaires complets** → **P2**.
- **Largeur fonctionnelle totale** → on gagne d'abord par la **profondeur du parcours
  principal + l'intelligence**, pas par le nombre de modules.

> Stratégie : **gagner sur l'expérience, l'unification et l'intelligence** d'abord ;
> rattraper la largeur réglementaire/connecteurs ensuite.

---

## 6. Risques & garde-fous

- **Ne pas sous-estimer la complexité réglementaire** (CERFA, NPEC, OPCO) : c'est
  là que les incumbents sont forts et que la confiance se gagne. Profondeur > esbroufe.
- **Ne pas se disperser** : la largeur fonctionnelle des leaders est un piège à 2 devs.
  On gagne par le **parcours principal solide + 2-3 différenciateurs marquants**.
- **Valider la valeur perçue** auprès de vrais CFA avant d'investir lourd.

---

## 7. Prochaines actions proposées

1. Confirmer les **2-3 différenciateurs prioritaires** à mettre en avant (démo & V1).
2. Les **matérialiser dans le prototype** (ex. « cash à risque », alertes proactives,
   pack de preuves) pour les rendre tangibles en réunion.
3. Recueillir des **retours terrain** (départements internes + 1-2 CFA pilotes).
4. Garder les **connecteurs réglementaires** comme cap P2 (différenciateur de la v2/v3).
