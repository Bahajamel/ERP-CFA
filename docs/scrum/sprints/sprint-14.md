# Sprint 14 — Signature électronique multi-parties (EPIC-08)

**Durée :** 1 semaine · **Branche :** `feature/signature-electronique` → `develop`
**Origine :** **gap concurrentiel #1** identifié à l'audit — les CFA jonglent
entre l'ERP et un outil de signature externe, avec ressaisie et contrats papier.

## 🎯 Sprint Goal

Signer un contrat d'apprentissage **sans quitter l'ERP** : envoyer le dossier en
signature électronique à toutes les parties (employeur, apprenti, représentant
légal si mineur, CFA), suivre l'avancement, et — à la signature de tous —
passer le contrat à « Signé » automatiquement en **archivant la preuve** (P1-08-6,
P1-08-7).

## 🧩 Architecture (prête pour un prestataire eIDAS réel)

Comme pour LivretRS, on découple : une **interface** `SignatureProvider`
(`envoyer` / `annuler`) que tout prestataire qualifié (Yousign, Docaposte
Contralia, Universign…) implémentera sans toucher au reste. Le driver actif est
choisi dans `config/signature.php` (`SIGNATURE_DRIVER`).

- **`simulation`** (par défaut) : déroule tout le parcours multi-parties en local,
  **sans valeur probante**, pour la démo et les tests.
- **`none`** : signature électronique désactivée (on marque « signé » à la main).
- **(à venir)** : un prestataire eIDAS réel, branché sur la même interface + le
  webhook de callback déjà en place.

## 📦 Livré

- **`SignatureRequest`** (enveloppe : contrat, prestataire, `external_id`,
  signataires en JSON avec date de signature par partie, statuts).
- **`SignatureService`** : compose les signataires (représentant légal ajouté
  **uniquement si l'apprenti est mineur** à la date de début, art. L6222-1),
  envoie l'enveloppe, enregistre chaque signature, et à la complétion passe le
  contrat à « Signé » + archive la preuve dans la GED. Idempotent (pas de double
  envoi).
- **Webhook** `POST /webhooks/signature/{provider}` (hors CSRF, authentifié par
  secret partagé) : point d'entrée des callbacks d'un prestataire réel.
- **UI contrat** : actions *Envoyer en signature électronique* (éditer les
  signataires avant envoi), *Simuler la signature (démo)*. Le statut de signature
  du contrat reflète l'avancement.

## 🧪 Tests

`tests/Feature/SignatureTest.php` (11 cas) : composition des signataires (+ mineur) ·
envoi + bascule « Envoyé » · idempotence · refus si déjà signé · signature
partielle · complétion → contrat signé + preuve archivée · **webhook de bout en
bout** · enveloppe inconnue (404) · driver désactivé · **rendu de la page
contrat + visibilité de l'action**.
**Suite complète : 289 tests verts.**

## 🗃️ Migration & config

- Table `signature_requests`.
- `config/signature.php` + variables `SIGNATURE_*` dans `.env.example`.
- Binding `SignatureProvider` dans `AppServiceProvider`.

## ✅ Définition de "Terminé"

- [x] Interface prestataire + driver simulation + driver none
- [x] Service (envoi, suivi, complétion, archivage) + webhook
- [x] Actions sur le contrat + statut de signature
- [x] Représentant légal si apprenti mineur
- [x] Tests verts (module + suite complète + rendu UI)
- [ ] Mergé sur `develop`
- [ ] Brancher un prestataire eIDAS réel (hors sprint — nécessite un compte)

## 🏆 Impact concurrentiel

La signature électronique multi-parties devient **native** : plus d'aller-retour
avec un outil tiers, contrat signé et archivé dans le dossier apprenant en un
geste. L'architecture pluggable permet de connecter un prestataire eIDAS le jour
où le CFA en choisit un, sans réécrire le workflow.
