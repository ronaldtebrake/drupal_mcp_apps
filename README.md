# OpenUI component previews for Drupal MCP Apps

**AI disclosure:** OpenAI Codex assisted with this module, its tests, documentation, and contrib patches. This is a proof of concept; the proposed upstream APIs have not all been merged.

Build an interactive MCP App from components that already exist in Drupal. The optional OpenUI integration uses the official OpenUI parser and React renderer for composition and editing controls. Drupal validates the component tree and renders its original SDC Twig templates and attached assets. There are no React copies of those templates.

The Rotterdam showcase is **preview only**: change text, select accessible Drupal Media, inspect OpenUI source, and compare desktop/mobile widths. It creates no pages and saves no content.

Tools and components remain reusable: Tool API supplies data and operations, while SDCs supply their schemas, Twig templates, and assets. The optional **Editorial pulse** dashboard demonstrates both: the same data tool and chart/activity components power a Drupal page and an MCP App. Neither showcase implements a Canvas authoring workflow.

## Modules

| Module | Responsibility |
| --- | --- |
| `mcp_apps` | Module-owned HTML resources, SDK MIME metadata, Drupal cacheability, and a reusable JavaScript host helper |
| `mcp_apps_openui` | Official OpenUI integration, SDC discovery/validation, native rendering, and account-bound preview sessions |
| `mcp_apps_demo` | Optional Rotterdam composition, credited sample images, and Media selection |
| `mcp_apps_activity_demo` | Optional editorial dashboard, fictional story/activity data, and reusable chart SDCs |
| `mcp_apps_activity_dev` | Optional local browser test routes; not required by the MCP App |

The foundation has no rendering dependency on OpenUI or Canvas. The OpenUI integration works with ordinary core SDCs without Canvas. The showcase uses a Mercury-generated theme: Mercury's existing image template and schema require Canvas and CVA. Canvas authoring, Canvas Tools, and publishing are outside this demo.

**The OpenUI integration is the intended community contribution.** The base module is a small packaging dependency for its resource builder and host helper; it does not implement the MCP Apps protocol. The demo is optional and is not needed to render your own SDCs. See [the OpenUI developer guide](modules/mcp_apps_openui/README.md) for the rendering flow and extension points.

## Install on top of Agent Access

