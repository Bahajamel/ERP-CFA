# Sprint 1 — Authentification, rôles & permissions

- **Période** : 30/06/2026 → 04/07/2026 (1 semaine)
- **Branche** : `feature/auth-roles` (depuis `develop`, merge par Pull Request)
- **Epics** : EPIC-01 (auth/rôles) + reste du EPIC-00 (layout, CI)
- **Répartition** : Claude implémente · Raslen relit et valide les PR
- **Review / démo** : vendredi 04/07

## 🎯 Sprint Goal

> Un utilisateur se connecte à l'ERP (en français) et **ne voit que les modules
> autorisés par son rôle**. L'administrateur peut gérer les utilisateurs et leurs
> rôles. Le socle est testé (Pest) et protégé par une CI.

## Stories engagées

| ID | Story | Pts | Statut |
|---|---|---|---|
| P0-01-1 | Connexion par identifiant + mot de passe (panel Filament `/admin`) | 3 | À faire |
| P0-01-4 | 10 rôles + permissions par module, attribution d'un rôle | 5 | À faire |
| P0-01-3 | CRUD utilisateur + **désactivation** (sans perte d'historique) | 3 | À faire |
| P0-01-5 | Chaque rôle ne voit que ses modules (accès au panel + navigation) | 5 | À faire |
| P0-00-3 | Layout / navigation de base + charte visuelle (vert maquette), FR | 3 | À faire |
| P0-00-4 | Tests Pest (règles d'accès) + CI GitHub Actions | 5 | À faire |

**Total engagé : 24 points.**

### Stretch (si le temps le permet)
| ID | Story | Pts |
|---|---|---|
| P0-01-2 | Authentification multifacteur (MFA) | 5 |
| P0-01-6 | Consulter connexions & actions sensibles (via activitylog) | 3 |

## Découpage technique (tâches)

1. **Branche** `feature/auth-roles` depuis `develop`.
2. **Rôles & permissions** (spatie) :
   - Enum/constantes des **10 rôles** + permissions par module.
   - `RolePermissionSeeder` (rôles + permissions + matrice d'accès du CDC §20).
   - `User` : trait `HasRoles`.
3. **Accès Filament par rôle** :
   - `User implements FilamentUser` → `canAccessPanel()`.
   - Navigation conditionnée au rôle (un commercial ne voit pas Finance, etc.).
4. **Gestion des utilisateurs** (`UserResource` Filament) :
   - Liste, création, édition, **activation/désactivation** (`is_active`).
   - Attribution du/des rôle(s).
   - Filtres (rôle, statut).
5. **Localisation FR** : `APP_LOCALE=fr` (déjà), publication/ajout des traductions
   Filament + messages de validation FR.
6. **Charte visuelle** : couleur primaire `brand` (vert émeraude) appliquée au panel.
7. **Qualité** :
   - Tests **Pest** : un rôle n'accède pas à un module interdit ; un compte
     désactivé ne peut pas se connecter ; l'admin peut créer un utilisateur.
   - **CI GitHub Actions** : `composer install` + migrations + `pest` sur PostgreSQL.
8. **Mise à jour** de l'`AdminUserSeeder` → rôle Administrateur.

## Definition of Done (rappel)

Voir `../README.md` §DoD. En particulier pour ce sprint :
- [ ] Les rôles & permissions sont vérifiés (données sensibles).
- [ ] Migrations incluses ; seeders idempotents.
- [ ] Tests Pest verts ; CI verte sur la PR.
- [ ] Interface en français.
- [ ] PR relue et mergée dans `develop`, `develop` testé après merge.

## Règles métier couvertes (CDC §20)

- Un utilisateur ne doit accéder qu'aux modules nécessaires à son rôle.
- Un commercial ne modifie pas les données financières.
- Un formateur n'accède pas aux informations financières.
- La finance ne modifie pas les évaluations pédagogiques.
- Désactiver un compte ne supprime pas son historique.

## Risques / points d'attention

- **Filament v5** : vérifier l'API exacte (`canAccessPanel`, navigation par rôle,
  resources) — version récente, à confirmer à l'implémentation.
- **CI PostgreSQL** : service Postgres dans GitHub Actions + variables d'env.
- Garder les seeders **idempotents** (rejouables sans doublon).

## Notes de review / rétro
*(à remplir en fin de sprint)*
