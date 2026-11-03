# Health Monitor Agent

`hm_agent` exposes `GET /hm-agent/status` for the central Health Monitor. It
supports Drupal 10.3 and 11 and has no contributed-module dependencies.

Define a unique secret for the site in `settings.local.php` or from an
environment variable. Never export or commit the secret:

```php
$settings['hm_agent_secret'] = getenv('HM_AGENT_SECRET') ?: '';
```

Each request must contain:

- `X-HM-Timestamp`: current Unix timestamp.
- `X-HM-Signature`: lowercase HMAC-SHA256 of the timestamp, a newline, and
  `/hm-agent/status`, using the site secret.

Requests more than 300 seconds old or in the future are rejected.
