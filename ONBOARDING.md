# ONBOARDING — ERP CFA (à donner à Claude Code)

> **Pour l'agent Claude qui lit ce fichier :** ce document te permet de
> comprendre le projet **et** d'installer automatiquement l'environnement de
> développement sur une machine **Windows 11**, à l'identique de la machine de
> référence. Lis la section 1 pour le contexte, puis exécute la section 3 pas à
> pas. **Avant d'installer quoi que ce soit, vérifie ce qui est déjà présent
> (section 2)** et n'installe que ce qui manque. Préviens l'utilisateur que des
> fenêtres UAC « Autoriser cette application… » peuvent apparaître et qu'il devra
> cliquer « Oui » (tu ne peux pas cliquer à sa place).

---

## 1. Le projet en bref

**ERP CFA** = plateforme unique (ERP) pour un Centre de Formation d'Apprentis,
qui remplace les multiples logiciels et fichiers Excel dispersés. Tous les
départements (commercial, admission, administratif, scolarité, pédagogie,
finance, qualité, direction) travaillent au même endroit autour du **cycle de
vie de l'apprenant** :

`Candidat → Entreprise → Besoin → Matching → Admission → Documents → Contrat → OPCO → Dashboard`

**Méthode :** Scrum, sprints d'1 semaine, 2 développeurs. Le backlog et la
roadmap sont versionnés en Markdown.

**Documentation à lire (dans le repo) :**
- `docs/scrum/README.md` — process Scrum, rôles, Definition of Done.
- `docs/scrum/product-backlog.md` — epics + user stories (P0/P1/P2).
- `docs/scrum/sprint-roadmap.md` — découpage en sprints.
- `docs/architecture/vision-intelligente.md` — différenciateurs métier (financement, Qualiopi, risque rupture, IA).
- `docs/architecture/modele-de-donnees.md` — **modèle de données global** (tables, champs, statuts, relations). À lire avant de coder.

**Conventions importantes :**
- Interface **100 % en français** ; noms techniques (tables/colonnes) en anglais.
- CFA **mono-site** (pas de notion de campus).
- Git : `main` (stable) / `develop` (intégration) / `feature/*`. Jamais de merge direct dans `main`.

## Stack technique (imposée)

| Élément | Choix | Version de référence |
|---|---|---|
| Backend | Laravel (PHP) | Laravel 13, PHP 8.3 |
| UI métier/admin | Filament (Livewire) | Filament 5 |
| Base de données | PostgreSQL | 16 |
| RBAC | `spatie/laravel-permission` | — |
| Journalisation | `spatie/laravel-activitylog` | — |
| GED versionnée | `spatie/laravel-medialibrary` | — |
| Assets front | Node.js + Vite | Node 20+ |

---

## 2. Vérifier l'existant (à exécuter d'abord)

Exécute dans PowerShell et n'installe que ce qui renvoie « NON TROUVE » :

```powershell
foreach ($t in 'php','composer','psql','node','git','winget') {
  $c = Get-Command $t -ErrorAction SilentlyContinue
  if ($c) { "{0,-10} : {1}" -f $t, $c.Source } else { "{0,-10} : NON TROUVE" -f $t }
}
```

---

## 3. Installation de l'environnement (Windows 11, via winget)

> Prérequis : `winget`, `git` et `node` sont normalement déjà présents sur
> Windows 11. Sinon : `winget install Git.Git OpenJS.NodeJS.LTS`.

### 3.1 PHP 8.3 (sans droits admin)

```powershell
$ProgressPreference='SilentlyContinue'
winget install --id PHP.PHP.8.3 --scope user --silent --accept-package-agreements --accept-source-agreements --disable-interactivity
```

Localise le dossier d'installation (le chemin contient un suffixe winget variable) :

```powershell
$phpDir = (Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Recurse -Filter php.exe |
  Select-Object -First 1).DirectoryName
$phpDir   # note ce chemin, réutilisé plus bas
```

### 3.2 Activer les extensions PHP nécessaires

```powershell
$ini = "$phpDir\php.ini"
Copy-Item "$phpDir\php.ini-development" $ini -Force
$c = Get-Content $ini -Raw
$c = $c -replace ';\s*extension_dir\s*=\s*"ext"', 'extension_dir = "ext"'
foreach ($e in 'curl','fileinfo','gd','mbstring','openssl','pdo_pgsql','pgsql','zip','exif','bcmath','intl','sodium','pdo_sqlite','sqlite3') {
  if (Test-Path "$phpDir\ext\php_$e.dll") { $c = $c -replace "(?m)^;\s*extension\s*=\s*$e\s*$", "extension=$e" }
}
Set-Content $ini $c -Encoding UTF8
& "$phpDir\php.exe" -m   # vérifie que pdo_pgsql, mbstring, intl, zip... sont listés
```