Start with an installed Drupal **11.4+** site and PHP **8.3+**. Apply and configure the [Agent Access recipe](https://www.drupal.org/project/agent_access), following its documentation for OAuth signing keys, clients, scopes, and an HTTPS MCP connection. Agent Access is the recommended installation foundation; this project adds app resources and optional component previews.

Run these commands from the site's Composer root:

```sh
composer config repositories.mcp_apps vcs https://github.com/ronaldtebrake/drupal_mcp_apps.git
composer config minimum-stability dev
composer config prefer-stable true
composer config allow-plugins.cweagans/composer-patches true
composer require drupal/mcp_apps:dev-main drush/drush:^13 \
  'drupal/mcp_server:dev-2.x#53e08bccf3722c08d17e192c0607e14bd1447e27' \
  'drupal/tool:dev-1.0.x#4c5809cd3d7c8dba0d71a55729a29c4695dca595' \
  'drupal/mcp_server_tool_bridge:dev-1.x#38aebe6431a48b33471adfeb909038af427007ca' \
  --with-all-dependencies
composer patches-relock
composer patches-repatch
vendor/bin/drush en mcp_apps -y
vendor/bin/drush updb -y
```

Composer Patches discovers the three checksum-pinned MR diffs from this package's `extra.patches`. Commit the site's Composer files and `patches.lock.json`. If an MR changes, review it and deliberately update its checksum. The `patches/` directory contains reviewable snapshots. No patch helper script is required.

Until the contributions have releases, the three patch base commits must also be required in the root project: Composer ignores commit references declared only by dependencies. Requiring just this module can select newer branch commits on which the patches no longer apply.

### Add the OpenUI integration

```sh
vendor/bin/drush en mcp_apps_openui -y
vendor/bin/drush cr
```

Grant **Use MCP Apps composer** to the intended role, alongside Agent Access's MCP endpoint permissions. For Media selection, also grant **View media** and **View published content** (`access content`). Configure an OAuth scope carrying that role through Agent Access's documented setup. An OAuth login alone does not grant module permissions.

Connect an MCP Apps host to `https://YOUR-DRUPAL-HOST/mcp`. Refresh its tool catalog after enabling submodules. Use `tool_api__sdc_component_catalog` to discover a module/theme's components, then `tool_api__component_composer_open` with an OpenUI `program` and an installed `theme`.

### Add the optional showcase

Composer does not infer optional dependencies from enabled submodules. Install them explicitly. The current showcase was tested with Mercury **1.0.5** and Canvas **1.11.0**:

```sh
composer require drupal/mercury:1.0.5 drupal/canvas:1.11.0 drupal/cva:^1.0
cd web
../vendor/bin/dr generate-theme mcp_apps_demo_theme --name='MCP Apps demo' --starterkit=mercury --path=themes/custom
cd ..
vendor/bin/drush theme:enable mcp_apps_demo_theme
vendor/bin/drush en mcp_apps_demo -y
vendor/bin/drush php:script web/modules/contrib/mcp_apps/modules/mcp_apps_demo/scripts/seed_demo.php
vendor/bin/drush cr
```

Theme generation requires Drupal core's starterkit dependencies: install `drupal/core-dev` matching your site's core version if they are not already available. Adapt `web/` and the module path to your site's layout. The preview theme is enabled without changing the site's default theme.

The explicit, repeatable seed imports six image Media entities and creates a dedicated Image media type if needed. It uses UUID tracking to avoid duplicates. It does not create articles, OAuth scopes, roles, or grant permissions. Existing content and files are preserved when upgrading from the hero demo; its tools and presentation overrides are retired by the update hook (`drush updb`).

### Compare Mercury and Byte components

For an optional component comparison, install and enable Byte alongside the
Mercury-generated demo theme. Tested with Byte theme **1.0.3**:

```sh
composer require drupal/byte_theme:1.0.3
vendor/bin/drush theme:enable byte_theme
vendor/bin/drush cr
```

This uses Byte's SDCs for isolated previews. It does not apply the full Byte
site recipe or change the default theme. Byte's maintainers recommend the
[complete Byte template](https://www.drupal.org/project/byte) for a complete
website and discourage using its theme as a base theme.

Ask the agent to use `mcp_apps_demo_theme` for Mercury or `byte_theme` for Byte.
Pass the same value as `provider` to `sdc_component_catalog` and as `theme` to
`component_composer_open`. Omitting `program` opens the same Rotterdam sample
adapted to that theme's actual schemas. Byte uses Section's columns for the
highlights; Mercury uses its separate Grid component. Templates and styles
remain owned by their respective themes.

For browser comparison, open `/admin/content/mcp-apps/composer?theme=byte_theme`
and `/admin/content/mcp-apps/composer?theme=mcp_apps_demo_theme` in separate tabs.

### Add the activity dashboard

After enabling `mcp_apps_openui`, enable the independent activity showcase:

```sh
vendor/bin/drush en mcp_apps_activity_demo -y
vendor/bin/drush cr
```

No extra Composer packages, theme generation, content imports, or Canvas/Media
modules are required. Chart.js is bundled locally with its MIT notice. The
dataset contains eight fictional Rotterdam stories, fictional editorial
activity, and a fixed 60-day readership sample. It does not read real analytics
or create content entities.

Refresh the MCP connection, then ask:

> Show the Editorial pulse dashboard for the last 30 days in the MCP App.

`tool_api__activity_dashboard_open` opens the app. Its day-range and section
filters call an app-only tool, which invokes `editorial_activity` through Tool
API and maps the report into ordinary SDC props. The data tool is also available
independently as `tool_api__editorial_activity`, without app metadata.

Individual components also have filterable views. Ask for **Readership over
time** or **Stories making an impact** with a period; the agent passes
`view: readership` or `view: stories` to `activity_dashboard_open`. Period and
section changes preserve the selected component, using the same Tool API report
and native SDCs as the dashboard. `activity` and `metrics` are also available.

Choose 7, 14, or 30 days, or **Custom period** and enter any whole number from
1 to 30, then press **Apply**. The 60-day sample supports an equally long
previous-period comparison for every selection. The tools accept the same open
range; for example, ask for readership over the last 14 days.

Compare the MCP App with `/admin/content/mcp-apps/activity`, a normal Drupal
render array using the same composition, Twig templates, CSS, and Chart.js.
The dashboard follows the site's default front-end theme, including its fonts
and design tokens. Switch between Byte and Mercury in Appearance, then press
Refresh in the app to compare the same components and data in either theme.
Chart style, accessible tables, and story drilldowns work inside the native
components without model calls.

For local browser testing without an MCP host, optionally enable
`mcp_apps_activity_dev` and open `/admin/content/mcp-apps/activity/app`.
That module owns the browser app and CSRF-protected transport routes. Neither
route is needed by the MCP App. See [the activity demo guide](modules/mcp_apps_activity_demo/README.md).

## Try the landing-page demo

Ask:

> What would a Rotterdam weekend landing page with a hero, three highlights, and a call to action, using components from mcp_apps_demo_theme look like? Display it in the MCP App.

The agent can retrieve `tool_api__sdc_component_catalog` with provider `mcp_apps_demo_theme`, generate a static OpenUI composition, and pass it to `tool_api__component_composer_open`. Calling the open tool without a program loads the optional Rotterdam sample.

OpenUI uses `Page([DrupalComponent(componentId, props, namedSlots)])`. Props are JSON objects; named slots contain ordered `DrupalComponent` lists. Image props in the showcase accept `{media_id: ID}`. Arbitrary JavaScript, OpenUI state, Query, and Mutation expressions are rejected in this first adapter. The actual OpenUI parser performs parsing; PHP receives the resulting plain tree.

`Page` is an OpenUI composition container, not a Drupal or Canvas page entity.

The HTML resource is `ui://drupal/component-composer`, served as `text/html;profile=mcp-app`. The host initializes the official MCP Apps SDK, forwards tool-result `_meta`, and mediates preview, asset, and Media calls. A host supporting only ordinary MCP tools receives structured results, without an interactive app.

For browser development, `/admin/content/mcp-apps/composer` runs the same bundle with a permission-checked, CSRF-protected Drupal transport. It is separate from verification of the MCP host handshake.

## Rendering and access boundaries

Drupal is authoritative for props, enum/required constraints, component discovery, and Media/field/file access. PHP-object schemas and unresolved references are reported as unsupported. Unknown components, render-array props, unsafe URLs, inaccessible Media, and private image files are rejected.

Preview assets are limited to installed extension assets and access-checked Media referenced by the composition. Hash-addressed, bounded chunks travel through app-only tools in presentation metadata. HTML and image bytes stay outside model-visible structured outputs. The preview runs in an opaque-origin sandbox with a CSP that blocks network requests. A failed composition leaves the last valid preview visible.

Resources default to max-age zero. Account-specific resources must remain uncached for now: the pinned MCP Server implementation does not correctly resolve cached resource variation pointers across accounts. Preview sessions expire after one hour and are bound to the authenticated account.

This first adapter supports static, JSON-compatible SDC compositions and local attached CSS, JavaScript, fonts, and images. External assets, private files, PHP-object props, and OpenUI Query/Mutation/state expressions are unsupported. It is a component preview, not a complete themed Drupal page or form renderer. The optional Media adapter recognizes Canvas's image schema; other image-prop formats need an adapter. The activity demo explicitly maps a Tool API report to component props; automatic binding of arbitrary tools to components is not implemented.

## Contrib dependencies

| Project | Contribution |
| --- | --- |
| [MCP Server MR !89](https://git.drupalcode.org/project/mcp_server/-/merge_requests/89) | SDK extension registration and native tool/resource metadata preservation |
| [Tool API MR !206](https://git.drupalcode.org/project/tool/-/merge_requests/206) | Generic definition and execution-result metadata |
| [MCP Server Tool Bridge MR !28](https://git.drupalcode.org/project/mcp_server_tool_bridge/-/merge_requests/28) | Forward only Tool API's `meta['mcp']` namespace into MCP definitions and results |

Modules use native ResourceProvider and Tool API plugins. They do not replace the MCP server factory or implement JSON-RPC. Host iframe isolation remains the host's responsibility; WebMCP and hosted OpenUI services are not used.

## Development and tests

The bundled HTML is shipped with the module; production needs no Node server. To rebuild reproducibly:

```sh
cd web/modules/contrib/mcp_apps
npm ci
npm run build
```

With Drupal core-dev and the usual PHPUnit database/base URL configuration:

```sh
vendor/bin/phpunit web/modules/contrib/mcp_apps/tests/src
DRUPAL_BASE_URL=https://YOUR-DRUPAL-HOST python3 web/modules/contrib/mcp_apps/tests/protocol_smoke.py
vendor/bin/drush php:script web/modules/contrib/mcp_apps/tests/composer_smoke.php
cd web/modules/contrib/mcp_apps
npm run test:ui
```

The protocol smoke uses the local Drush `admin` account (`DRUPAL_USER` overrides it); this is separate from HTTP OAuth verification. The UI smoke uses the actual bundle and SDK with a simulated host and a fixture generated from native Drupal rendering. Tests cover two independent apps, permissions, account isolation, standalone SDC rendering without Canvas, nested slots, schema errors, Media/field/private-file access, asset transport, responsive controls, and last-valid-preview behavior.

To run the optional Byte comparison through the same smoke tests, set
`MCP_APPS_PREVIEW_THEME=byte_theme` for the protocol and PHP smoke scripts.
Set `MCP_APPS_UI_FIXTURE=/tmp/mcp-composer-byte-fixture.json` when generating the
Byte fixture and running `npm run test:ui` to retain the Mercury fixture.

With `mcp_apps_activity_demo` enabled, run its independent protocol and UI smoke:

```sh
python3 web/modules/contrib/mcp_apps/tests/activity_protocol_smoke.py
vendor/bin/drush php:script web/modules/contrib/mcp_apps/tests/activity_smoke.php
cd web/modules/contrib/mcp_apps
npm run test:activity
```

Its Drupal tests cover permissions, invalid filters, cross-account sessions,
CSRF protection, and native rendering without Canvas. The UI test covers the
official SDK handshake, filtering, sequential asset calls, errors, responsive
widths, cancellation, and source-checked iframe sizing.

## Licenses and credits

The module's code is GPL-2.0-or-later. OpenUI is MIT licensed; its full notice and bundled dependency notices are retained in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md). Mercury supplies the showcase component templates and styles; they are generated from its starterkit rather than copied into this module. The optional Byte comparison uses the unmodified components and assets from the Composer-installed Byte theme, which retains its own licenses and credits.

The six photographs are licensed separately under the [Unsplash License](https://unsplash.com/license). Copyright remains with **Arnout van Nieuwkoop, Alexander Psiuk, micheile henderson, Roel Oosterwijk, Ufoma Ojo, and Mitchell Leach**. The photographs were not AI generated. Preserve their individual credits and source links in [the image license file](modules/mcp_apps_demo/assets/LICENSE.md) and [manifest](modules/mcp_apps_demo/assets/manifest.json) when redistributing this demo.
