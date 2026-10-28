# Railway setup (one-time, manual)

Everything code-side is done (Dockerfile, settings.railway.php, predeploy script,
CI workflow). This is the dashboard part, done once at railway.app.

## 1. Create the project
1. New Project → Deploy from GitHub repo → `FLoRyN1994/DrupalHealthMonitor`.
2. Railway detects the `Dockerfile` and builds the `app` service from it.
3. Rename the service to `app` (Settings → name).

## 2. Add the database
1. In the project, **+ New** → **Database** → **MySQL**.
2. Nothing else to configure; Railway creates `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`,
   `MYSQLUSER`, `MYSQLPASSWORD` on that service automatically.

## 3. Wire the app's variables
On the **app** service → **Variables**, add:

| Variable | Value |
| --- | --- |
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
| `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USER` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `DRUPAL_HASH_SALT` | a random 64-char string — generate with `openssl rand -hex 32` |
| `DRUPAL_TRUSTED_HOST` | `health-monitor.tofdev.com` |
| `DRUPAL_ADMIN_PASS` | a strong password (only used on the very first deploy) |
| `RAILWAY_ENVIRONMENT` | Railway sets this automatically — don't add it manually |

`${{MySQL.MYSQLHOST}}`-style references pull the value live from the MySQL service, so
nothing is typed by hand or duplicated.

## 4. Volume for uploaded files
**app** service → **Volumes** → **+ New Volume** → mount path `/var/www/html/web/sites/default/files`.
Without this, uploaded files (images, the AI report PDFs later) are lost on every deploy.

## 5. Pre-deploy command
**app** service → **Settings** → **Deploy** → **Pre-Deploy Command**:
```
sh scripts/railway-predeploy.sh
```
This runs `drush deploy` on every deploy after the first, and installs the site from
`config/sync` on the very first one.

## 6. Networking
**app** service → **Settings** → **Networking**:
- Generate a `*.up.railway.app` domain first and confirm the site loads there.
- Then **+ Custom Domain** → `health-monitor.tofdev.com`.
- Railway shows a CNAME and a TXT record — create both at the tofdev.com DNS provider.
  Both are required; the domain stays unverified with only one.
- Wait for the green check mark (usually under an hour, up to 72h for DNS propagation).
  SSL is issued automatically once verified.
- **Gotcha:** both the generated and custom domain default their target port to 80,
  not the Dockerfile's `EXPOSE 8080`/`$PORT`. If the site loads but shows the stock
  "Welcome to nginx!" page instead of Drupal, the domain is routing to the wrong port.
  Fix each domain's **Target Port** to `8080` (Settings → Networking, or
  `railway domain update <domain> --port 8080 --service app`).

## 7. Cron service (add once drush cron work exists — M3+)
**+ New** → **Empty Service**, same GitHub repo, same Dockerfile.
- **Settings → Deploy → Start Command:** `drush cron`
- Copy the same `DB_*` and `DRUPAL_*` variables as the app service (or use a shared
  variable group: project **Settings → Shared Variables**).
- **Settings → Cron Schedule:** `*/5 * * * *`
- No public networking and no volume needed on this service.

## 8. Enable Wait for CI
**app** service → **Settings → Deploy**, toggle **Wait for CI**. It only appears once
`.github/workflows/ci.yml` has an `on: push` trigger for `main` — it already does.
Now Railway deploys `main` only after CI (PHPCS, PHPStan, PHPUnit, Docker build) passes.

## First deploy checklist
- [ ] app service builds successfully from the Dockerfile
- [ ] MySQL service added, variables wired with `${{MySQL.*}}` references
- [ ] Volume mounted on `web/sites/default/files`
- [ ] Pre-deploy command set
- [ ] Site loads on the `*.up.railway.app` domain
- [ ] Custom domain verified, HTTPS working on health-monitor.tofdev.com
- [ ] Wait for CI enabled
- [ ] Cron service added (once there's a cron job worth running)
