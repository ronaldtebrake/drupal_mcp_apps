# Drupal MCP Apps Media picker

**AI disclosure:** This module, its local patches, and this documentation were developed with assistance from OpenAI Codex. The implementation is a proof of concept backed by automated tests; the proposed contrib APIs have not been merged upstream.

A visual article-editing workflow that demonstrates MCP Apps through **Drupal Tool API**, **MCP Server Tool Bridge**, and **MCP Server**. Ask an MCP Apps host to update an article's hero, browse Drupal Media, preview the proposed crop, and confirm a new draft revision.

The module includes its HTML/JavaScript bundle and sample photos. Installation does not require Node.js, an existing content type, an existing Media type, or this repository author's development site.

## Install on a separate Drupal site

Use Drupal 11, PHP 8.3+, Composer 2, Git, and the PHP extensions required by Drupal, including GD and OpenSSL. Serve Drupal's `web/` directory at an HTTPS URL that your MCP host can reach.

For a new project:

```sh
composer create-project drupal/recommended-project:^11 mcp-apps-demo
cd mcp-apps-demo
```

For an existing Drupal project, start in its Composer root instead. The examples below assume the standard `web/` document root and `web/modules/contrib/` installer path.

### 1. Download the module and its dependencies

The module is currently distributed through its GitHub repository. Alpha stability is needed for the OAuth companion; stable releases remain preferred.

```sh
composer config repositories.mcp_apps vcs https://github.com/ronaldtebrake/drupal_mcp_apps.git
composer config minimum-stability alpha
composer config prefer-stable true
composer config allow-plugins.cweagans/composer-patches true
composer require drupal/mcp_apps:dev-main drush/drush:^13 --with-all-dependencies
```

Composer installs MCP Server, Tool API, the bridge, MCP Server OAuth, Simple OAuth, its OAuth 2.1 extensions, and their dependencies. The `drupal/mcp_server_oauth-mcp_server_oauth` package name in composer.json is Drupal.org's packaged OAuth module, whose Drupal machine name is `mcp_server_oauth`.

If your project prompts about standard Composer plugins such as `symfony/runtime` or `php-http/discovery`, configure them according to your project's Composer policy. Composer Patches must be allowed for the next step.

### 2. Apply the bundled upstream patches

Run these commands before enabling the module:

```sh
php web/modules/contrib/mcp_apps/scripts/configure_composer.php
composer update drupal/mcp_server drupal/tool drupal/mcp_server_tool_bridge cweagans/composer-patches --no-interaction
composer patches-relock
composer patches-repatch
```

The helper merges the three local patches into the **site's root composer.json**, calculates their SHA-256 hashes, and preserves unrelated patches. The three contrib releases are pinned because these patches target specific release archives. `patches-repatch` reinstalls those packages and applies the patches through Composer.

Commit your site's `composer.json`, `composer.lock`, and `patches.lock.json`. Bundled patch files live in this module's `patches/` directory. Local paths must be registered in the consuming project's root; dependency-relative patch paths are not supported by [Composer Patches](https://docs.cweagans.net/composer-patches/usage/defining-patches/).

### 3. Install Drupal and enable the demo

If Drupal is not installed yet, install it using the normal installer and your database settings. For a disposable SQLite demo with PDO SQLite available, one option is:

```sh
vendor/bin/drush site:install standard \
  --db-url=sqlite://localhost/sites/default/files/.ht.sqlite \
  --account-name=admin --site-name="MCP Apps demo" -y
```

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

### 4. Configure OAuth signing keys

Create keys outside the document root and configure Simple OAuth to use them:

```sh
mkdir -m 700 oauth-keys
vendor/bin/drush simple-oauth:generate-keys "$PWD/oauth-keys"
vendor/bin/drush config:set simple_oauth.settings public_key "$PWD/oauth-keys/public.key" -y
vendor/bin/drush config:set simple_oauth.settings private_key "$PWD/oauth-keys/private.key" -y
vendor/bin/drush cr
```

The PHP/web-server user must be able to read the keys. Keep private keys out of version control. Existing sites with working OAuth keys should retain their configuration.

Connect your MCP Apps host to:

```text
https://YOUR-DRUPAL-HOST/mcp
```

Choose OAuth authentication and sign in with the site administrator for the first demonstration. Request the `mcp_apps_demo` scope; the protected-resource metadata advertises it. If your host has an explicit scope field, enter that value. The enabled modules expose OAuth discovery and dynamic client registration; your host can register its OAuth client. Hosts without dynamic registration require a client configured under **Configuration → Web services → Consumers**, with the host's actual redirect URI and Authorization Code grant and `mcp_apps_demo` as its default Authorization Code scope.

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

Sample photos are bundled, labelled as stock images, and attributed in `assets/manifest.json`. They are not photographs of the article's actual event. The UI is a dedicated Olivero-style demo presentation, not an imported Drupal CMS recipe.

## Remove sample content

```sh
vendor/bin/drush php:script web/modules/contrib/mcp_apps/scripts/cleanup_demo.php
```

Cleanup only removes UUID-tracked demo data. Referenced Media, files with usage, and bundles containing other content are retained. Uninstalling the module removes its resource registration and bridge configurations. The demo role/scope are retained by sample-content cleanup and are removed when the module is uninstalled. OAuth keys and site-wide authentication configuration remain yours to manage.
