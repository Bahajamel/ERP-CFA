# Prototype ERP CFA — maquette de validation

Maquette **statique** (données fictives) pour présenter la plateforme aux
différents départements et valider la direction du projet. Aucune logique, aucune
base de données — uniquement de l'interface.

## Lancer la maquette

**Option 1 — la plus simple :** double-cliquer sur `index.html` (s'ouvre dans le navigateur).

**Option 2 — sur localhost** (recommandé pour une démo) :

```powershell
# depuis la racine du projet
php -S 127.0.0.1:8080 -t prototype
# puis ouvrir http://127.0.0.1:8080
```

> Une connexion internet est nécessaire (Tailwind CSS est chargé via CDN).

## Comment présenter aux équipes

- Menu de gauche **ou** sélecteur « Connecté en tant que » (en haut à droite)
  pour passer d'un espace à l'autre : Direction, Commercial, Admission,
  Administratif & OPCO, Pédagogie, Finance, Qualité, RH, Marketing, Administration.
- Les espaces marqués **« Vision »** (RH, Marketing) sont hors V1 : ils montrent
  la cible à terme, pour recueillir l'avis des équipes.

## Modifier le contenu

Tout le contenu (KPI, tableaux, statuts) est dans **`assets/data.js`**.
On peut ajuster textes et chiffres sans toucher au reste. Le rendu est dans
`assets/app.js`, le style dans `assets/styles.css`.
