# Support MCP Apps through Drupal-native tool and resource APIs

## Problem

Contrib and custom modules should be able to expose independent MCP Apps through mcp_server, without replacing the server factory or implementing protocol handling.

Two working proof-of-concept apps use the installed mcp/sdk 0.7.1 attribute discovery path: a Drupal Media picker and a Drupal Views chart. Both pass real stdio protocol tests for tools/list metadata, tools/call structuredContent, and resources/read HTML/MIME/CSP metadata. Both tools also execute through the existing chat connection. This demonstrates SDK support, but does not demonstrate equivalent support through Drupal-native plugin definitions.

## Verified current behavior

- The factory does not call Builder::enableExtension(), although the installed SDK supports McpApps. Server initialization currently does not advertise io.modelcontextprotocol/ui.
- Drupal's Tool attribute and ToolDefinition constructors do not expose arbitrary metadata. registerTools() does not pass meta to the SDK Tool definition.
- Concrete resource and resource-template registration accept the supplied URI and MIME type, but do not forward resource-definition metadata to the SDK.
- CacheableResourceContent and ResourceContentCache preserve the content payload, including ui:// URI, text/html;profile=mcp-app, HTML, and _meta.ui.csp. A bootstrapped Drupal audit verified this for two distinct app URIs. No new content DTO is needed for those fields.

## Proposed scope

1. Let mcp_server own one-time registration of the SDK McpApps protocol extension. Individual app modules register tools and resources, not duplicate protocol extensions. Whether this is always enabled or conditional should be decided with maintainers.
2. Add arbitrary metadata to the native Tool attribute and ToolDefinition, preserve it through derivatives, and forward it as meta to the SDK Tool definition.
3. Forward metadata from concrete resource and resource-template definitions to the SDK. Document the content-level metadata already supported by CacheableResourceContent.
4. Provide a minimal example using a tool linked to a ui:// HTML resource and the official JavaScript App SDK. Keep business UI and behavior in consuming modules.
5. Add integration tests with two independently registered apps, including access control and client capability negotiation/fallback behavior.

Reuse the PHP SDK for extension advertisement, schema serialization, discovery, and dispatch. Do not add WebMCP or an agent iframe renderer to this server module.

## Acceptance criteria

- initialize advertises io.modelcontextprotocol/ui with text/html;profile=mcp-app when Apps support is enabled.
- Two separate test modules expose distinct tools and ui:// resources without duplicate extension registration.
- tools/list preserves each tool's _meta.ui.resourceUri and unrelated custom metadata.
- resources/list and resources/templates/list preserve supplied definition metadata where applicable.
- resources/read returns the correct HTML, MIME type, and content metadata for each app.
- Native tools/call preserves structuredContent; normal textual tools continue to work.
- Unauthorized tools/resources remain inaccessible.
- A client without Apps support can still obtain a useful non-UI result; capability-aware behavior is documented and tested separately from server advertisement.

## Local verification

The Media picker now demonstrates an article hero workflow: read-only opening, current/proposed previews, and a separate app-visible write tool that saves a draft revision after review. It uses a dedicated demo bundle; arbitrary content models and moderation workflows are outside this proof of concept. Thumbnail bytes travel in presentation metadata/resource HTML, keeping the demo usable when a host cannot fetch local DDEV image URLs.

Executed successfully on October 5, 2026:

```sh
ddev exec python3 web/modules/custom/mcp_apps/tests/protocol_smoke.py
ddev drush php:script web/modules/custom/mcp_apps/tests/server_support_audit.php
```

The first test exercises two real SDK-discovered apps over MCP. The second tests native authoring surfaces and Drupal resource-content handling. Full native plugin registration tests for two separate modules remain acceptance work for the proposed change; the current tests do not claim that coverage.

The companion Tool API bridge already preserves structuredContent. A related issue should agree where app presentation metadata belongs and preserve it through Tool API definitions, MCP config derivatives, and mcp_server ToolDefinition. The current demo deliberately does not imply that these SDK-discovered tools are Tool API plugins.
