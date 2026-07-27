# Scénario de démonstration — Direction de CFA

**Objectif :** en ~15 minutes, faire vivre à un directeur de CFA le parcours
complet d'un apprenti dans l'ERP, et à chaque étape montrer **en quoi c'est mieux
que chez Ypareo / Digiforma / Dendreo**. Fil rouge : *une seule plateforme, la fin
des doubles saisies, et de l'intelligence qui protège le financement.*

> **Prérequis** — jeu de démo chargé : `php artisan migrate:fresh --seed`.
> Lancer l'app : `php artisan serve --no-reload` (avec `PHP_CLI_SERVER_WORKERS=8`).
> Pour la génération/signature en direct : `run_service.bat` (LivretRS) + `php artisan queue:work`.

## 0. Ouverture — « le problème que vit un CFA aujourd'hui »

> « Aujourd'hui un CFA jongle entre un logiciel de gestion daté, des fichiers Excel
> et des dossiers partagés. On ressaisit les mêmes infos, on découvre les problèmes
> trop tard, et l'audit Qualiopi est un sprint de panique. »

Se connecter en **Direction** (`direction@cfa-v2s.fr` / `password`).
→ L'**accueil** affiche d'emblée le **Guide de démarrage** (7 étapes du cycle) et la
**Vue direction** : candidats actifs, besoins, contrats, **OPCO bloqués**,
financement encaissé, tâches. *« Tout le pilotage sur un écran, cliquable. »*

## 1. Le cycle de vie de l'apprenant (le cœur)

Chaque module porte un **sous-titre explicatif** : l'outil se comprend sans
formation. Dérouler le fil :

1. **Candidat** → fiche + **Vue Pipeline** (kanban) : où en est chaque candidat.
2. **Admission** → checklist des pièces obligatoires ; un dossier incomplet
   **bloque** la suite. *« La conformité est verrouillée dès l'entrée. »*
3. **Entreprise + Besoin** → l'entreprise, ses tuteurs, et le poste à pourvoir.
4. **Matching** → rapprochement candidat ↔ besoin, suivi jusqu'à l'embauche.
5. **Contrat** → *(voir §2, signature électronique)*.
6. **Dossier OPCO** → *(voir §3, protection du financement)*.

> Argument transverse : **source unique**. Le candidat saisi une fois se retrouve
> dans l'admission, le contrat, l'OPCO, la finance — **zéro double saisie**, là où
> les concurrents cloisonnent (ou renvoient à Excel).

## 2. 🖊️ Signature électronique native *(gap concurrentiel #1)*

Sur un **contrat** « Envoyé pour signature » (Chloé Mercier) :
- Action **« Envoyer en signature électronique »** → les signataires sont
  **pré-remplis** (apprenti, employeur, CFA — **+ représentant légal si mineur**).
- Montrer une demande **en cours** (l'apprenti a déjà signé, 1/3).
- Action **« Simuler la signature »** → toutes les parties signent → le contrat
  passe **automatiquement à « Signé »** et la **preuve est archivée dans la GED**.

> « Chez les autres, on exporte le contrat, on va sur un outil tiers, on ré-importe
> le PDF signé. Ici c'est **natif** : signé et classé dans le dossier, sans quitter
> l'ERP. » *(Architecture prête pour un prestataire eIDAS réel — Yousign, Docaposte.)*

## 3. 🛡️ Protection du financement OPCO *(la valeur qui parle au directeur)*

Dans **Dossiers OPCO**, ouvrir le dossier **bloqué** (rejeté → en correction, NIR
erroné) :
- Le rejet a **automatiquement créé une tâche corrective** assignée à
  l'administratif, avec échéance.
- Le tableau de bord direction compte les **OPCO bloqués** en rouge.

> « Un dossier OPCO qui traîne, c'est du financement qui s'évapore. L'outil le
> **détecte, l'assigne et le relance** — il ne se contente pas de l'afficher. »

## 4. 🔮 Détection ET gestion du risque de rupture *(différenciateur phare)*

C'est **le** point où Ypareo est le plus critiqué : *« on constate les ruptures,
on ne les anticipe pas. »*

**a) Anticiper** — widget **« Apprentis à risque de rupture »** : Yanis Moreau,
score **50/100 (Élevé)**, avec les **facteurs expliqués** (maître d'apprentissage
parti + OPCO rejeté) et le lien vers le contrat. Une tâche a été créée pour le
commercial.

**b) Gérer** — module **Dossiers de rupture** : un dossier **en accompagnement**
(Inès Chevalier). Montrer :
- le **motif** et l'**initiative** tracés ;
- la **recherche d'un nouvel employeur** (reclassement) en cours ;
- l'ouverture d'un dossier **propage** la rupture : tâches de régularisation
  **OPCO + Finance** créées automatiquement ;
- le widget **« Ruptures & reclassement »** affiche le **taux de reclassement**.

> « On passe du **constat** à l'**action** : *pourquoi* c'est à risque, *qui* doit
> agir, et *comment* on reclasse l'apprenti. Personne ne fait ça aussi loin. »

## 5. ✅ Qualiopi audit-ready en continu

Module **Registre Qualiopi** : les 32 indicateurs / 7 critères, avec **statut de
conformité**, responsable et **preuves** rattachées.
- Le badge de menu montre les **non-conformités** (3 en démo) ; chacune a **généré
  une tâche** pour son responsable.
- Widget **« Conformité Qualiopi »** = taux en temps réel.

> « La conformité devient un **processus vivant** mesuré au fil de l'eau, pas un
> classeur qu'on reconstitue la veille de l'audit. »

## 6. 📄 Génération des livrables CFA (LivretRS) & missions L6231-2

Sur un contrat signé : **« Générer les livrables (auto) »** → LivretRS produit les
livrables **brandés et signés**, importés dans la GED et **tagués aux 14 missions
CFA** (art. L6231-2). Le registre **Missions CFA** montre la **couverture** de
chaque mission.

> « L'obligation des 14 missions, prouvée automatiquement — un sujet que les
> généralistes de la formation continue (Digiforma, Dendreo) ne traitent pas. »

## 7. Clôture — le pitch en une phrase

> « **Une seule plateforme** pour tout le cycle de l'apprenti, **moderne et
> auto-explicative**, qui **unifie** les données et surtout **protège votre
> financement** et **anticipe les ruptures**. Là où les autres affichent, nous
> **agissons**. »

---

## Comptes de démonstration

| Rôle | Email | Ce qu'il met en valeur |
|---|---|---|
| Direction | `direction@cfa-v2s.fr` | Vue de pilotage globale, risques, ruptures |
| Commercial | `commercial@cfa-v2s.fr` | Candidats, besoins, matching |
| Admission | `admission@cfa-v2s.fr` | Checklist & conformité des dossiers |
| Administratif | `administratif@cfa-v2s.fr` | Contrats, signature, OPCO |
| Finance | `finance@cfa-v2s.fr` | Lignes financières, facturation |
| Qualité | `qualite@cfa-v2s.fr` | Registre Qualiopi |

Mot de passe : `password`. Accès rapide démo : `/demo/admin` (hors production).

## Points « wow » à ne pas rater

1. **Simuler la signature** → contrat signé + preuve archivée, en direct.
2. Le widget **risque de rupture** qui **explique** le score (pas juste une liste).
3. L'ouverture d'un **dossier de rupture** qui crée les tâches OPCO + Finance.
4. Le **badge Qualiopi** rouge → non-conformités transformées en tâches.
5. Chaque page se **présente elle-même** (sous-titre métier) — zéro formation.
