# Drupal Health Monitor

[![CI](https://github.com/FLoRyN1994/DrupalHealthMonitor/actions/workflows/ci.yml/badge.svg)](https://github.com/FLoRyN1994/DrupalHealthMonitor/actions/workflows/ci.yml)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.txt)

> A fleet dashboard for Drupal maintenance retainers: core/module versions, security
> updates, Drupal 12 readiness and uptime for every client site, with AI-written client
> reports and an MCP server so you can ask Claude about your fleet.

![Drupal Health Monitor dashboard demo](docs/dashboard-demo.gif)

**Live:** [health-monitor.tofdev.com](https://health-monitor.tofdev.com) · The
dashboard itself is authenticated because it contains operational client data.

## Why

Freelancers and agencies running Drupal maintenance retainers juggle updates and security
advisories across many sites by hand. Health Monitor collects a signed snapshot from each
client site, flags security updates, unsupported versions and Drupal 12 blockers with
deterministic PHP, and turns the findings into a plain-English report. See
[`docs/BLUEPRINT.md`](docs/BLUEPRINT.md) for the full scope, architecture diagram and
why this differs from Warden, Status Dashboard and Upgrade Status.

## Architecture

```mermaid
flowchart LR
  CS[Client sites<br/>hm_agent JSON endpoint] -->|signed JSON| Q
  DO[drupal.org<br/>release + security data] --> AN
  subgraph M[Monitor site - Drupal 11]
    Q[Cron + Queue workers] --> AN[Analyzer services<br/>findings, health score, D12]
    AN --> EN[Entities<br/>Site, Snapshot, Finding]
    EN --> DB[Dashboard<br/>SDC + HTMX]
    EN --> AR[AI reports]
    MCP[MCP server<br/>OAuth, read-only tools] --> AR
  end
  AR -->|prompt| AI[Anthropic API / any provider]
  CL[Claude Desktop / Code / Codex] -->|queries| MCP
```

Two codebases live in one repo: the **monitor** (`hm_core`, the dashboard) and a
small, core-only **agent** (`hm_agent`) installed on each client site. Agent requests
use a five-minute HMAC signature window. Detection and effort calculations are
deterministic PHP; AI is used only to turn verified evidence into client-ready prose.

## Setup

Requirements: [Docker](https://docs.docker.com/get-docker/) and
[DDEV](https://ddev.readthedocs.io/en/stable/users/install/ddev-installation/).

From a fresh checkout:

```bash
ddev start
ddev composer install
ddev drush site:install minimal --site-name="Drupal Health Monitor" \
  --account-name=admin --account-pass=admin -y
ddev drush recipe ../recipes/health_monitor -y
ddev demo-sites
```

Open a one-time login directly on the dashboard:

```bash
ddev drush uli /admin/health-monitor/dashboard
```

The recipe can be reapplied safely and is the one-command setup for an installed
Drupal site:

```bash
ddev start && ddev drush recipe ../recipes/health_monitor -y
```

It installs the monitor, theme, AI/MCP/OAuth stack, least-privilege configuration,
and two paused demo sites with representative snapshots and findings. `ddev
demo-sites` provisions two independent local Drupal clients, creates their HMAC
secrets outside Git, and activates them. Set `ANTHROPIC_API_KEY` in the environment
to generate AI reports; the deterministic dashboard and MCP read tools work without
it.

## MCP demo

The MCP endpoint is `https://YOUR-HOST/mcp/post`. Simple OAuth protects it with a
dedicated `MCP Client` role and the `health_monitor:mcp` scope; the broad tools from
the contributed MCP module are disabled.

To connect a client:

1. Create a dedicated Drupal user at `/admin/people/create` and assign only the
   **MCP Client** role.
2. Add a confidential consumer at `/admin/config/services/consumer/add`. Enable the
   `client_credentials` grant, select the `health_monitor:mcp` scope, associate the
   dedicated user, and store the client ID and secret in your client secret store.
3. Exchange those credentials at `/oauth/token`, then send the bearer token with
   JSON-RPC requests to `/mcp/post`.

For example, list the deliberately small tool surface from a shell with `curl` and
`jq`:

```bash
export HM_BASE_URL=https://health-monitor.ddev.site
export HM_MCP_CLIENT_ID='replace-me'
export HM_MCP_CLIENT_SECRET='replace-me'

HM_MCP_TOKEN="$(curl --silent --show-error --fail \
  --user "${HM_MCP_CLIENT_ID}:${HM_MCP_CLIENT_SECRET}" \
  --data 'grant_type=client_credentials' \
  "${HM_BASE_URL}/oauth/token" | jq --raw-output '.access_token')"

curl --silent --show-error --fail \
  --header "Authorization: Bearer ${HM_MCP_TOKEN}" \
  --header 'Content-Type: application/json' \
  --data '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' \
  "${HM_BASE_URL}/mcp/post" | jq
```

The server exposes exactly four application tools:

- `list_sites` — fleet health score and open-finding counts
- `get_site_findings` — evidence for one site
- `get_security_updates` — fleet-wide security work and deterministic effort
- `generate_site_report` — create a versioned AI report for one site

Ask an MCP client: **“Which client sites need security updates this week, and how
many hours in total?”** Against the included demo data it answers that **Acme
Production and Beacon Staging need work: two security updates, estimated at 5.5
hours total**, grounded in `get_security_updates` rather than model guesswork.

## Status

The six-milestone MVP is complete and deployed. It includes custom operational
entities, signed agent collection, queues and uptime history, drupal.org analysis,
Drupal 12 readiness, health scoring, an HTMX/SDC dashboard, versioned AI reports,
OAuth-protected MCP tools, a repeatable recipe, CI, and Railway deployment.

See [`docs/PROGRESS.md`](docs/PROGRESS.md) for the implementation history and
[`docs/DECISIONS.md`](docs/DECISIONS.md) for the architectural record.

## What I'd do next

- Add a client-facing, role-scoped read-only portal.
- Generate and schedule monthly PDF maintenance reports.
- Send a Monday Slack or email fleet digest.
- Collect Lighthouse performance and accessibility scores.
- Ingest Composer audit results for PHP dependency vulnerabilities.
- Add AI Search over historical findings and reports.
- Port to Drupal 12 when it ships and publish the migration notes.

## License

[GPL-2.0-or-later](LICENSE.txt), same as Drupal.
