# Mise en ligne pilote — Oracle Cloud Always Free

Guide de déploiement **gratuit** pour une phase de test réelle par un CFA pilote.
Ce n'est pas une production commerciale : voir §15 pour le passage au payant.
Complète `docs/DEPLOIEMENT.md` (contraintes générales worker + cron).

> ⚠️ Deux processus permanents restent **indispensables** : sans le worker de
> file (`queue:work`) et le planificateur (`schedule:run`), aucun e-mail, aucune
> notification, aucune suspension d'essai ni sauvegarde ne partent. Voir §6 et §7.

---

## 1. Solution recommandée & pourquoi Oracle convient

**Oracle Cloud Always Free — forme ARM Ampere A1 Flex (jusqu'à 4 OCPU / 24 Go RAM),
Ubuntu 22.04 LTS, région UE.**

- Vrai serveur Linux : PHP 8.3, PostgreSQL, Nginx, Supervisor, cron — les deux
  processus permanents tournent sans restriction.
- L'ARM Ampere gratuit (24 Go) encaisse `npm run build` (Vite/esbuild) et Filament
  sans peine. Tout le stack (PHP / PostgreSQL / Node) est compatible ARM.
- 200 Go de stockage bloc gratuit, 10 To de sortie/mois, IP publique réservée
  gratuite.

> Choisir une **région UE** (Paris, Marseille, Francfort) dès la création de la
> *tenancy* : la région est quasi définitive, et les données CFA relèvent du RGPD.
> Pour le pilote, démarrer avec des **données anonymisées** (cf. §12).

## 2. Limites & risques du gratuit

| Risque | Détail | Parade |
|---|---|---|
| Récupération des instances inactives | Oracle peut réclamer une VM Always Free ARM jugée « idle ». | Trafic minimal + monitoring uptime ; migrer vers payant avant le vrai lancement. |
| Pas de SLA | Aucune garantie de disponibilité. | Acceptable pour un pilote, pas pour du commercial. |
| iptables verrouillé | Les images Oracle bloquent 80/443 même après ouverture du pare-feu réseau. | Ouvrir les **deux** couches (cf. §7). |
| Capacité ARM parfois indisponible | « Out of capacity » à la création. | Réessayer / autre AD, ou script de retry. |
| Serveur unique | DB + app + fichiers sur la même VM. | Backups **hors serveur** obligatoires (§11). |

## 3. Prérequis serveur exacts

- OS : Ubuntu 22.04 LTS (ou 24.04).
- PHP 8.3 + PHP-FPM (repo `ppa:ondrej/php`).
- PostgreSQL 15/16 + client (`pg_dump` pour les backups).
- Nginx, Composer 2, Node.js 22 + npm, Supervisor, cron, Certbot.

**Extensions PHP requises** (déduites des dépendances réelles) :

```
php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml
php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl
php8.3-fileinfo php8.3-exif
```

- `gd` : dompdf (fiche besoin), fpdi/fpdf (surcharge CERFA), media-library.
- `zip` + client PostgreSQL : spatie/laravel-backup.
- `pgsql/pdo_pgsql`, `mbstring`, `xml/dom`, `curl`, `bcmath`, `intl`,
  `fileinfo`, `exif`.

## 4. Variables d'environnement (`.env` de test public)

```dotenv
APP_NAME="Meridian CFA"
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # php artisan key:generate
APP_URL=https://test.meridian-cfa.fr

# Sécurité prod
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=127.0.0.1      # (ranges Cloudflare si proxy devant)

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=meridian
DB_USERNAME=meridian
DB_PASSWORD=***          # fort, jamais dans Git

# File + planificateur en base
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

# E-mail réel (sinon rien ne part) — cf. §10
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=***
MAIL_PASSWORD=***
MAIL_FROM_ADDRESS="no-reply@meridian-cfa.fr"
MAIL_FROM_NAME="Meridian CFA"

# Backups hors serveur — cf. §11
BACKUP_DISK=s3
BACKUP_NOTIFICATION_EMAIL=d.phoulevang@cfa-v2s.fr
AWS_ACCESS_KEY_ID=***
AWS_SECRET_ACCESS_KEY=***
AWS_DEFAULT_REGION=eu-paris-1
AWS_BUCKET=meridian-backups
AWS_ENDPOINT=https://<compartiment>.compat.objectstorage.eu-paris-1.oraclecloud.com
AWS_USE_PATH_STYLE_ENDPOINT=true
```

## 5. Configuration Nginx

```nginx
server {
    listen 80;
    server_name test.meridian-cfa.fr;
    root /var/www/meridian/public;

    index index.php;
    charset utf-8;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    client_max_body_size 25M;          # imports de documents/pièces

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS on;         # Laravel voit la requête comme sécurisée
        include fastcgi_params;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
```

Certbot ajoute ensuite le bloc `listen 443 ssl` + la redirection 80→443.

## 6. Supervisor (worker de file — OBLIGATOIRE)

`/etc/supervisor/conf.d/meridian-worker.conf` :

```ini
[program:meridian-worker]
command=php /var/www/meridian/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/meridian
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/meridian/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start meridian-worker
```

Après chaque déploiement : `php artisan queue:restart`.

## 7. Cron (planificateur) + pare-feu Oracle

Cron :

```cron
* * * * * cd /var/www/meridian && php artisan schedule:run >> /dev/null 2>&1
```

Déclenche (déjà câblé dans `routes/console.php`) : alertes OPCO/dossiers, purge
RGPD, suspension des essais échus, backups nocturnes.

**Pare-feu — les DEUX couches** :

```bash
# 1) VCN -> Security List : autoriser 80 et 443 (ingress 0.0.0.0/0) dans la console Oracle
# 2) iptables de la VM (bloqué par défaut sur les images Oracle) :
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80  -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

## 8. HTTPS

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d test.meridian-cfa.fr --redirect
```

Renouvellement auto (timer systemd). Pré-requis : le DNS `test.meridian-cfa.fr`
pointe vers l'IP publique réservée de la VM. En production, `AppServiceProvider`
force déjà `https` et `SecurityHeaders` pose les en-têtes (HSTS sur https).

## 9. Stratégie e-mail (pilote)

| Option | Gratuit | Avantage | Limite |
|---|---|---|---|
| **Brevo** (reco) | 300 e-mails/jour | Mise en route immédiate, SMTP simple | Volume limité, sender à valider |
| Microsoft 365 SMTP (`@cfa-v2s.fr`) | inclus si licence | Domaine déjà réputé | Quotas M365, auth moderne à gérer |
| Amazon SES | ~0 € (usage) | Fiable, scalable | Sortie du sandbox + DKIM |
| Postmark | 100/mois d'essai | Excellente délivrabilité | Faible quota gratuit |

Pour un pilote : Brevo 300/j suffit. Configurer **SPF + DKIM** sur
`meridian-cfa.fr` pour la délivrabilité.

## 10. Stratégie backup

`spatie/laravel-backup` est déjà en place. Une sauvegarde sur le disque de la VM
ne protège de rien → cible S3-compatible **hors serveur**, gratuit :

- **Oracle Object Storage** (Always Free 20 Go, API S3-compatible) — même cloud,
  offsite du boot volume. Le plus cohérent.
- Alternatives : Cloudflare R2 (10 Go) ou Backblaze B2 (10 Go), S3-compatibles.

Couvre la base PostgreSQL (`pg_dump`) + les fichiers (documents privés, pièces).
Vérifier : `which pg_dump`, puis `php artisan backup:run` et
`php artisan backup:list`. Le cron lance `backup:clean`/`backup:run` chaque nuit.

## 11. Commandes exactes de déploiement

```bash
# — Système
sudo apt update && sudo apt -y upgrade
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt -y install nginx postgresql postgresql-client supervisor cron unzip git \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl php8.3-fileinfo php8.3-exif
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt -y install nodejs

# — Base
sudo -u postgres psql -c "CREATE USER meridian WITH PASSWORD '***';"
sudo -u postgres psql -c "CREATE DATABASE meridian OWNER meridian;"

# — Application
cd /var/www && sudo git clone <repo> meridian && cd meridian
git checkout main            # branche de release
cp .env.example .env         # puis renseigner le .env de prod (§4)
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan db:seed --class="Database\Seeders\ProdBaseSeeder" --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:cache-components
sudo chown -R www-data:www-data /var/www/meridian/storage /var/www/meridian/bootstrap/cache
```

Puis : Nginx (§5) → Certbot (§8) → Supervisor (§6) → cron (§7).

## 12. Sécurité minimale (même en test)

- `APP_DEBUG=false`, `APP_ENV=production` (la porte `/demo/admin` disparaît
  automatiquement hors `local`).
- **Changer les mots de passe seedés** : `admin@cfa-v2s.fr` et
  `editeur@meridian-cfa.fr` (défaut `password`).
- MFA déjà obligatoire pour le rôle Administrateur.
- Documents privés sur disque privé + liens signés (déjà en place).
- Ne pas exécuter les seeders de démo (`DemoFicheBesoin*`) ; données anonymisées.
- Aucun secret dans Git (`.env` hors dépôt).

## 13. Procédure de rollback

1. Avant chaque déploiement : `php artisan backup:run` (snapshot DB+fichiers) —
   ou `pg_dump` dédié.
2. Le code est taggé : `git tag v-pilote-YYYYMMDD` avant migration.
3. En cas d'échec :
   - Code : `git checkout <tag précédent>` + `composer install --no-dev` + caches.
   - Base : `php artisan migrate:rollback` si réversible, sinon restaurer le dump.
   - `php artisan queue:restart`.

## 14. Checklist de test avant de donner l'accès au CFA

- [ ] `https://test.meridian-cfa.fr` répond en HTTPS (cadenas), redirection 80→443.
- [ ] En-têtes de sécurité présents (`curl -I` → HSTS, X-Frame-Options…).
- [ ] Connexion admin + parcours MFA OK ; mots de passe seedés changés.
- [ ] Créer un candidat/entreprise, générer un PDF (fiche besoin) → s'ouvre.
- [ ] E-mail réel reçu (ex. invitation inscription) — worker actif.
- [ ] Notification cloche apparaît (queue traitée).
- [ ] `php artisan schedule:list` correct ; une tâche planifiée s'exécute.
- [ ] `php artisan backup:run` → archive visible sur l'Object Storage (offsite).
- [ ] `/demo/admin` absent (404) en production.
- [ ] Documents privés non accessibles en URL directe.

## 15. Passage à la vraie production payante

- Hébergement avec SLA (VPS payant piloté par Forge/Ploi, ou managé) +
  environnement de staging + CI/CD.
- PostgreSQL managé (sauvegardes PITR, réplication) séparé de l'app.
- Redis pour cache/queue/sessions (perf) + Laravel Horizon (supervision file).
- E-mail : domaine authentifié complet (SPF/DKIM/DMARC), fournisseur à volume.
- Monitoring/alerting (uptime, Sentry, logs centralisés), CDN.
- RGPD : registre des traitements, DPA hébergeur, région UE confirmée,
  purge/consentements.