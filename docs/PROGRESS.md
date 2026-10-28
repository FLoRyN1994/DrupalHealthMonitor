# Progress

Legend: `[ ]` todo · `[~]` in progress · `[x]` done
Update this file at the end of every session (see AGENTS.md).

## M1 — Foundation
- [ ] DDEV project (PHP 8.4, MySQL 8.4), Drupal 11.4 via `drupal/recommended-project`
- [ ] Git repo, `.gitignore`, push to GitHub
- [ ] Config sync directory `config/sync`, first export
- [ ] Dev tooling: drush, phpstan + phpstan-drupal, coder (PHPCS), drupal-rector, phpunit
- [ ] `phpstan.neon`, `phpcs.xml`
- [ ] GitHub Actions CI: PHPCS, PHPStan, PHPUnit
- [ ] README skeleton

## M1b — Deployment (do right after M1, deploy early and often)
- [ ] `Dockerfile` (PHP 8.4 FPM + nginx) + `.dockerignore`
- [ ] `settings.php` reads DB, hash salt, trusted hosts from env vars
- [ ] Railway project: `app`, `db` (MySQL 8.4), `cron` services, volumes
- [ ] Railway GitHub autodeploy from `main` with Wait for CI, pre-deploy `drush deploy`
- [ ] Custom domain health-monitor.tofdev.com (CNAME + TXT), HTTPS verified
- [ ] CI workflow triggers on `push` to `main` and on pull requests

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

- (empty — project not started)
