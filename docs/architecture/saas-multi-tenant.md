# Architecture SaaS multi-tenant — ERP CFA

> **But** : passer d'un ERP mono-établissement (notre CFA) à une **solution vendable à
> plusieurs CFA partenaires**, avec une isolation forte des données (RGPD / NIR),
> une fiabilité de niveau production et un coût d'infrastructure maîtrisé.
>
> **Décision actée** : on prépare le multi-tenant **maintenant** (des CFA partenaires
> sont attendus à court terme). Le faire avant les premiers clients coûte ~10× moins
> cher qu'après.

---

## 0. ⚠️ État réel — lire ceci en premier

**Le modèle « schéma-par-CFA / `stancl/tenancy` » décrit aux sections 1 à 5 n'a PAS été
retenu.** Il s'est révélé trop lourd pour cette application (sessions/cache/queue en
`database`, découpe de ~60 migrations, réécriture du socle de tests). `stancl` a été
installé puis désinstallé. Les sections suivantes sont **conservées à titre de
référence** pour le jour où un client exigerait une isolation physique.

**Architecture réellement en place : multi-tenance Filament native** — base unique,
colonne `organisation_id`, isolation logique. Une `Organisation` = un CFA ; notre CFA
(V2S) est un tenant parmi d'autres.

| Phase | Contenu | État |
|---|---|---|
| A | Modèle `Organisation`, pivot `organisation_user`, `User implements HasTenants` | ✅ |
| B | `organisation_id` + trait `BelongsToOrganisation` sur 18 modèles métier (3 lots) | ✅ |
| C | `->tenant(...)` activé, URLs `/admin/{slug}/…`, socle de tests | ✅ |
| D | Isolation réelle, scoping des comptes, unicités par CFA, panneau éditeur | ✅ |

### Ce que fait la phase D (et pourquoi)

1. **`OrganisationScope` (global scope Eloquent)** — jusqu'ici le cloisonnement ne
   venait que de l'ownership Filament, qui ne protège **que les Resources** : tout
   service, job, commande ou widget requêtant Eloquent voyait **tous les CFA**. Le
   scope déplace la barrière dans le modèle. Hors contexte CFA (job, commande,
   panneau éditeur) il ne s'applique pas — c'est voulu. Échappatoire explicite :
   `Model::query()->tousLesCfa()`.
2. **Comptes cloisonnés** — `UserResource` n'est pas scopable par ownership (relation
   many-to-many) : le filtrage est fait dans `getEloquentQuery()`, ce qui protège
   aussi la résolution d'URL. Un compte créé depuis un CFA lui est rattaché
   automatiquement (sans quoi il se connecte sans accès à aucun CFA).
3. **Unicités par CFA** — `companies.siret` et `invoices.numero` étaient uniques
   *globalement* : deux CFA ne pouvaient pas partager un employeur, ni numéroter
   leurs factures librement. Index composites avec `organisation_id`.
4. **Deux panneaux, deux audiences** — `/admin/{cfa}` (le personnel travaille dans son
   centre) et `/editeur` (nous créons/suspendons les CFA clients). Le rôle `Éditeur`
   est **hors matrice CFA** : `Administrateur => '*'` ouvre tous les modules mais
   jamais le pouvoir éditeur. Un CFA se supprime jamais depuis l'UI — seulement se
   suspend (réversible ; ses données incluent des pièces à NIR).
5. **Tests d'isolation** — la suite tournait dans un CFA unique et ne prouvait donc
   rien. `OrganisationIsolationTest` crée deux CFA et vérifie l'étanchéité réelle.

### Points connus, non traités

- `organisation_id` reste **nullable** (`nullOnDelete`). Une ligne à `organisation_id`
  nul est invisible de tous les CFA. À resserrer en `NOT NULL` + `cascadeOnDelete`
  le jour d'un vrai effacement RGPD par client.
- **Formulaires publics** (candidature, entreprise) : ils fixent `organisation_id` via
  `Organisation::defaut()` et s'exécutent sans CFA courant. En multi-CFA, il faudra
  des **liens publics dédiés par CFA** portant l'organisation cible.
- Pas de `->tenantRegistration()` : ouvrir un CFA est un acte commercial, il passe
  par `/editeur`. Pas de facturation SaaS (`tenantBillingProvider`).

---

## 1. Modèle de multi-tenance retenu : **schéma-par-CFA** (modèle B)

> ⚠️ **Non retenu** — voir la section 0. Conservé pour référence.

Un seul serveur PostgreSQL, **un schéma Postgres par CFA client**. Le CFA « central »
(le nôtre) est un tenant comme les autres.

| Modèle | Isolation | Coût | Effacement RGPD d'un client |
|---|---|---|---|
| A. Base partagée (`tenant_id`) | logicielle (fragile) | le moins cher | difficile |
| **B. Schéma-par-CFA** ✅ | **forte, physique** | **= modèle A** (schémas gratuits) | `DROP SCHEMA` = net |
| C. Serveur-par-CFA | totale | cher, ops lourdes | overkill |

**Pourquoi B** :
- Isolation physique = argument commercial **et** tranquillité RGPD/NIR.
- Un seul serveur → **pas plus cher** qu'une base partagée.
- Sauvegarde / export / suppression **par client** triviaux.
- Chemin bien balisé en Laravel via **`stancl/tenancy`**.

