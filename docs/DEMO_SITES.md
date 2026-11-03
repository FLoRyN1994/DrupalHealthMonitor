# Local demo client sites

DDEV provisions two independent Drupal client sites after `ddev start`. They
share the repository's Composer codebase but have separate databases, files,
configuration, and generated HMAC secrets.

Run `ddev describe` for the exact URLs when the standard HTTP or HTTPS ports are
already occupied on the host.

| Site | URL | Install profile | Database |
| --- | --- | --- | --- |
| Acme Production | `https://client-one.ddev.site` | Standard | `hm_client_one` |
| Beacon Staging | `https://client-two.ddev.site` | Minimal | `hm_client_two` |

Provision them again at any time with:

```shell
ddev demo-sites
```

The command is idempotent. It creates each database only when needed, generates
gitignored local settings, installs Drupal, enables `hm_agent`, and registers
both sites in the monitor. The monitor stores each generated secret through the
Key module's development-only state provider. Retrieve a secret manually with:

```shell
ddev demo-site-secret client-one
ddev demo-site-secret client-two
```

For an administrator login, use Drush's one-time login command instead of a
stored demo password:

```shell
ddev drush --uri=https://client-one.ddev.site user:login
```
