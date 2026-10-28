# Decisions

Append-only log. Format: date — decision — why.

- 2026-10-26 — Plain Drupal 11.4 core (recommended-project), not Drupal CMS — dashboard app, lean base shows architecture.
- 2026-10-26 — Custom content entities, not nodes — operational data, stronger showcase.
- 2026-10-26 — AI only summarizes; detection is deterministic PHP — reliable results, testable.
- 2026-10-28 — Agent auth: shared secret per site, HMAC of timestamp + path, 5-minute window — simple and secure, no OAuth on client sites.
- 2026-10-28 — `hm_agent` supports `^10.3 || ^11`, core-only deps — clients may still be on D10.
- 2026-10-28 — Upgrade Status is a data source, not a competitor — agent forwards its results when installed.
- 2026-10-28 — AGENTS.md is the single instruction file; CLAUDE.md imports it — Codex and Claude share one source of truth.
- 2026-10-28 — Local dev with DDEV (not MAMP) — reproducible stack for reviewers, CI and AI agents.
- 2026-10-28 — Host on Railway at health-monitor.tofdev.com, Dockerfile build, MariaDB service (superseded below), existing Railway account with CI/CD.
- 2026-10-28 — CI/CD: GitHub Actions + Railway autodeploy with Wait for CI; `drush deploy` as pre-deploy command — failed checks never reach production.
- 2026-10-28 — Database: MySQL 8.4 LTS instead of MariaDB, locally (DDEV) and on Railway — owner's usual stack, same as Acquia; 8.0 is past end of life.
