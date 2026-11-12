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
- [x] Analyzer: security / update / unsupported / D12 blocker findings
- [x] Health score + unit tests
- [x] Upgrade Status results ingestion

## M5 — Dashboard UI
- [x] `hm_theme` from starterkit
- [x] SDC: site card, health badge, findings table
- [x] HTMX refresh and filters

## M6 — AI and MCP
- [x] AI module + Anthropic provider; client report, release digest, D12 advice
- [x] MCP module tools: list_sites, get_site_findings, get_security_updates, generate_site_report
- [x] Install recipe `recipes/health_monitor` with demo content
- [x] README demo GIF + MCP demo

## Handoff notes
_Latest session first. Who, date, what's half-done, what's next, gotchas._

- 2026-11-12, Codex — The MCP compatibility hotfix deployed successfully and
  eliminated the scheduled cron service exception. Its first run showed the
  remaining private-file warning still occurs despite correct `0700
  www-data:www-data` ownership in the app container. The follow-up on
  `fix/private-htaccess` creates Drupal core's canonical deny-all `.htaccess`
  before either runtime bootstraps. The generated file matches core's rules and
  mode `0444`; the full Composer, PHPCS, PHPStan, and PHPUnit gate passes. Next:
  pass CI, deploy both services, and confirm the scheduled cron log contains
  neither error.

- 2026-11-12, Codex — The first post-MVP Railway cron run exposed an upstream
  MCP 1.2.3 defect: its removed SSE message-storage service is still requested
  by `mcp_cron()`. Added a conditional compatibility service that is a no-op for
  the stateless HTTP transport and yields automatically if upstream restores
  the service. Also made the container's private file root writable only by
  Drupal so core can create its protective `.htaccess`. A real verbose local
  cron run now completes without the service exception. Composer
  validation/audit, PHPCS (94 files), PHPStan (76 files), and PHPUnit (51
  tests, 288 assertions) pass; seven upstream AI/JSON-RPC deprecations remain.
  Next: merge the hotfix, redeploy both services, and verify a clean scheduled
  production run.

- 2026-11-12, Codex — Completed the final M6 task and the full MVP on
  `feat/m6-readme-demo`. The README now presents the deployed product, an
  animated GIF captured from the authenticated production dashboard, accurate
  fresh-install and repeatable-recipe steps, the architecture and security
  boundaries, a complete OAuth client-credentials MCP walkthrough, the four
  least-privilege tools, a grounded demo answer, and the post-MVP roadmap. A
  clean-clone DDEV start now defers demo provisioning until Composer is present.
  The recipe reapplies successfully, both signed local agents provision, and a
  real temporary OAuth client returned exactly the four documented MCP tools;
  its credentials were removed afterward. Composer validation/audit, PHPCS (92
  files), PHPStan (74 files), and PHPUnit (51 tests, 287 assertions) pass. The
  seven deprecations remain upstream in AI/JSON-RPC. All blueprint milestones
  are complete; next work is optional stretch goals.

- 2026-11-12, Codex — Completed the M6 install recipe on
  `feat/m6-install-recipe`. `recipes/health_monitor` installs the dashboard
  theme, monitor, AI, MCP, and OAuth stack; configures the environment-backed
  Anthropic key, least-privilege MCP surface, OAuth scope, theme blocks, and
  administrative Views; and imports two clients, two paused sites, two
  snapshots, and five representative findings. Paused defaults prevent cron
  from calling placeholder endpoints outside DDEV; `ddev demo-sites` attaches
  local secrets and activates them. The recipe also fixes the missing main-page
  content block. It applies successfully twice on the live DDEV site without
  duplicate content, its authenticated dashboard renders both sites/findings,
  and a fresh-kernel test passes with 24 assertions. Composer validation/audit,
  PHPCS, and PHPStan pass; the full suite passes with 51 tests and 287
  assertions. The seven remaining deprecations are upstream in AI/JSON-RPC.
  Next: finish the README, dashboard demo GIF, and documented MCP client/demo,
  the final blueprint task.