### Mise en œuvre technique (`stancl/tenancy`)

- Chaque CFA = un enregistrement `Tenant` + son schéma Postgres dédié.
- **Routage par sous-domaine** : `moncfa.<domaine-erp>.fr` → tenant résolu automatiquement.
- Une base **centrale** (schéma `public`) contient uniquement : la liste des tenants,
  les domaines, la facturation SaaS, les super-admins.
- Les **migrations tenant** (nos 69 migrations métier) se rejouent **par schéma** :
  `php artisan tenants:migrate`.
- Filament fonctionne par tenant (panneau admin isolé). Le panneau **central**
  (gestion des CFA clients) est un second panneau Filament réservé à l'éditeur.

### Chantier de bascule (ordre recommandé)

1. Installer `stancl/tenancy`, séparer migrations **centrales** vs **tenant**
   (nos tables métier deviennent « tenant »).
2. `TenancyServiceProvider` : bootstrap DB (bascule de schéma), cache, filesystem par tenant.
3. Isoler le **stockage fichiers par tenant** (préfixe/bucket par tenant — voir §3).
4. Adapter le `DemoSeeder` en **seeder de tenant** (données de démo par CFA).
5. Rejouer **toute la suite de tests** en contexte tenant (déjà verte sur Postgres — cf. CI).
6. Panneau central Filament : créer/suspendre/facturer un CFA, provisionner son schéma.

---

## 2. Infrastructure (fiable + bas coût, 100 % UE)

Tout hébergé en **Union Européenne** (obligatoire : on stocke du NIR / carte vitale).

| Brique | Choix | Coût indicatif |
|---|---|---|
| Nom de domaine | **OVH** | ~10 €/an |
| Serveur applicatif | 1 VPS **Scaleway** (FR) ou **Hetzner** (DE) | ~5–15 €/mois |
| PostgreSQL | Démarrage : sur le VPS. Puis : **Postgres managé** (backups + réplication) | 0 € → ~15–25 €/mois |
| Stockage documents | **Object Storage Scaleway/OVH** (S3 — déjà câblé dans le code) | ~0,012 €/Go |
| Déploiement + fiabilité | **Laravel Forge** ou **Ploi** (SSL auto, déploiements, backups DB quotidiens, monitoring) | ~12 €/mois |

**Total démarrage : ~30–45 €/mois** pour héberger **plusieurs CFA** de façon fiable,
et ça scale sans réécriture.

**Pourquoi Forge/Ploi et pas un VPS nu** : « super fiable » à bas coût suppose
d'automatiser SSL, sauvegardes, mises à jour sécurité et redémarrages. Forge/Ploi le font
pour ~12 €/mois — meilleur rapport fiabilité/prix/effort. Le VPS nu est moins cher mais
« fiable » consomme alors *notre* temps.

### Trajectoire de montée en charge (ne pas sur-dimensionner au départ)

- **Phase 1 (MVP, 1–5 CFA)** : VPS unique (app + Postgres) + Object Storage + Forge.
- **Phase 2 (croissance)** : Postgres **managé** séparé (backups/réplication), app sur VPS plus gros.
- **Phase 3 (échelle)** : réplica de lecture, CDN pour les assets, éventuellement 2 VPS derrière un load-balancer.

---

## 3. Stockage des documents (rappel technique)

- Les fichiers passent par **Spatie Media Library** → bascule vers S3 = **config, pas code**
  (`MEDIA_DISK=s3` + variables `AWS_*` déjà présentes dans `config/filesystems.php`).
- **Multi-tenant** : préfixer les chemins par tenant (ou bucket par tenant) pour ne jamais
  mélanger les fichiers de deux CFA.
- 🔴 **À corriger à cette occasion** : les collections sensibles `piece_identite` et
  `carte_vitale` (données NIR) sont aujourd'hui sur le disque **`public`** (URL publiques
  devinables). En prod : **bucket privé** + **URL signées temporaires**
  (`->getTemporaryUrl(now()->addMinutes(5))`). C'est le seul vrai correctif de code côté stockage.

---

## 4. RGPD / réglementaire (à verrouiller avant commercialisation)

- **Hébergement UE strict** (jamais de région US) — Scaleway/OVH/Hetzner OK.
- **Chiffrement au repos** des documents sensibles + politique de **rétention/purge légale**.
- **Isolation par tenant** = base du « registre des traitements » et du droit à l'effacement.
- ⚠️ **À vérifier sur les sources officielles CNIL** (ne pas se fier à la mémoire) :
  encadrement de l'usage du **NIR**, et pertinence éventuelle d'un hébergement **HDS**.
  Ce doc ne tranche pas ce point réglementaire.

---

## 5. Récap décisionnel

> **`stancl/tenancy` (schéma-par-CFA) + VPS Scaleway/Hetzner + Object Storage EU +
> Laravel Forge + domaine OVH.**
> Fiable, ~30–45 €/mois pour plusieurs clients, RGPD-solide, évolutif sans réécriture.

**Prochaines actions concrètes**
1. Réserver le domaine OVH + créer les comptes Scaleway/Hetzner + Forge.
2. Créer un bucket Object Storage EU (privé).
3. Lancer le chantier `stancl/tenancy` (§1) sur une branche dédiée.
4. Corriger le stockage des pièces sensibles (bucket privé + URL signées).
5. Vérifier les obligations CNIL/NIR sur sources officielles.
