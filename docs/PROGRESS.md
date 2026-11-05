# Progress

Legend: `[ ]` todo · `[~]` in progress · `[x]` done
Update this file at the end of every session (see AGENTS.md).

## M1 — Foundation
- [x] DDEV project (PHP 8.4, MySQL 8.4), Drupal 11.4 via `drupal/recommended-project`
- [x] Git repo, `.gitignore`, push to GitHub (github.com/FLoRyN1994/DrupalHealthMonitor)
- [x] Config sync directory `config/sync`, first export
- [x] Dev tooling: drush, phpstan + phpstan-drupal, coder (PHPCS), drupal-rector, phpunit (via drupal/core-dev)
- [x] `phpstan.neon`, `phpcs.xml`
- [x] GitHub Actions CI: PHPCS, PHPStan, PHPUnit, Docker build (.github/workflows/ci.yml)
- [x] README skeleton

## M1b — Deployment (do right after M1, deploy early and often)
- [x] `Dockerfile` (PHP 8.4 FPM + nginx) + `.dockerignore`
- [x] `settings.php` reads DB, hash salt, trusted hosts from env vars (settings.railway.php)
- [x] Railway project `health-monitor`: `app` service (linked to GitHub repo, `main`),
  `MySQL` service, volume on `app` at `web/sites/default/files`, `app` variables wired
  (`DB_*` as `${{MySQL.*}}` refs, `DRUPAL_HASH_SALT`, `DRUPAL_ADMIN_PASS`,
  `DRUPAL_TRUSTED_HOST`) — all via `railway` CLI
- [x] Railway dashboard-only steps: pre-deploy command `sh scripts/railway-predeploy.sh`
  on `app`, Wait for CI toggle on `app`
- [x] First deploy triggered and verified on the `*.up.railway.app` domain
  (`app-production-9474.up.railway.app`, HTTP 200)
- [x] Custom domain health-monitor.tofdev.com (CNAME + TXT), HTTPS verified
  (certificate valid, DNS propagated)
- [x] Railway `cron` service (same image, `drush cron` every five minutes)
- [x] CI workflow triggers on `push` to `main` and on pull requests

## M2 — Data model
- [x] `hm_core` module skeleton
- [x] Entities: Client, Site, Snapshot, Finding (+ admin forms, list builders)
- [x] Views: clients list, sites list
- [x] Kernel tests for entity CRUD

## M3 — Agent and collection
- [x] `hm_agent` module: `/hm-agent/status` route, HMAC + timestamp check, collector service
- [x] Two extra DDEV sites as demo clients
- [x] Monitor: queue worker fetching snapshots, uptime ping
- [x] Key module for secrets

## M4 — Analysis
- [x] drupal.org release data client (cached)
- [ ] Analyzer: security / update / unsupported / D12 blocker findings
- [ ] Health score + unit tests
- [ ] Upgrade Status results ingestion

## M5 — Dashboard UI
- [ ] `hm_theme` from starterkit
- [ ] SDC: site card, health badge, findings table
- [ ] HTMX refresh and filters

## M6 — AI and MCP
- [ ] AI module + Anthropic provider; client report, release digest, D12 advice
- [ ] MCP module tools: list_sites, get_site_findings, get_security_updates, generate_site_report
- [ ] Install recipe `recipes/health_monitor` with demo content
- [ ] README demo GIF + MCP demo

## Handoff notes
_Latest session first. Who, date, what's half-done, what's next, gotchas._

- 2026-11-05, Codex — Completed the first M4 task on `feat/m4-release-data`.
  Added an autowired drupal.org release-history client with typed project/release
  value objects and an explicit success/unavailable result, so an outage cannot
  be interpreted as a project with no releases. It validates project machine
  names, accepts at most 5 MB of XML with external networking disabled, normalizes
  project status/supported branches plus release security/core compatibility
  metadata, caches successful lookups for six hours and failures for five minutes,
  and logs only fresh failures. Fixture-based tests cover success, empty projects,
  malformed/mismatched XML, HTTP/network errors, validation, and both cache TTLs.
  A live Token lookup returned 25 releases with 8.x-1.18 first. PHPCS and PHPStan
  pass; the full suite passes with 18 tests and 104 assertions. The four
  deprecations remain upstream Key 1.22 notices. Next: deterministic analyzers for
  security, regular update, unsupported, and Drupal 12 blocker Findings.

- 2026-11-05, Codex — Fast-forwarded and pushed M2, M3, and the Railway cron
  work to `main`. GitHub Actions run 42043747870 passed both quality/tests and
  the production Docker build. Railway's GitHub webhook again did not create a
  deployment automatically after green CI, so both services were explicitly
  redeployed from their configured `main` source. The app pre-deploy installed
  Key and `hm_core`, imported the Client/Site Views, and rebuilt caches; the live
  domain returns Drupal HTTP 200. The updated cron image executed on schedule.
  That run exposed the scaffold `admin@example.com` Update Manager recipient and
  missing sendmail transport, so the placeholder recipient was removed from
  exported config; real email transport/recipients belong with the alerting work.

- 2026-11-05, Codex — Completed the deferred M1b production cron task on
  `feat/m1b-railway-cron`. Added the Railway `cron` service from the same GitHub
  `main` source/image, with `drush cron`, schedule `*/5 * * * *`, no public
  networking, no volume, and DB/Drupal variables expressed as references to the
  existing MySQL/app variables (no secret values were copied). Deployment
  `4d863894-f5e5-43d6-9baa-82a2b5ab9cc6` built successfully; the first scheduled
  execution started at 14:05:48 UTC and exited normally at 14:05:52 UTC. At
  creation time GitHub `main` was still at the M1-era code; the completed M2/M3
  work was subsequently merged and deployed.