- 2026-11-12, Codex — Completed M6's MCP task on `feat/m6-mcp-tools`.
  Installed stable MCP 1.2.3 and security-fixed Simple OAuth 6.1.4, exposed
  exactly `list_sites`, `get_site_findings`, `get_security_updates`, and
  `generate_site_report`, and disabled the broad contributed tool groups.
  Read operations return deterministic health, findings, and effort data;
  report generation reuses the versioned AI-report service. Access requires
  the dedicated `mcp_client` role and exported `health_monitor:mcp` OAuth
  permission scope. Local and production signing keys are generated/injected
  outside Git. A real client-credentials token successfully listed the exact
  four tools; an unauthenticated request returned 401. Composer validation and
  audit, PHPCS, PHPStan, and all 50 tests/263 assertions pass. Seven
  deprecations are upstream in AI and JSON-RPC. Next: build the install recipe
  with demo content; then finish the README, MCP client setup/demo, and GIF.

- 2026-11-11, Codex — Production incident while deploying the AI-report merge:
  `railway up` preserved local macOS checkout modes (Drupal files 0600 and
  directories 0700), producing HTTP 403 from PHP-FPM. More importantly, the
  pre-deploy script treated a failed Drupal bootstrap as an empty database and
  ran `site:install --existing-config`, which dropped and recreated the Railway
  schema. Railway PITR is disabled for this stock `mysql:8` service and there
  are no on-demand backups, so the prior operational rows are not recoverable.
  The install did restore all exported configuration and there was no client
  report data yet. The deployment guard now queries `information_schema` and
  refuses `site:install` whenever any table exists, and the Docker build
  normalizes web-root permissions. A local production-image build verifies
  directories 0755, files 0644, and valid shell syntax. This fix must be merged
  and deployed before feature work resumes; production is temporarily serving
  403 until that corrected image replaces deployment
  `2ca58555-c9d1-446b-8dc3-c190260eb288`.

- 2026-11-11, Codex — Completed M6's AI-report task on
  `feat/m6-ai-reports`. Added Drupal AI plus the Anthropic provider, with the
  API credential represented only by an environment-backed Key configuration
  (`ANTHROPIC_API_KEY`) and `claude-sonnet-5` selected through exported config.
  A permission-protected Site action builds deterministic context from open
  Findings, calculates auditable effort hours in PHP, sends only JSON evidence
  to a prompt that treats all values as untrusted data, validates the model's
  three-field JSON response, and saves every result as a new `hm_ai_report`
  version. Reports expose client summary, release digest, Drupal 12 advice,
  effort, generation time, and model. Provider failures are logged and shown
  safely without persisting partial reports. The form renders locally with
  HTTP 200, post-update storage installation succeeds, Composer audit reports
  no advisories, PHPCS and PHPStan pass, and the full suite passes with 49 tests
  and 246 assertions. Six deprecations are upstream in Drupal AI 1.5.2. The
  Railway app currently has no `ANTHROPIC_API_KEY`, so report generation will
  remain safely unavailable in production until that secret is supplied. Next:
  implement the four MCP tools against the same typed entity/report services.

- 2026-11-11, Codex — Completed HTMX refresh/filtering and therefore M5 on
  `feat/m5-dashboard-components`. The dashboard now filters by environment,
  monitoring status, and health band through ordinary GET requests with a
  no-JavaScript submit/reset path. Drupal core's HTMX API progressively
  enhances changes and submits, requests the minimal main-content renderer,
  selects and replaces only `#hm-dashboard-results`, updates browser history,
  cancels superseded requests, loads core's asset integration, and exposes a
  status indicator. Filter query args are allowlisted and included in render
  cache contexts; Findings are scoped to the selected Sites. Full and HTMX
  requests return HTTP 200 locally, and the controller kernel test covers
  combined filters, component props, and generated HTMX attributes. PHPCS and
  PHPStan pass; the full suite passes with 44 tests and 217 assertions. The
  four deprecations remain upstream Key 1.22 notices. M5 is complete; next:
  M6's AI module/provider integration and deterministic report context.

- 2026-11-11, Codex — Completed the M5 Single Directory Components task on
  `feat/m5-dashboard-components`. Added stable, schema-validated site-card,
  health-badge, and findings-table components with responsive styling and
  accessible score/severity output. A new permission-protected dashboard at
  `/admin/health-monitor/dashboard` renders live Site/Finding entity data,
  carries entity/list cacheability, and is linked from the admin content menu.
  Live local rendering returns HTTP 200 for both demo sites; the component
  kernel test covers nested rendering, a critical finding row, and all three
  CSS libraries. PHPCS and PHPStan pass; the full suite passes with 43 tests
  and 204 assertions. The four deprecations remain upstream Key 1.22 notices.
  The browser screenshot host was unavailable, so verification used rendered
  HTML plus DOM assertions. Next: add HTMX refresh and filters around the
  dashboard's stable component structure.

