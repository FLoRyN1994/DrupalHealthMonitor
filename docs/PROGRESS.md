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
  dashboard if it's ever needed. Next: start M2 — `hm_core` module skeleton and the
  Client/Site/Snapshot/Finding entities.
