# Activity app browser testing

Optional local development tooling for `mcp_apps_activity_demo`. This module
owns `/admin/content/mcp-apps/activity/app` and its CSRF-protected, allowlisted
`/call` transport. It serves the same HTML bundle used by the MCP App.

Enable with `vendor/bin/drush en mcp_apps_activity_dev -y`. An authenticated
account needs **Use MCP Apps composer**. Use `?view=stories` or
`?view=readership` for focused views.

Do not enable this module on production sites. The MCP App uses its native
resource and Tool API plugins independently of these routes.
