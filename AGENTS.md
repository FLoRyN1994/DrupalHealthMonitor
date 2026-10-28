# AGENTS.md — Drupal Health Monitor

Shared instructions for every AI coding agent on this repo (Codex, Claude Code, others).
Repo: https://github.com/FLoRyN1994/DrupalHealthMonitor (default branch `main`).
The owner, Florin, switches between agents at any time. Nothing lives in chat history:
**the repo is the only memory.**

## Read first, every session
1. `docs/BLUEPRINT.md` — what we are building (scope, entities, stack, milestones).
2. `docs/PROGRESS.md` — what is done, what is in progress, what is next.
3. `docs/DECISIONS.md` — decisions already made. Do not reopen them without asking.
4. `git log --oneline -15` and `git status` — what the last agent actually did.

## Handoff rules (so agents can switch mid-task)
- Work on one task from `docs/PROGRESS.md` at a time. Mark it `[~]` when you start.
- Commit small and often. Never leave uncommitted work at the end of a session.
- Before you stop, update `docs/PROGRESS.md`:
  - tick finished items `[x]`,
  - under **Handoff notes**, write what is half-done, what to do next, and any gotchas.
- Record any new architectural choice in `docs/DECISIONS.md` (date, decision, why).
- Commit message format: `<area>: <what>` e.g. `hm_core: add Site entity`. Add an
  `Agent: codex` or `Agent: claude` trailer so history shows who did what.
- Only one agent works on a branch at a time. Feature branches: `feat/<milestone>-<short-name>`.

## Stack and commands
- Drupal 11.4, PHP 8.4, MySQL 8.4, DDEV, Drush 13.
- Start: `ddev start`
- Install deps: `ddev composer install`
- Drush: `ddev drush <cmd>`; export config after config changes: `ddev drush cex -y`
- Tests: `ddev exec vendor/bin/phpunit -c web/core web/modules/custom`
- Static analysis: `ddev exec vendor/bin/phpstan analyse`
- Coding standards: `ddev exec vendor/bin/phpcs` (fix: `vendor/bin/phpcbf`)
- All three must pass before a task is marked done.

## Code conventions
- Modern Drupal 11 only: OOP hooks with `#[Hook]` attributes, plugin attributes (not
  annotations), constructor property promotion, autowired services, typed properties,
  `declare(strict_types=1);`.
- No deprecated APIs. Code must be ready for Drupal 12.
- Custom modules: `hm_core` (monitor) and `hm_agent` (client sites, must support
  `^10.3 || ^11`, core-only dependencies).
- Theme: `hm_theme` with Single Directory Components; use HTMX for interactivity,
  avoid custom jQuery.
- Detection logic is deterministic PHP with unit tests. AI is only used to summarize
  and explain findings.
- Descriptive variable names; no one-letter names except loop indexes.
- Secrets never go in config or git: use the Key module and `settings.local.php` / env vars.

## Gotchas
- `ddev composer create-project` only runs in an empty folder. Never rerun it here; use
  `ddev composer require` / `update` instead.
- Start the stack with `ddev start` (Docker Desktop must be running). Don't start
  containers from Docker Desktop directly.

## Don't
- Don't commit `vendor/`, `web/core/`, contrib modules, `settings.local.php` or `.env`.
- Don't add contrib modules not listed in the blueprint without noting it in DECISIONS.md.
- Don't rewrite another agent's finished work just for style.