### 3.3 Composer (via script officiel)

```powershell
$bin = "C:\Users\$env:USERNAME\bin"; New-Item -ItemType Directory -Force $bin | Out-Null
& "$phpDir\php.exe" -r "copy('https://getcomposer.org/installer','$env:TEMP\composer-setup.php');"
& "$phpDir\php.exe" "$env:TEMP\composer-setup.php" --install-dir=$bin --filename=composer.phar
Set-Content "$bin\composer.bat" "@echo off`r`nphp `"%~dp0composer.phar`" %*" -Encoding ASCII
```

### 3.4 PostgreSQL 16 (⚠️ UAC : cliquer « Oui »)

```powershell
winget install --id PostgreSQL.PostgreSQL.16 --silent --accept-package-agreements --accept-source-agreements --override "--mode unattended --unattendedmodeui none --superpassword postgres --serverport 5432 --enable_acledit 1"
```

> Mot de passe superutilisateur local : **postgres** (dev uniquement, à changer
> en recette/prod). Service attendu : `postgresql-x64-16` (Running).

### 3.5 Mettre à jour le PATH utilisateur

```powershell
$paths = @($phpDir, "C:\Users\$env:USERNAME\bin", "C:\Program Files\PostgreSQL\16\bin")
$cur = [Environment]::GetEnvironmentVariable("Path","User")
foreach ($p in $paths) { if ($cur -notlike "*$p*") { $cur = "$cur;$p" } }
[Environment]::SetEnvironmentVariable("Path", $cur, "User")
```

> **Important :** ferme et rouvre le terminal / l'IDE pour que `php`, `composer`
> et `psql` soient reconnus. Dans la même session, utilise les chemins complets.

### 3.6 Créer la base de données

```powershell
$env:PGPASSWORD = "postgres"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -h 127.0.0.1 -c "CREATE DATABASE erp_cfa ENCODING 'UTF8';"
```

---

## 4. Mettre le projet en route (après `git clone`)

```powershell
git clone <URL_DU_DEPOT> ERP-CFA
cd ERP-CFA
git checkout develop          # on travaille sur develop

composer install              # installe Laravel + Filament + spatie (depuis composer.json)
Copy-Item .env.example .env   # le .env.example est déjà pré-réglé (pgsql + fr)
php artisan key:generate
php artisan migrate            # crée les tables dans erp_cfa

npm install
npm run build                 # ou: npm run dev (en développement)

# Compte administrateur de dev (admin@cfa-v2s.fr / password)
php artisan db:seed --class=AdminUserSeeder
# (ou, en interactif : php artisan make:filament-user)

php artisan serve             # http://127.0.0.1:8000  (panel admin: /admin)
```

Vérifie que la page de connexion s'affiche **en français**.

---

## 5. Paramètres de référence

| Paramètre | Valeur (dev local) |
|---|---|
| Base de données | `erp_cfa` |
| Hôte / port | `127.0.0.1` / `5432` |
| Utilisateur DB | `postgres` |
| Mot de passe DB | `postgres` |
| URL app | `http://127.0.0.1:8000` |
| Panel admin | `http://127.0.0.1:8000/admin` |

---

## 6. Workflow Git (rappel pour démarrer une feature)

```powershell
git checkout develop
git pull origin develop
git checkout -b feature/<nom-de-la-feature>
# ... développement ...
git add .
git commit -m "Message clair"
git push origin feature/<nom-de-la-feature>
# puis ouvrir une Pull Request vers develop, relue par le coéquipier
```

---

## 7. Notes pour l'agent Claude

- N'installe que les outils manquants détectés en section 2.
- Les commandes winget en *scope machine* (PostgreSQL) déclenchent l'UAC :
  préviens l'utilisateur, il doit cliquer « Oui ».
- Si `php`/`composer` ne répondent pas après installation, c'est le PATH non
  rechargé : utilise les chemins complets ou demande à rouvrir le terminal.
- Ne modifie pas `main`. Travaille sur `develop` puis branche `feature/*`.
- En cas de doute sur le modèle de données, lis `docs/architecture/modele-de-donnees.md`
  et ne change pas les noms de tables/colonnes/statuts sans accord de l'équipe.
