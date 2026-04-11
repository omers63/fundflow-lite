# Deploying Fundflow to Hostinger

This document combines general Hostinger guidance with a **VPS-focused** production runbook for this application (Laravel 13, PHP **8.3+**, Filament, scheduled commands).

---

## 1. Choosing Hostinger hosting

| Option                   | When to use                                                                                                                                                              |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **VPS (KVM)**            | **Recommended** for this project: SSH, Composer, correct `public` document root, cron, queues without arbitrary limits.                                                  |
| **Web hosting (shared)** | Only if the plan offers **PHP 8.3+**, **SSH**, **Composer**, and document root can point to **`public/`**. Often limiting for queues, long jobs, and `composer install`. |

**Requirement:** `composer.json` specifies `"php": "^8.3"`. Confirm the panel shows **PHP 8.3 or 8.4**, not only 8.2.

The sections below assume **Hostinger VPS** (typically **Ubuntu 22.04/24.04**).

---

## 2. Prepare the application (local or CI)

1. **Front-end assets** (Vite): `npm ci` then `npm run build` before or on the server.
2. Do **not** deploy `node_modules`, local `.env`, or (optionally) `.git` if you ship a tarball.
3. Prefer **Git** on the server (`git clone` / `git pull`) over manual ZIP uploads.

**PHP extensions** commonly needed: openssl, pdo, mbstring, tokenizer, xml, ctype, json, bcmath, fileinfo, curl; enable **gd** (or imagick) if PDF/image features error.

---

## 3. VPS: first boot

- Use **SSH** (root or sudo user; SSH keys recommended).
- Point the domain **A record** to the VPS IP.
- Update packages:

```bash
sudo apt update && sudo apt upgrade -y
```

---

## 4. VPS: install stack (Ubuntu)

```bash
sudo apt install -y nginx mariadb-server php8.3-fpm php8.3-cli php8.3-mysql \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd \
  php8.3-intl unzip git
```

Install **Composer** from the official instructions: [https://getcomposer.org/download/](https://getcomposer.org/download/) (e.g. into `/usr/local/bin/composer`).

---

## 5. VPS: database

```bash
sudo mysql_secure_installation
sudo mysql -e "CREATE DATABASE fundflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'fundflow'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';"
sudo mysql -e "GRANT ALL ON fundflow.* TO 'fundflow'@'localhost'; FLUSH PRIVILEGES;"
```

Mirror credentials in `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Production should use **MySQL/MariaDB**, not SQLite.

---

## 6. VPS: application path and ownership

Example application root: `/var/www/fundflow` (web root must be **`public/`**).

```bash
sudo mkdir -p /var/www/fundflow
sudo chown -R $USER:www-data /var/www/fundflow
```

Clone or pull the repository into `/var/www/fundflow`.

---

## 7. Laravel setup (run in project root on the server)

```bash
cd /var/www/fundflow
cp .env.example .env
nano .env   # APP_URL, DB_*, APP_DEBUG=false, mail, Twilio, etc.
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```

**Permissions:**

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

---

## 8. VPS: Nginx (document root = `public`)

Replace `yourdomain.com` and verify the PHP socket (`php8.3-fpm.sock`):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/fundflow/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable and reload:

```bash
sudo ln -s /etc/nginx/sites-available/fundflow /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

**Critical:** the site **root** must be `.../public`, not the repository root.

---

## 9. HTTPS (Let’s Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

Set `APP_URL=https://yourdomain.com` and run `php artisan config:cache` again. If traffic passes through Hostinger’s proxy, configure Laravel **TrustProxies** / URL forcing so Filament and redirects use HTTPS correctly.

---

## 10. Scheduler (required for Fundflow)

`routes/console.php` registers scheduled commands (delinquency, contributions, loan notifications/applications, settlements, **`fund:reconcile`** daily/monthly, etc.). Laravel expects **one** cron entry.

As user `www-data` (or the user that runs the app):

```bash
sudo crontab -e -u www-data
```

Add:

```cron
* * * * * cd /var/www/fundflow && php artisan schedule:run >> /dev/null 2>&1
```

---

## 11. Queues (optional)

If `.env` uses `QUEUE_CONNECTION=database` or `redis`, run `php artisan queue:work` under **Supervisor** so workers restart after reboot. If you use `sync`, no worker is required.

---

## 12. Shared hosting notes (non-VPS)

If you must use **shared** Hostinger web hosting instead of a VPS:

- Set **document root** to the Laravel **`public`** directory (or use their Laravel wizard if available).
- Run `composer install --no-dev` via SSH or upload `vendor` from a matching environment.
- Add the same **cron** line for `schedule:run` if cron is allowed.
- Queue workers are often unavailable; prefer `sync` or upgrade to VPS.

---

## 13. Production checklist

- [ ] PHP **8.3+** (CLI and FPM match)
- [ ] Web **root** = **`public`**
- [ ] `composer install --no-dev`
- [ ] `.env` complete; `php artisan key:generate`
- [ ] `php artisan migrate --force` (and seeders if required, e.g. Shield permissions)
- [ ] `php artisan storage:link`
- [ ] Writable **`storage/`** and **`bootstrap/cache/`**
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `php artisan config:cache`, `route:cache`, `view:cache`, `filament:optimize`
- [ ] Cron: `* * * * * ... schedule:run`
- [ ] SSL + `APP_URL` with `https`
- [ ] Twilio / mail keys in `.env` as needed

---

## 14. Routine deploy (VPS)

```bash
cd /var/www/fundflow
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build   # if assets are built on the server
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
sudo systemctl reload php8.3-fpm
```

---

## 15. Security and operations (VPS)

**Firewall (example with UFW):**

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

**Hostinger hPanel:** use **snapshots** before major upgrades; configure **backups** (e.g. nightly `mysqldump` of `fundflow` and copies of `storage/app` if uploads are local).

---

## 16. Integrations referenced in `.env`

- **Twilio** (`laravel-notification-channels/twilio`)
- **Mail** for notifications
- User uploads: `storage/app/public` + `storage:link`; consider object storage later if needed

---

_Generated from internal deployment notes for Hostinger (general + VPS). Adjust paths, PHP version, and domain names for your server._
