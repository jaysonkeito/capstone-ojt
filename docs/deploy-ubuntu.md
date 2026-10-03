# OJT Tracker — Ubuntu deployment runbook (MIS Office, NORSU-BSC)

> **The office chose Docker** — use [`docs/deploy-docker.md`](deploy-docker.md) for the
> actual deployment (same domain, same server, everything in containers). This bare-metal
> Nginx/PHP/MySQL guide is kept as the fallback if the office server can't run Docker.

Target: the purchased domain **norsubscojt.online** (registered at Z.com, active to July 2027)
fronting the MIS Office's Ubuntu server. Stack: **Nginx + PHP 8.3-FPM + MySQL + Certbot
(Let's Encrypt)**, with systemd for the queue worker and cron for the scheduler. One server
hosts everything, matching how the app runs today.

> The Android app loads `https://norsubscojt.online`, and the kiosk PC can point at the same
> domain — so this one server serves the web app, the front-desk kiosks, and the interns'
> phones.

---

## 0. DNS first (Z.com client area)

In the Z.com panel → **My Domains → norsubscojt.online → Manage DNS / Nameservers**:

| Type | Host | Value | TTL |
|---|---|---|---|
| A | `@` | *the office server's PUBLIC IPv4* | 3600 |
| A | `www` | *same public IPv4* | 3600 |

- The public IP is what the office router/modem shows ("what is my IP" from a machine behind
  it). If the campus network has **no public IPv4** and can't do port forwarding, skip to
  **§6 Cloudflare Tunnel** — it needs no router changes and issues HTTPS by itself.
- DNS propagation can take up to a few hours. `nslookup norsubscojt.online` should return the
  office IP before continuing past §3.

## 1. Server packages (Ubuntu 22.04/24.04)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server unzip curl git \
  php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-gd php8.4-curl php8.4-zip php8.4-intl
# Composer + Node (for the Vite build):
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

## 2. MySQL + app user

```bash
sudo mysql
```
```sql
CREATE DATABASE cas_ojt_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ojt'@'localhost' IDENTIFIED BY 'STRONG-PASSWORD-HERE';
GRANT ALL PRIVILEGES ON cas_ojt_management.* TO 'ojt'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Application code

```bash
sudo mkdir -p /var/www/ojt-tracker && sudo chown $USER /var/www/ojt-tracker
# copy the project over (git clone when the repo exists, or scp/rsync the folder)
rsync -av --exclude node_modules --exclude vendor --exclude .zcode --exclude android \
  /path/to/D:/ojt-tracker/ user@server:/var/www/ojt-tracker/

cd /var/www/ojt-tracker
composer install --no-dev --optimize-autoloader
npm ci && npm run build && rm -rf node_modules   # keep the built assets, drop the tooling
cp .env.example .env
```

Production `.env` essentials (full file mirrors the local one):

```ini
APP_NAME="OJT Tracker"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://norsubscojt.online

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cas_ojt_management
DB_USERNAME=ojt
DB_PASSWORD=STRONG-PASSWORD-HERE

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan db:seed --force        # first deployment only — seeds staff + roster
chown -R www-data:www-data storage bootstrap/cache
```

## 4. Nginx site

`/etc/nginx/sites-available/ojt-tracker`:

```nginx
server {
    listen 80;
    server_name norsubscojt.online www.norsubscojt.online;
    root /var/www/ojt-tracker/public;

    index index.php;
    client_max_body_size 25m;          # journal photo uploads (5 MB) + template uploads

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/ojt-tracker /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

At this point **http://norsubscojt.online should open the login page** (HTTP only for now —
Certbot needs it).

## 5. HTTPS (Certbot) + background jobs

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d norsubscojt.online -d www.norsubscojt.online
# auto-renewal is installed with certbot; verify with:
sudo certbot renew --dry-run
```

Queue worker (review notifications, etc. run through the database queue):

```bash
sudo tee /etc/systemd/system/ojt-queue.service >/dev/null <<'UNIT'
[Unit]
Description=OJT Tracker queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
RestartSec=3
WorkingDirectory=/var/www/ojt-tracker
ExecStart=/usr/bin/php /var/www/ojt-tracker/artisan queue:listen --tries=3

[Install]
WantedBy=multi-user.target
UNIT
sudo systemctl enable --now ojt-queue
```

Scheduler (cron, `sudo crontab -e -u www-data`):

```
* * * * * cd /var/www/ojt-tracker && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## 6. Alternative: Cloudflare Tunnel (if the office has no public IP)

Campus servers are often behind NAT that MIS can't port-forward. A free Cloudflare Tunnel
solves both reachability *and* HTTPS in one step:

1. Add the domain to Cloudflare (free plan): at Z.com, switch nameservers to Cloudflare's.
2. On the server: `cloudflared tunnel create ojt-tracker`, route `norsubscojt.online` to
   `http://localhost:80`, run `cloudflared service install` (runs at boot).
3. Skip Certbot — Cloudflare terminates HTTPS at the edge. Keep `SESSION_SECURE_COOKIE=true`
   (the browser always sees HTTPS).
4. In Cloudflare SSL/TLS mode use **Flexible→Full** only if you also enable an origin cert;
   `Full (strict)` + Certbot on the origin is the cleanest combination if you want both layers.

The MIS Office can decide between a public IP (§5) and a tunnel (§6) — the app itself is
identical either way.

## 7. Kiosk PC + Android app after go-live

- Kiosk: `kiosk-station.bat` already has the production line commented — set
  `KIOSK_URL=https://norsubscojt.online/admin/kiosk` and log the kiosk account in once.
- Android: the shipped APK already points at `https://norsubscojt.online`. Dev builds can
  still target a PC via `CAP_SERVER_URL=http://<pc-ip>:8000 npx cap sync android`
  (see `docs/mobile-app.md`).

## 8. Deploying updates (after the first install)

```bash
cd /var/www/ojt-tracker
# bring the new code in (git pull / rsync), then:
composer install --no-dev --optimize-autoloader
npm ci && npm run build && rm -rf node_modules
php artisan migrate --force
php artisan config:clear && php artisan cache:clear && php artisan route:clear
sudo systemctl restart ojt-queue php8.4-fpm
```

## 9. Backups (do not skip)

Nightly cron as root (`sudo crontab -e`):

```
30 2 * * * mysqldump -u ojt -p'STRONG-PASSWORD-HERE' cas_ojt_management | gzip > /var/backups/ojt-$(date +\%F).sql.gz
0 3 * * * find /var/backups -name 'ojt-*.sql.gz' -mtime +14 -delete
```

The intern photos and uploaded templates live in `storage/app/` — include `/var/www/ojt-tracker/storage`
in whatever file backup the office already runs.

## 10. First-login checklist after go-live

1. Log in as admin → Settings: working hours, working days, OJT period start.
2. Templates: download each starter, re-upload the ones the office customizes (Weekly
   Progress Report, Personal Information, etc.).
3. Staff: create the real coordinator/supervisor accounts (default password = last name).
4. Kiosk PC: open the kiosk URL, scan a test intern's QR four times (AM In → AM Out → PM In
   → PM Out).
5. Install the APK on a phone, log in as an intern, run the offline test from
   `docs/mobile-app.md` (airplane-mode QR + offline journal sync).
