# Déploiement en production — Meridian CFA

Guide de mise en ligne de l'ERP. À jour au 2026-07-24.

> ⚠️ Deux processus permanents sont **indispensables** : sans le worker de file
> d'attente et le planificateur, les notifications, e-mails, alertes et la
> suspension des essais gratuits **ne partent jamais**. Voir §3.

---

## 1. Variables d'environnement de production

À positionner dans le `.env` du serveur (jamais commité) :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.fr

# Sécurité (cf. durcissement prod)
SESSION_SECURE_COOKIE=true          # cookie de session jamais transmis en clair
TRUSTED_PROXIES=10.0.0.1            # IP(s) du reverse-proxy, PAS « * » en prod

# File d'attente et planificateur reposent sur la base
QUEUE_CONNECTION=database

# E-mail réel (sinon les envois partent dans le log)
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...

# Signature électronique (si activée) — ne jamais commiter les clés
SIGNATURE_DRIVER=yousign
# YOUSIGN_API_KEY=...
```

En production, l'application force déjà le HTTPS sur les URL générées
(`URL::forceScheme` dans `AppServiceProvider`) et sert les en-têtes de sécurité
(`SecurityHeaders`). La porte de démonstration `/demo/admin` **n'existe pas**
hors `local` (garde `! app()->isProduction()`).

---

## 2. Installation / mise à jour

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force

# Premier déploiement uniquement : socle sans données de démo
php artisan db:seed --class=Database\\Seeders\\ProdBaseSeeder --force

# Caches de production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components
```

`ProdBaseSeeder` crée : organisation par défaut, rôles/permissions, **compte
administrateur** (`admin@cfa-v2s.fr`) et **compte éditeur**
(`editeur@meridian-cfa.fr`), indicateurs Qualiopi, missions CFA, OPCO.
**Changer ces mots de passe de démonstration avant ouverture.**

---

## 3. Processus permanents (obligatoires)

### 3.1 Worker de file d'attente

Les notifications (cloche), e-mails et traitements différés passent par la file
(`QUEUE_CONNECTION=database`). Un worker doit tourner en permanence, supervisé.

Exemple **Supervisor** (`/etc/supervisor/conf.d/meridian-worker.conf`) :

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

Après chaque déploiement : `php artisan queue:restart` (recharge le code).

### 3.2 Planificateur (cron)

Une seule entrée cron fait tourner toutes les tâches planifiées :

```cron
* * * * * cd /var/www/meridian && php artisan schedule:run >> /dev/null 2>&1
```

Tâches pilotées par le planificateur (`routes/console.php`) :

| Tâche | Heure | Rôle |
|---|---|---|
| `opco:flag-echeances` | 06:00 | versements OPCO en retard |
| `app:generer-alertes` | 06:15 | alertes transverses (dossiers, signatures, échéances) |
| `candidats:purger-corbeille` | 03:00 | purge RGPD après 30 j |
| `essai:suspendre-expires` | 02:00 | **suspend les CFA dont l'essai gratuit est échu** |
| `backup:clean` / `backup:run` | 01:30 / 01:45 | **sauvegarde base + fichiers** (disque `BACKUP_DISK`) |

---

## 4. Checklist go-live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] HTTPS actif ; `SESSION_SECURE_COOKIE=true` ; `TRUSTED_PROXIES` = IP du proxy
- [ ] Worker de file supervisé (§3.1) **en marche**
- [ ] Cron `schedule:run` **en place** (§3.2)
- [ ] Mots de passe des comptes seedés changés (admin + éditeur)
- [ ] E-mail (`MAIL_*`) configuré et testé
- [ ] **Sauvegardes** : `BACKUP_DISK` pointé vers un stockage **hors serveur**
      (ex. `s3`) et `pg_dump` présent dans le PATH. Vérifier avec
      `php artisan backup:run` puis `php artisan backup:list`. *(Le planificateur
      lance `backup:run`/`backup:clean` chaque nuit — cf. §3.2.)*
- [ ] Identité juridique renseignée sur les mentions légales de la vitrine
      (placeholders `[à compléter]`)

---

## 5. Points connus restant à traiter

- **Content-Security-Policy** : seul `frame-ancestors 'self'` est posé (sûr,
  anti-clickjacking). La CSP complète (`script-src`/`style-src`) reste à
  construire, testée écran par écran — elle casserait Filament/Livewire sinon.
- Voir l'audit des manques pour le reste (connecteurs OPCO/DECA, reporting
  réglementaire, etc.).