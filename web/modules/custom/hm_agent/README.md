# Health Monitor Agent

## Introduction

`hm_agent` exposes a single read-only, signed status endpoint,
`GET /hm-agent/status`, that a central Drupal Health Monitor instance polls to
assess this site's core version, enabled modules, pending database updates,
and optional Upgrade Status scan results. The module has no contributed
dependencies and supports Drupal 10.3 and 11.

- Project page and issue queue links go here once the project is created on
  drupal.org (see `docs/BLUEPRINT.md` M12).

## Requirements

This module requires no modules outside Drupal core. It optionally reads
saved scan results from the
[Upgrade Status](https://www.drupal.org/project/upgrade_status) module when
that module is installed, but does not require it.

## Installation

Install as you would normally install a contributed Drupal module. See
[Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules)
for further information.

## Configuration

The module has no administration UI. Define a unique, random secret for the
site in `settings.local.php` or an environment variable; never export or
commit the secret value:

```php
$settings['hm_agent_secret'] = getenv('HM_AGENT_SECRET') ?: '';
```

The central Health Monitor signs every request to `/hm-agent/status` with
this shared secret. Each request must contain:

- `X-HM-Timestamp`: the current Unix timestamp.
- `X-HM-Signature`: the lowercase hex HMAC-SHA256 of the timestamp, a
  newline, and the request path (`/hm-agent/status`), keyed with the site
  secret.

Requests more than 300 seconds old, timestamped in the future, signed with
the wrong secret, or missing the secret configuration are rejected with
`401 Unauthorized`. Responses are never cached (`Cache-Control: no-store`).

## Maintainers

Current maintainers:

- Florin Tomozei - [drupal.org username TBD, fill in once the project is
  created]
