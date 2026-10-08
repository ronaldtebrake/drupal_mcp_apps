# MCP Apps OpenUI

Compose existing Drupal Single Directory Components inside an MCP App. OpenUI
describes the composition; Drupal renders the original Twig templates and their
attached assets. No component templates are reimplemented in React.

This is the reusable integration in this project. The Rotterdam demo, Mercury,
Canvas authoring, and sample images are not required for ordinary SDC previews.
Install through the [project instructions](../../README.md), then enable
`mcp_apps_openui` and grant `use mcp apps composer` to the intended authenticated
role. Agent Access supplies the documented OAuth and MCP connection setup.

## How it works

1. `sdc_component_catalog` describes SDCs discovered by Drupal's component
   manager. The optional `provider` input selects a module or installed theme.
2. The host agent generates a static OpenUI program using `Page` and
   `DrupalComponent`, then calls `component_composer_open` with that program
   and an installed preview theme.
3. MCP Server serves `ui://drupal/component-composer`. The bundled app connects
   through the official MCP Apps SDK and parses the program with OpenUI.
4. `component_composer_preview` validates the resulting JSON tree. The
   `Composition` service builds native `#type: component` render arrays;
   `PreviewRenderer` renders them with Drupal's renderer and asset resolver.
5. The app retrieves only assets attached to that validated preview through
   `component_composer_asset`, then displays the output in a sandboxed iframe.

Tool IDs above receive the `tool_api__` prefix through MCP Server Tool Bridge.
Preview and asset tools have MCP Apps visibility `app`; the catalog and open
tool are available to the host agent. Permission checks still apply to every
tool and resource; visibility is not an access-control mechanism.

## Component composition

For an installed `my_theme:heading` SDC with a string prop named `text`:

```text
root = Page([
  DrupalComponent("my_theme:heading", {"text": "Hello from Drupal"}, {})
])
```

Always use IDs, props, and slot names from the catalog. The third argument maps
named slots to ordered `DrupalComponent` lists. `Page` is a composition
container; it does not create a Drupal or Canvas page. Changing props or slots
updates the preview without another LLM call. Nothing is saved.

## Extending the integration

Adding an SDC to an enabled module or installed theme makes it discoverable;
no OpenUI-specific component registration is required. Components must have
JSON-compatible prop schemas and supported local assets.

Modules can implement `hook_mcp_apps_composer_defaults_alter()` to supply an
initial component tree when no program was provided. The optional demo uses
this hook; its sample composition does not live in the reusable integration.
See [mcp_apps_openui.api.php](mcp_apps_openui.api.php).

The base `mcp_apps` module currently supplies `AppResourceBuilder` and the
JavaScript host helper. Protocol registration belongs to MCP Server and the
PHP SDK. These helpers can move with OpenUI if it becomes a separate project.

## Current boundaries

- Static compositions only: no arbitrary JavaScript, OpenUI Query, Mutation,
  state expressions, or automatic tool-to-component data binding.
- Plain JSON props only: PHP objects and unresolved schema references are
  reported as unsupported. Drupal remains authoritative for validation.
- Local attached CSS, JavaScript, fonts, and images only; external assets and
  private files are rejected. Full-page wrappers and Drupal forms are outside
  this preview adapter.
- Media references require accessible public image Media and the Canvas image
  prop schema. The optional demo supplies the picker tool and thumbnails.
- Sessions belong to the authenticated account and expire after one hour.
  Calls are queued because Drupal's HTTP MCP transport locks each session.

For browser development, `/admin/content/mcp-apps/composer` uses the same bundle
through a permission-checked, CSRF-protected Drupal endpoint. Testing there
does not replace verification in an MCP Apps host.
