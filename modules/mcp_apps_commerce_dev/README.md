# Optional Commerce app browser development

Enable this module only for local browser QA. It exposes `/shop/app` and the CSRF-protected, allowlisted `/shop/app/call` transport. Both require an authenticated shopper with the Commerce demo and composer permissions.

The bundle is identical to the MCP resource, but uses an HTTP development transport. These routes are unnecessary for an MCP Apps host. Disable this module to remove both routes.
