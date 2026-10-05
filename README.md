# Drupal MCP Apps demos

Two independent interactive apps using Drupal data and the official MCP Apps SDKs. The module is a proof of concept for upstream MCP Server / Tool API bridge issues, not a new app framework.

## Test in an MCP Apps host

Call either tool through the existing Drupal MCP connection:

```text
media_picker_open {}
views_chart_open {}
```

After enabling or changing discovery, rebuild Drupal caches and refresh the host connection's tool catalogue.

### Media Picker — article hero workflow

- Real, access-checked Drupal image Media, with a searchable thumbnail grid.
- Open an article by `node_id` or `article_title`. Two dedicated demo article drafts are seeded.
- Compare current and proposed heroes using the same article card rendered on the demo node page. The app bundle and Drupal library share `ui/article.css`, and both use Drupal's `mcp_apps_hero` image style (1100 × 500, centred crop). Text wraps responsively to the available width. This is a dedicated demo presentation, not an imported Drupal CMS recipe.
- The website displays article-specific alt text and tracks the referenced Media and image style in its render cache. Only full views of the demo article bundle use this presentation.
- Select an image, edit article-specific alt text, then **Review hero change** and **Save hero to article**. The app-only `media_picker_save_hero` tool creates a new revision, preserving article title/body and draft status. Shared Media alt text is unchanged.
- The first implementation supports the dedicated, unmoderated demo article bundle only. Published content and stale revisions are rejected. Arbitrary site bundles and moderation transitions need a later integration step.
- Choose no article to use the original **Use this media** chat handoff without saving.
- Local search responds immediately; **Search Drupal** calls the server again.
- Sample photos are labelled and attributed. They are stock imagery, not actual DrupalCon coverage. Access-checked Drupal image derivatives travel as data URLs in tool-result `_meta`, so the app does not need network access to a local DDEV site. Private files are omitted; image bytes stay outside model-visible structured data. Thumbnail payloads are bounded; external Drupal URLs remain a fallback for omitted thumbnails.

Example: `media_picker_open {"article_title":"weekend of discovery"}` or `media_picker_open {"node_id":186}` in this sandbox.

Resource: `ui://drupal/media-picker`.

### Views Chart — visual exploration

- Executes the real `mcp_apps_content_activity` Drupal View.
- Groups accessible node rows by month and content type in UTC.
- Switch line / bar charts, toggle types and focus individual months.
- Exact values are available in an accessible table.
- Period, author and data-source refreshes call the server; author input maps to the View's native exposed entity-autocomplete filter.
- **Discuss this view** sends the visible series and current filters to the conversation.
- Defaults to clearly labelled sample draft content. `scope: "all"` uses accessible site content. Results are bounded to the first 2,000 View rows; truncation is indicated.

Example: `views_chart_open {"months":6,"scope":"demo","author":1}`.

Resource: `ui://drupal/views-chart`.

## Implementation and upstream scope

```text
src/Mcp/MediaPicker.php       Tool + HTML resource, real Media search
src/Mcp/ViewsChart.php        Tool + HTML resource, real View execution
src/HeroArticle.php          Shared article text and website rendering
templates/                   Demo article template
src/Mcp/DemoSupport.php       SDK result/resource helpers and Drupal Fiber handling
src/McpAppsServiceProvider.php  Existing mcp_server SDK discovery extension point
ui/                          Frontends using @modelcontextprotocol/ext-apps
dist/                        Self-contained, offline JavaScript app bundles
assets/                      Attributed sample photos and source manifest
```

Tools advertise `_meta.ui.resourceUri`; resources return `text/html;profile=mcp-app` and content CSP metadata. Both use structured results plus useful text/JSON fallback. No WebMCP dependency, manual MCP protocol implementation, or host iframe renderer is included.

The current demo uses **SDK attribute discovery**, because the native Drupal Tool attribute and ToolDefinition cannot yet carry arbitrary metadata. The factory also drops resource-definition metadata and does not advertise the Apps protocol extension. These upstream gaps are documented in [MCP_SERVER_ISSUE.md](MCP_SERVER_ISSUE.md); this module does not patch contrib code or claim those gaps are resolved.

Tool API's bridge already returns structuredContent. A related issue should establish a metadata contract and preserve app resource links when Tool API definitions become MCP derivatives. These demo tools currently use SDK attributes directly, not Tool API plugins. Keeping that distinction explicit makes the issue evidence reproducible.

## Setup

From the DDEV site root:

```sh
ddev exec 'cd web/modules/custom/mcp_apps && npm ci && npm run build'
ddev drush en mcp_apps -y
ddev drush php:script web/modules/custom/mcp_apps/tests/seed_demos.php
ddev drush cr
```

Requires an existing image Media type. Bundled assets are sufficient; `tests/fetch_demo_images.py` is an optional developer download helper. Sources and credits are stored in `assets/manifest.json`; the source pages identify the Unsplash license: https://unsplash.com/license.

The seed script creates six Media entities and public files, two article drafts with hero fields, 102 unpublished chart sample nodes across Articles / Events / Pages over 12 months, one blocked demo author account, and one View. It tracks IDs and UUIDs in state and refuses to populate untracked colliding bundles or overwrite an untracked View. It does not modify existing content types or publish sample nodes. Rerunning in the same month does not duplicate the samples.

Run `tests/cleanup_demos.php` through `ddev drush php:script` to remove tracked sample data. Node-referenced media, files with usage, and bundles with additional content are retained.

## Verification

```sh
ddev drush php:script web/modules/custom/mcp_apps/tests/backend_smoke.php
ddev drush php:script web/modules/custom/mcp_apps/tests/hero_smoke.php
ddev drush php:script web/modules/custom/mcp_apps/tests/article_presentation_smoke.php
ddev exec python3 web/modules/custom/mcp_apps/tests/protocol_smoke.py
ddev exec node web/modules/custom/mcp_apps/tests/ui-smoke.mjs
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/mcp_apps/src web/modules/custom/mcp_apps/tests/seed_demos.php web/modules/custom/mcp_apps/tests/cleanup_demos.php web/modules/custom/mcp_apps/tests/backend_smoke.php
```

The protocol test calls the real MCP transport and writes fixtures under `/tmp` inside DDEV. UI tests use those fixtures with a simulated host and the actual bundled SDK. Backend tests verify author partitions and anonymous access restrictions; a sample Media status change is restored in a finally block.

Authenticated browser previews use the same UI and current-account data:

- https://webmcp-integration.ddev.site/admin/content/mcp-apps/media-picker
- https://webmcp-integration.ddev.site/admin/content/mcp-apps/views-chart

Previews allow local exploration only; chat handoff and server refresh controls require an MCP host. Preview responses are private/no-store with nonce CSP. Browser preview success is separate from actual MCP host initialization.