- 2026-11-03, Codex — Completed M3 on `feat/m3-agent-endpoint`. Added cron
  scheduling for active Sites, an attributed Queue Worker, signed Guzzle
  collection, payload validation, append-only Snapshot persistence, and the
  `hm_uptime_check` table. The agent request itself is the uptime check, so every
  attempt records HTTP status, latency, success, and an optional error. Added an
  idempotent post-update because this existing install predated `hm_core.install`
  and had no stored schema version. DDEV provisioning now also registers both
  demo clients in the monitor with Key's development-only state provider. Real
  cron runs collected both sites successfully; each now has repeated snapshots
  and HTTP 200 uptime rows. The full suite passes (11 tests, 55 assertions), as
  do PHPStan and PHPCS. Exercising Key's provider plugins reveals four upstream
  deprecations in Key 1.22 (two annotation notices, attribute discovery, and the
  legacy original-property check); custom code is clean. Next: the M1b Railway
  cron service is now actionable, then M4 can start with the drupal.org release
  data client.

- 2026-11-03, Codex — Completed the second M3 task on
  `feat/m3-agent-endpoint`. DDEV now provisions two independent multisite demo
  clients automatically: Acme Production at `client-one.ddev.site` (standard
  profile, 40 enabled modules) and Beacon Staging at `client-two.ddev.site`
  (minimal profile, 15 enabled modules). Each has its own database, files,
  configuration, and randomly generated gitignored HMAC secret. Added idempotent
  `ddev demo-sites` and `ddev demo-site-secret` commands plus setup docs. A clean
  `ddev restart` proves the post-start hook is idempotent; unsigned endpoints
  return 401 and signed endpoints return 200 with zero pending updates. The full
  DDEV suite still passes (9 tests, 36 assertions), along with PHPStan and PHPCS.
  Next: implement the monitor queue worker that fetches snapshots and records
  uptime checks.

- 2026-11-03, Codex — Completed the first M3 task on `feat/m3-agent-endpoint`. Added the
  cross-version, core-only `hm_agent` module with `GET /hm-agent/status`,
  five-minute timestamp/HMAC authentication, deterministic core/PHP/enabled
  module/pending database update collection, no-store responses, setup docs,
  unit coverage for authentication/controller responses, and a kernel service
  wiring test. Runtime verification caught and fixed an invalid namespace-prototype
  service definition. A live request on Drupal 11.4.11/PHP 8.4.27 returned the
  expected payload; unsigned requests returned 401. The complete custom suite
  passes in DDEV (9 tests, 36 assertions), as do PHPStan and PHPCS. The only two
  deprecations are the previously documented Drupal 13 annotation notices from
  contrib Key. Next: create the two extra DDEV demo client sites.

- 2026-10-29, Codex — M2 data model is implemented on `feat/m2-data-model`:
  `hm_core`, Client/Site/Snapshot/Finding content entities, admin forms, tailored
  list builders, permissions, menus, actions, Views data handlers, and kernel CRUD
  coverage. Site stores only an entity reference to a Key record; Key 1.22 is now
  installed ahead of M3. Composer audit found the newly published CVE-2026-45070
  in Symfony Mime 7.4.19, so the lock was updated to patched 7.4.22; audit is clean.
  PHPCS, PHPStan level 6, and PHPUnit pass in DDEV/PHP 8.4 (2 tests, 18 assertions).
  Configured Client and Site Views are exported and available under
  `/admin/health-monitor/reports/*`; their routes execute successfully. Key itself
  emits two Drupal 13 annotation deprecations; custom code is clean. M2 is complete.
  Next: start M3 with the cross-version `hm_agent` endpoint and HMAC validation.

- 2026-10-28, Claude — M1 and M1b both done. First deploy succeeded: predeploy script
  ran `drush site:install --existing-config` against the Railway MySQL service, config
  imported cleanly. `https://health-monitor.tofdev.com` and
  `https://app-production-9474.up.railway.app` both return 200, SSL certificate valid.
  Gotcha that cost real time: after linking the `app` service to the GitHub repo via
  `railway add --repo`, no deploy ever fired on push — `latestDeployment` stayed `null`
  even after CI passed. Root cause: the repo was private and the Railway GitHub App's
  repo access likely hadn't synced for it. Fix was two steps together — made the repo
  public, then ran `railway service source connect --repo ... --branch main --service
  app` again to force Railway to resync the connection. The very next push deployed
  successfully. If this happens again on a private repo, check
  github.com/settings/installations → Railway → Configure → repository access first.
  Gotcha: `DRUPAL_ADMIN_PASS` was generated with `openssl rand -base64 24` and stored
  directly in Railway (not written anywhere else) — read it back from the Railway
  dashboard if it's ever needed. Second gotcha, found right after "done": the site
  returned 200 but served the stock "Welcome to nginx!" page, not Drupal. Both
  Railway domains (generated + custom) had defaulted their **target port to 80**;
  our container actually listens on `$PORT` (8080) per the Dockerfile. Fixed with
  `railway domain update <domain> --port 8080 --service app` on both domains — see
  `docs/RAILWAY_SETUP.md` step 6. Also hardened `docker/entrypoint.sh` to re-remove
  `/etc/nginx/sites-enabled/default` at container start (it was reappearing at build
  time despite the Dockerfile's `rm`, most likely recreated by a dpkg trigger during
  `install-php-extensions`'s own apt install/purge cycle — not fully root-caused, but
  harmless to remove defensively every container start). Confirmed
  `https://health-monitor.tofdev.com` now actually renders Drupal's install/login
  page. Next: start M2 — `hm_core` module skeleton and the Client/Site/Snapshot/Finding
  entities.
