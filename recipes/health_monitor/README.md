# Drupal Health Monitor recipe

Apply this recipe to an installed Drupal site from the repository root:

```bash
ddev drush recipe ../recipes/health_monitor
```

It installs the Health Monitor module and theme, AI/MCP/OAuth dependencies,
least-privilege MCP configuration, dashboard blocks and Views, and two paused
demo sites with snapshots and findings. Demo sites are paused so a non-DDEV
installation never calls placeholder endpoints. Run `ddev demo-sites` after
applying the recipe to connect and activate the two local signed agents.

OAuth and Anthropic secrets are intentionally absent. DDEV generates local
OAuth signing keys; production reads them from the Railway variables documented
in `docs/RAILWAY_SETUP.md`. Anthropic reads `ANTHROPIC_API_KEY` through Key.
