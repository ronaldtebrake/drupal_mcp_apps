# Drupal MCP Apps Media picker

**AI disclosure:** This module, its local patches, and this documentation were developed with assistance from OpenAI Codex. The implementation is a proof of concept backed by automated tests; the proposed contrib APIs have not been merged upstream.

A visual article-editing workflow built on top of the [Agent Access recipe](https://www.drupal.org/project/agent_access), extending its Drupal Tool API, MCP Server Tool Bridge, and MCP Server foundation with MCP Apps. Ask an MCP Apps host to update an article's hero, browse Drupal Media, preview the proposed crop, and confirm a new draft revision.

The module includes its HTML/JavaScript bundle and sample photos. Installation does not require Node.js, an existing content type, an existing Media type, or this repository author's development site.

## Install on top of Agent Access

This demo's installation starts with **Agent Access** on an installed Drupal **11.4+** site. Follow the [Agent Access installation and connection documentation](https://www.drupal.org/project/agent_access) to apply the recipe, configure OAuth signing keys, and verify a connection to the site's HTTPS MCP endpoint. Its documentation owns the shared MCP/OAuth setup; the steps below add the interactive Media picker.

The tested recipe release is `drupal/agent_access:1.0.0-alpha2`. It supplies the starter `tool_api__entity_list` and `tool_api__entity_metadata` tools and the `drupal:mcp:connect` / `drupal:content:read` scopes. This module adds its own tools, UI resource, demo content, and editing scope. No changes to the Agent Access recipe are required.

Use PHP 8.3+, Composer 2, Git, GD, and OpenSSL. Run the commands below from the site's Composer root. Examples assume the standard `web/` document root, `web/modules/contrib/` module path, and project-local Drush 13.

### 1. Install the MCP Apps extension

The demo is distributed through GitHub. Enable alpha stability for the existing experimental dependency stack while preferring stable packages:

```sh
composer config repositories.mcp_apps vcs https://github.com/ronaldtebrake/drupal_mcp_apps.git
composer config minimum-stability alpha
composer config prefer-stable true
composer config allow-plugins.cweagans/composer-patches true
composer require drupal/mcp_apps:dev-main drush/drush:^13 --with-all-dependencies
```

The module declares its own dependencies for Composer and Drupal to validate. Agent Access remains the installation foundation. The demo pins MCP Server, Tool API, and the bridge to the releases listed below because its proposed patches target those archives. A site using newer releases must resolve those constraints before proceeding; do not apply these patches to different versions.

### 2. Apply the proposed contrib patches

The module declares patches under `extra.patches` in its `composer.json`. Composer Patches discovers them from the installed dependency. URLs point to an immutable Git commit and include SHA-256 checksums; no helper script or root-project patch declarations are needed.

Refresh the site's patch lock and reapply before enabling the module:

```sh
composer patches-relock
composer patches-repatch
```

Commit the site's `composer.json`, `composer.lock`, and `patches.lock.json`. Patch source files remain in this module's `patches/` directory for maintainer review. Dependency patch discovery must be enabled (the Composer Patches default); see [defining patches in dependencies](https://docs.cweagans.net/composer-patches/usage/defining-patches/#dependencies).

If upgrading from the earlier helper-based setup, remove its three MCP Apps entries from the site's root `extra.patches` before relocking. Preserve unrelated patches.

### 3. Enable and seed the demo

Then enable the module and create the samples:

```sh
vendor/bin/drush en mcp_apps -y
vendor/bin/drush php:script web/modules/contrib/mcp_apps/scripts/seed_demo.php
vendor/bin/drush cr
```

Enabling `mcp_apps` also enables its MCP, Tool API, and OAuth dependencies, including authorization-server metadata, dynamic client registration, PKCE, and native-app support. It registers the HTML resource provider and installs the two bridge tool configurations.

The seed creates six attributed image Media entities and two unpublished articles. If no Image media type exists, it creates a dedicated demo Image type. It records IDs and UUIDs, leaves existing content types alone, and can be rerun without duplicating samples or resetting a selected hero. It also creates the **MCP Apps demo editor** role and **mcp_apps_demo** OAuth scope, and assigns that role to uid 1 for the first demonstration. The role includes Drupal content-access bypass for the demo drafts; only run the seed on a site intended for this demonstration. Anonymous and authenticated role permissions are unchanged.

Use Olivero on a disposable demo site to match the article preview:

```sh
vendor/bin/drush theme:enable olivero
vendor/bin/drush config:set system.theme default olivero -y
```

The app and full demo article pages share the article CSS and Drupal's `mcp_apps_hero` image style: a centred 1100 × 500 crop. Other content types retain their normal presentation.

### 4. Connect with the demo's editing scope

Keep the working signing keys and OAuth configuration established through Agent Access. For shared connection setup and troubleshooting, use the [Agent Access documentation](https://www.drupal.org/project/agent_access).

Connect your MCP Apps host to:

```text
https://YOUR-DRUPAL-HOST/mcp
```

Choose OAuth authentication and sign in with the site administrator for the first demonstration. The Agent Access read scopes do not grant hero-editing access. Request the additional `mcp_apps_demo` scope; the protected-resource metadata advertises it. If your host has an explicit scope field, enter that value. The enabled modules expose OAuth discovery and dynamic client registration; your host can register its OAuth client. Hosts without dynamic registration require a client configured under **Configuration → Web services → Consumers**, with the host's actual redirect URI and Authorization Code grant and `mcp_apps_demo` as its default Authorization Code scope.

For a non-administrator demo account, assign **MCP Apps demo editor** under **People**. For a more limited role, grant the permissions below and configure an OAuth scope carrying that role under `/admin/config/people/simple_oauth/oauth2_scope/dynamic`. Authorization Code login also requires **Grant OAuth2 codes**. Module permissions and Drupal content access still apply independently of OAuth. This demonstration verifies OAuth authentication and Drupal permissions; it does not claim per-tool OAuth scope enforcement by the companion alpha release.

## Demonstrate the workflow

Ask your MCP Apps host:

> Update the hero for ‘A weekend of discovery in Rotterdam’

Or call the tool explicitly:

```json
{"name":"tool_api__media_picker_open","arguments":{"article_title":"A weekend of discovery in Rotterdam"}}
```

Choose an image, edit article-specific alt text, compare current and proposed heroes, then choose **Review hero change** and **Save hero to article**. Saving uses the app-visible `tool_api__media_picker_save_hero` tool. It creates an unpublished revision and preserves the title, body, and shared Media alt text. Open the article's URL to compare the actual Drupal page with the app preview; article IDs vary between sites.

The HTML resource is `ui://drupal/media-picker`, served as `text/html;profile=mcp-app`. A host must support MCP Apps to render it. Ordinary MCP clients receive useful text and structured results. The optional Drupal page `/admin/content/mcp-apps/media-picker` is a read-only browser preview; it cannot replace MCP host initialization or save from the app.

## Permissions and access

Assign permissions under **People → Permissions**:

| Permission | Purpose |
| --- | --- |
| Access MCP server | Reach the MCP endpoint |
| Use the MCP Media picker | Open/search the tool, read its HTML resource, and use the browser preview |
| Update article heroes through MCP | Save a hero; also requires the picker permission |
| View media / View published content | See accessible Media and content |

Drupal node view/update, field view/edit, and file access checks remain in force. The sample drafts belong to the administrator: another account needs access to those unpublished drafts and permission to edit them. On an isolated demonstration site, a dedicated tester role can use Drupal's **Bypass content access control** permission; on an existing site, use your site's appropriate draft-access policy instead.

No permissions are automatically granted to anonymous or authenticated roles. A picker-only user cannot save. Denied calls return no article/image data and make no content changes. The implementation rejects published or moderated articles, stale revision tokens, inaccessible Media, and private files.

## What the three patches demonstrate

```text
Tool API plugin + definition/result metadata
    → MCP ToolConfig derivative + bridge
    → MCP Server factory + native ResourceProvider
    → official PHP MCP SDK + MCP Apps host
```

| Contrib project | Patched release | Proposed work |
| --- | --- | --- |
| `mcp_server` | `2.0.0-beta5` | Enable the SDK McpApps extension once; preserve Tool/derivative and resource/template definition metadata; retain resource content `_meta` using SDK content objects |
| `tool` | `1.0.0-beta8` | Generic definition and execution-result metadata, retained by formatted results without becoming tool outputs |
| `mcp_server_tool_bridge` | `1.0.0-beta3` | Carry definition metadata into MCP derivatives and result metadata into CallToolResult |

The bridge patch also corrects two existing test fixtures that reference a removed Tool API output-definition class. The patches include regressions for metadata inheritance, result formatting, and separate resource descriptor/content metadata.

The demo uses native Drupal plugins and bridge configuration entities. It does not replace the server factory, register SDK-discovered tools, implement JSON-RPC, use WebMCP, or supply an agent iframe renderer. Large image previews travel in presentation metadata and resource HTML, outside model-visible structured content. The committed app bundle uses the official JavaScript MCP Apps SDK.

The generic metadata contract and unconditional extension advertisement are proposals for maintainer discussion. A full conformance suite for multiple independent app modules and capability-dependent registration remains outside this single-app demonstration.

## Tests and development

The Drupal tests cover module permissions, HTTP 403/200 responses, node/field/Media restrictions, denied writes leaving content unchanged, safe revisions, and actual tool/resource dispatch through the native factory and bridge. Unit regressions exercise the proposed metadata contracts.

With Drupal core-dev and the normal Drupal PHPUnit database/base-URL configuration installed:

```sh
vendor/bin/phpunit web/modules/contrib/mcp_apps/tests/src
vendor/bin/phpunit web/modules/contrib/mcp_server
vendor/bin/phpunit web/modules/contrib/tool/tests/src/Unit
vendor/bin/phpunit web/modules/contrib/mcp_server_tool_bridge
```

To verify the real MCP transport and the actual UI bundle, run from the site root:

```sh
DRUPAL_BASE_URL=https://YOUR-DRUPAL-HOST python3 web/modules/contrib/mcp_apps/tests/protocol_smoke.py
cd web/modules/contrib/mcp_apps
npm ci
node tests/ui-smoke.mjs
```

The protocol check authenticates as the local `admin` account through Drush and creates a temporary UI fixture. `DRUPAL_USER` selects another local account. This tests protocol dispatch separately from HTTP OAuth login. The UI test uses the real bundle and SDK with a simulated host.

After changing UI source, rebuild and commit the bundle:

```sh
npm run build
```

## Demo images and copyright

The six bundled photographs are third-party images from Unsplash. **The photographs are licensed under the [Unsplash License](https://unsplash.com/license), separately from the module's GPL-2.0-or-later code license.** Copyright remains with the respective photographers; the photographs were not generated by AI.

Photo credits: **Arnout van Nieuwkoop, Alexander Psiuk, micheile henderson, Roel Oosterwijk, Ufoma Ojo, and Mitchell Leach**, via Unsplash. See [image licensing and individual source links](assets/LICENSE.md) for the credit for each file. The same credits are stored in [assets/manifest.json](assets/manifest.json).

These are labelled stock images illustrating the demo, not photographs of the articles' actual events. Preserve the credits when redistributing the demo. The UI is a dedicated Olivero-style demo presentation, not an imported Drupal CMS recipe.

## Remove sample content

```sh
vendor/bin/drush php:script web/modules/contrib/mcp_apps/scripts/cleanup_demo.php
```

Cleanup only removes UUID-tracked demo data. Referenced Media, files with usage, and bundles containing other content are retained. Uninstalling the module removes its resource registration and bridge configurations. The demo role/scope are retained by sample-content cleanup and are removed when the module is uninstalled. OAuth keys and site-wide authentication configuration remain yours to manage.