- 2026-11-11, Codex — Completed the M5 starterkit task on
  `feat/m5-dashboard-ui`. Generated `hm_theme` from Drupal 11.4.11's
  Starterkit, retained its attributed OOP preprocess hook with strict typing,
  enabled it as the default theme, and exported matching block placements for
  branding, messages, page title, local actions/tasks, and sidebar menus. A
  live anonymous request returns HTTP 200 and loads the new theme's assets.
  PHPCS and PHPStan pass; the full suite passes with 42 tests and 195
  assertions. The four deprecations remain upstream Key 1.22 notices. Local
  PHPUnit needs `SIMPLETEST_DB=mysql://db:db@db/db` supplied explicitly for
  kernel tests. Next: build the site card, health badge, and findings table as
  Single Directory Components.

- 2026-11-05, Codex — Completed Upgrade Status ingestion and therefore M4 on
  `feat/m4-release-data`. The core-only agent optionally reads Upgrade Status's
  saved key/value results and emits a versioned, maximum-250-project summary
  with explicit not-installed, never-scanned, available, stale, and failed
  states. Results older than 30 days are stale; malformed results and scan
  transport failures are not treated as clean scans. The monitor validates the
  bounded payload and only reconciles fresh scans targeting Drupal 12. Current
  scan errors create a high-severity D12 blocker; a fresh clean scan resolves
  only that project's scan-backed blocker. Missing, stale, failed, or D11-target
  evidence preserves prior findings. Finding now records `release_data` versus
  `upgrade_status` evidence so the two lifecycles cannot clear each other; an
  open release-data blocker suppresses a duplicate scan blocker. Added a
  post-update storage migration, including a repair update discovered by an
  end-to-end cron run, and verified both demo agents return HTTP 200 afterward.
  PHPCS and PHPStan pass; the full suite passes with 42 tests and 195 assertions.
  The four deprecations remain upstream Key 1.22 notices. M4 is complete; next
  blueprint task is M5's `hm_theme` starterkit. Local Beacon Staging currently
  scores 92 because the 12-check window correctly retains two HTTP 500 checks
  produced while testing its stale multisite container; successful checks will
  age them out.

- 2026-11-05, Codex — Completed the M4 health score on
  `feat/m4-release-data`. A pure calculator starts at 100 and applies capped,
  multiplicative penalties for open security, unsupported, D12 blocker, and
  regular update findings, plus a proportional uptime penalty capped at 30
  points across the latest 12 checks. Missing uptime history is neutral and the
  lower boundary is zero. A persistence service reads only open Findings and
  the bounded uptime window, then avoids saving Site when the score is
  unchanged. Collection recalculates after every recorded result, including
  connection failures; on successful collection it analyzes the new Snapshot
  first, so the score includes current findings. Unit tests cover every weight,
  multiplicity caps, mixed penalties, downtime, no history, and boundaries; a
  kernel test covers resolved-finding exclusion and the 12-check window. Next:
  Upgrade Status results ingestion, the last M4 task. PHPCS and PHPStan pass;
  the full suite passes with 36 tests and 155 assertions. The four deprecations
  remain upstream Key 1.22 notices.

- 2026-11-05, Codex — Completed the M4 deterministic analyzer on
  `feat/m4-release-data`. Agent module entries now identify core, contributed,
  and custom origin and map contributed submodules to their drupal.org project.
  Every persisted Snapshot triggers analysis of core and identifiable contrib
  projects. Stable published releases are evaluated for security updates,
  regular same-major updates, unsupported project/branch/security coverage,
  and Drupal 12 compatibility. Findings are upserted by Site + project + type;
  disappeared findings and removed projects are resolved only with complete
  successful evidence, while release-feed failures preserve existing alerts.
  Missing compatibility metadata remains unknown instead of becoming a false
  D12 blocker. Unit and kernel coverage exercises all finding types, legacy
  versions, prerelease exclusion, core releases, and reconciliation lifecycle.
  Live checks verified agent provenance and produced no false finding for the
  installed Drupal core. PHPCS and PHPStan pass; the full PHPUnit suite passes
  with 25 tests and 141 assertions. The four deprecations remain upstream Key
  1.22 notices. Next: health score calculation and tests.

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
