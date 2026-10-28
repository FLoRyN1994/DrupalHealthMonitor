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
- [ ] README skeleton

## M1b — Deployment (do right after M1, deploy early and often)
- [x] `Dockerfile` (PHP 8.4 FPM + nginx) + `.dockerignore`
- [x] `settings.php` reads DB, hash salt, trusted hosts from env vars (settings.railway.php)
- [x] Railway project `health-monitor`: `app` service (linked to GitHub repo, `main`),
  `MySQL` service, volume on `app` at `web/sites/default/files`, `app` variables wired
  (`DB_*` as `${{MySQL.*}}` refs, `DRUPAL_HASH_SALT`, `DRUPAL_ADMIN_PASS`,
  `DRUPAL_TRUSTED_HOST`) — all via `railway` CLI
- [ ] Railway dashboard-only steps (CLI has no equivalent): pre-deploy command
  `sh scripts/railway-predeploy.sh` on `app`, Wait for CI toggle on `app`
- [ ] First deploy triggered and verified on the `*.up.railway.app` domain
- [ ] Custom domain health-monitor.tofdev.com (CNAME + TXT), HTTPS verified
- [ ] Cron service (deferred to M3+, once there's a queue worth running)
- [x] CI workflow triggers on `push` to `main` and on pull requests

## M2 — Data model
- [ ] `hm_core` module skeleton
- [ ] Entities: Client, Site, Snapshot, Finding (+ admin forms, list builders)
- [ ] Views: clients list, sites list
- [ ] Kernel tests for entity CRUD

## M3 — Agent and collection
- [ ] `hm_agent` module: `/hm-agent/status` route, HMAC + timestamp check, collector service
- [ ] Two extra DDEV sites as demo clients
- [ ] Monitor: queue worker fetching snapshots, uptime ping
- [ ] Key module for secrets

## M4 — Analysis
- [ ] drupal.org release data client (cached)
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

- 2026-10-28, Claude — M1 done (README skeleton added). M1b: Railway project
  `health-monitor` created via `railway` CLI — `app` service linked to the GitHub repo
  on `main`, `MySQL` service added, volume mounted on `app`, all `app` variables set
  (`DB_*` as `${{MySQL.*}}` refs, generated `DRUPAL_HASH_SALT`/`DRUPAL_ADMIN_PASS`,
  `DRUPAL_TRUSTED_HOST`). No deploy triggered yet. Next: in the Railway dashboard, set
  `app`'s Pre-Deploy Command to `sh scripts/railway-predeploy.sh` and enable Wait for CI
  (Settings → Deploy) — the CLI has no equivalent for either. Then trigger a deploy,
  confirm the site loads on the generated `*.up.railway.app` domain
  (`app-production-9474.up.railway.app`), then add the custom domain
  `health-monitor.tofdev.com` and its CNAME/TXT records at the DNS provider. Gotcha:
  `DRUPAL_ADMIN_PASS` was generated with `openssl rand -base64 24` and stored directly in
  Railway (not written anywhere else) — if it's ever needed, read it back from the
  Railway dashboard, not from here.
