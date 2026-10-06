# OJT Tracker — Docker deployment runbook (MIS Office, NORSU-BSC)

Target: **norsubscojt.online** on the MIS Office Ubuntu server, deployed as Docker
containers — Nginx, PHP-FPM (this repo's image), MySQL, the queue worker, and the scheduler
all run as one `docker compose` stack. This is the office's chosen path; the bare-metal
variant stays in `docs/deploy-ubuntu.md` for reference.

Everything the stack needs lives in the repo: `Dockerfile`, `docker-compose.yml`,
`docker-compose.tls.yml` (HTTPS override), `docker/nginx/`, `docker/php/`,
`docker/app/entrypoint.sh`.

```
                 ┌──────────────── docker compose ────────────────┐
  interns' app ──┤  web (nginx) → app (php-fpm) → db (MySQL 8)    │
  kiosk PC     ──┤        │           ├─ queue (worker)           │
  browser ───────┘        └─ public volume   └─ scheduler           │
                 └──────────── volumes: db-data, app-storage ──────┘
```

The Android app and kiosk both load `https://norsubscojt.online` — this one server serves
them plus the browser UI.

---

## 0. Server prerequisites (Ubuntu 22.04/24.04)

```bash
# Docker Engine + Compose plugin:
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER     # log out/in afterwards
docker compose version            # sanity check
```

No PHP, Node, or Nginx install needed on the host — those are in the containers.

## 0b. Server access: Tailscale (MIS Office requirement)

The MIS Office reaches servers through **Tailscale** — a private mesh VPN that gives every
device a stable `100.x.y.z` address reachable from any network the owner logs into. Set it
up on both ends the morning of the deployment:

**On the Ubuntu server** (needs outbound internet; run on-site Tuesday):

```bash
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
# prints a login URL — open it and sign in with the MIS tailnet account
tailscale ip -4        # note the server's 100.x.y.z address
```

**On the Windows laptop** (install from tailscale.com, sign in to the same tailnet):

```bash
ssh <user>@100.x.y.z            # or the MagicDNS name, e.g. ojt-server
```

After that you can administer the server from home too — same-internet is only needed for
the first login if the tailnet isn't set up yet. Management traffic (SSH) never touches the
public internet, which is exactly why MIS prefers it.

Two practical notes for this project:

- **The app itself still serves publicly** via `norsubscojt.online` (DNS + Certbot, or the
  Cloudflare Tunnel in §4). Tailscale is for *administering* the server, not for serving
  the app.
- **Testing the Android app against the office server before DNS/HTTPS is live**: rebuild a
  dev APK pointed at the server's tailnet IP —
  `CAP_SERVER_URL=http://100.x.y.z npx cap sync android` then `./gradlew assembleDebug`.
  The config enables cleartext HTTP for exactly this dev case.

## 1. DNS (Z.com client area)

| Type | Host | Value | TTL |
|---|---|---|---|
| A | `@` | *office server's public IPv4* | 3600 |
| A | `www` | *same IPv4* | 3600 |

`nslookup norsubscojt.online` must return the office IP before doing the HTTPS step.
**If the campus has no public IP / no port forwarding**, skip Certbot entirely and use the
Cloudflare Tunnel path in §5 — it needs no DNS A record at all (Cloudflare manages it).

## 2. Put the code + .env on the server

```bash
sudo mkdir -p /opt/ojt-tracker && sudo chown $USER /opt/ojt-tracker
# copy the project folder over (rsync/scp/USB — everything except node_modules,
# vendor, android). Then:
cd /opt/ojt-tracker
cp .env.production .env
```

Edit `.env`: fill in the FILL-IN values (the database password), then generate the
encryption key **on the host** — the image ships without a `.env` (deliberately, see
`.dockerignore`), so `key:generate` inside a throwaway container would write nowhere:

```bash
# fill DB_PASSWORD first, then replace the empty APP_KEY= line:
sed -i "s|^APP_KEY=$|APP_KEY=base64:$(openssl rand -base64 32)|" .env
grep '^APP_KEY=' .env        # must show base64:…
```

The production values that must differ from development:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://norsubscojt.online
DB_CONNECTION=mysql
DB_HOST=db                  # ← the compose service name, not 127.0.0.1
DB_DATABASE=cas_ojt_management
DB_USERNAME=ojt
DB_PASSWORD=<strong password>
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
```

## 3. Build + first run

```bash
docker compose build                      # builds the app image (assets + vendor baked in)
docker compose up -d db                   # start the database first
docker compose up -d                      # brings up app, web, queue, scheduler
```

The entrypoint (`docker/app/entrypoint.sh`) seeds the Nginx volume, waits for MySQL, caches
config/routes/views, and fixes permissions on every start — migrations and data loading stay
deliberate manual steps so the team controls schema changes.

Then load the data — **path 1 or path 2, not both**:

**Path 1 — fresh install** (empty database, no existing OJT data):

```bash
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --force
```

**Path 2 — import the real data** (chosen for go-live: carries the intern roster, the
September attendance records, accounts, and the customized CAS templates). Copy the three
files from the laptop's `storage/ojt-prod/` to the server, then:

```bash
docker compose exec -T db sh -c 'exec mysql -h127.0.0.1 -uojt -p"$MYSQL_PASSWORD" cas_ojt_management' < ojt-prod-dump.sql
docker compose exec -T db sh -c 'exec mysql -h127.0.0.1 -uojt -p"$MYSQL_PASSWORD" cas_ojt_management' < import-cleanup.sql
# restore intern photos / uploaded files into the app-storage volume
# (check the exact volume name with `docker volume ls | grep app-storage`):
docker run --rm -v "$PWD:/src" -v ojt-tracker_app-storage:/data alpine tar xzf /src/ojt-storage.tgz -C /data
```

The dump includes the full schema *and* the `migrations` table — import it into an
**empty** database (no `migrate` first; the import creates every table and leaves the
schema exactly as the dev machine had it). `import-cleanup.sql` strips the local test
accounts and development session/cache rows and is safe to re-run. `db:seed` is **not**
run on this path.

**Check:** `docker compose ps` shows db healthy + 4 services running; open
`http://norsubscojt.online` → the login page.

## 4. HTTPS — two paths, pick one

### Path A — public IP + Let's Encrypt (Certbot profile)

```bash
# with the stack still on HTTP (default.conf answers the ACME challenges):
docker compose --profile tls up -d certbot
docker compose run --rm certbot certonly --webroot -w /var/www/public \
  -d norsubscojt.online -d www.norsubscojt.online \
  --email mis@norsu.edu.ph --agree-tos --no-eff-email

# switch Nginx to the TLS vhost (adds 443, mounts the certs):
docker compose -f docker-compose.yml -f docker-compose.tls.yml --profile tls up -d web
```

Certbot renews automatically every 12 h; renewal rewrites the live certs and Nginx picks
them up within its 30 s window (or `docker compose exec web nginx -s reload`).

### Path B — Cloudflare Tunnel (no public IP, HTTPS included)

1. Add the domain to Cloudflare (free): at Z.com switch the nameservers to Cloudflare's.
2. In the Cloudflare dashboard → Zero Trust → Networks → Tunnels: create a tunnel for
   `norsubscojt.online` → service `http://web:80`, copy the token.
3. Add `CLOUDFLARE_TUNNEL_TOKEN=<token>` to `.env`, then:
   ```bash
   docker compose --profile tunnel up -d cloudflared
   ```
   No ports open on the office router; HTTPS terminates at Cloudflare's edge. Remove the
   `ports: 80:80` exposure if the office wants the site reachable only through the tunnel.

Either way the app URL stays `https://norsubscojt.online`.

## 5. Deploying an update

```bash
cd /opt/ojt-tracker
# replace the source (git pull / rsync), then:
docker compose build
docker compose run --rm app php artisan migrate --force    # when migrations exist
docker compose up -d                                       # restarts on the new image
```

## 6. Backups (do not skip)

Nightly cron as root (`sudo crontab -e`):

```
30 2 * * * docker compose -f /opt/ojt-tracker/docker-compose.yml exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" cas_ojt_management' | gzip > /var/backups/ojt-$(date +\%F).sql.gz
45 2 * * * docker run --rm -v ojt-tracker_app-storage:/data -v /var/backups:/backup alpine tar czf /backup/ojt-storage-$(date +\%F).tgz -C /data .
0 3 * * * find /var/backups -name 'ojt-*' -mtime +14 -delete
```

The second line captures intern photos and uploaded templates (the `app-storage` volume).

## 7. Useful commands

| Task | Command |
|---|---|
| Tail app logs | `docker compose logs -f app queue` |
| Artisan/tinker | `docker compose run --rm app php artisan tinker` |
| Sync intern passwords after name corrections | `docker compose run --rm app php artisan ojt:sync-intern-passwords` |
| Restart after config change | `docker compose restart app queue scheduler` |
| Enter MySQL | `docker compose exec db mysql -uojt -p cas_ojt_management` |

## 8. First-login checklist after go-live

1. Log in as admin → Settings: verify working hours, working days, OJT period start
   (imported with the data — check rather than re-enter).
2. Templates: verify the customized CAS starters are listed per college (imported with the
   data — the template files themselves came across in `ojt-storage.tgz`).
3. Staff: verify the coordinator/supervisor/dean accounts are present (imported); create
   any that are missing (default password = last name).
4. Kiosk PC: `KIOSK_URL=https://norsubscojt.online/admin/kiosk` in `kiosk-station.bat`,
   scan a **real intern's** QR four times (AM In → AM Out → PM In → PM Out) — the local
   test accounts were stripped by `import-cleanup.sql`. Delete those four scans
   afterwards from the intern's Duty History if you don't want them counted.
5. Install the APK on a phone, run the offline test from `docs/mobile-app.md`.
