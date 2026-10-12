# Drupal components in a Commerce MCP App

**AI disclosure:** OpenAI Codex assisted with the module, illustrations, tests, documentation and contrib patches. This is a proof of concept; the upstream APIs are still supplied through pinned contributions where needed.

Fieldwork Supply is a small coffee-gear shop powered by real Drupal Commerce products, variations, prices, carts, orders and simulated payments. The same Drupal SDCs render the shop on the website and inside an MCP App. OpenUI describes the composition; Drupal renders the original Twig templates, CSS, JavaScript and artwork.

Ask:

> Help me choose a coffee grinder under €150. Show the shopping app.

The compact catalog shows filters and product cards. Compare products, choose a finish and add an item. A small basket summary shows its count and total; **Proceed** opens the separate basket component. **Back to products** preserves your filters and comparison. Review the total and place a **test order inside the app**. No real money is charged, no receipt email is sent, and no delivery or fulfilment is initiated.

## Modules

| Module | Responsibility |
| --- | --- |
| `mcp_apps` | Module-owned app resources, SDK metadata and a JavaScript host helper |
| `mcp_apps_openui` | OpenUI parsing, SDC discovery and validation, native rendering and account-owned preview sessions |
| `mcp_apps_commerce_demo` | Optional Commerce tools, shared shopping SDCs, explicit catalog seeding and simulated checkout |
| `mcp_apps_commerce_dev` | Optional browser app preview for development; unnecessary for MCP hosting |

The foundation and OpenUI integration do not depend on Commerce or Canvas. The OpenUI integration is the intended reusable contribution. Shopping code lives in the optional demo.

The previous Rotterdam and Editorial pulse showcases have been retired. Existing seeded content and files are preserved. Historical screenshots in `docs/screenshots` describe the earlier showcase, not the current Commerce interface.

## Install on top of Agent Access

Start with Drupal **11.4+** and PHP **8.3+**. Apply and configure the [Agent Access recipe](https://www.drupal.org/project/agent_access), following its documentation for OAuth signing keys, clients, scopes and an HTTPS MCP connection.

From your site's Composer root:

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
vendor/bin/drush en mcp_apps_openui -y
vendor/bin/drush updb -y
```

Composer Patches discovers the checksum-pinned MR diffs in this package's `extra.patches`. Commit your site's Composer files and `patches.lock.json`. The `patches/` directory retains reviewable snapshots. No helper script is required.

Require the patch base commits in the root project until compatible releases are available: Composer ignores commit references declared only by dependencies. Review changed MRs before deliberately updating their checksums.

To replace an existing showcase installation, uninstall `mcp_apps_activity_dev`, `mcp_apps_activity_demo` and `mcp_apps_demo` **before updating the code**. This removes their registration and configuration without deleting imported content or files.

## Enable the Commerce showcase

Composer does not infer optional dependencies from enabled submodules. Install Commerce explicitly; this demo was tested with **Commerce 3.3.10**:

```sh
composer require drupal/commerce:^3.3
vendor/bin/drush en mcp_apps_commerce_demo -y
vendor/bin/drush php:script web/modules/contrib/mcp_apps/modules/mcp_apps_commerce_demo/scripts/seed_demo.php
vendor/bin/drush cr
```

Adapt `web/` and the module path to your site's layout. Seeding is explicit and repeatable: it creates a dedicated EUR demo store, six products, their variations, a separate order type and a test-only payment gateway. It preserves existing products, other stores and the default store.

Grant the intended authenticated role:

- **Use MCP Apps composer**
- **Use MCP Apps Commerce demo**
- **View products** (`view commerce_product`)
- **View own orders** (`view own commerce_order`) for order-page handoff
- **Place MCP Apps test orders** only when that role should complete simulated checkout

Configure an OAuth scope carrying the role through Agent Access. OAuth authentication alone does not grant these permissions. The module does not grant them automatically.

Connect your MCP Apps host to `https://YOUR-DRUPAL-HOST/mcp` and refresh its tool catalog. Use `tool_api__commerce_shop_open`; its optional inputs are `query`, `category` (`all`, `grinders`, `brewing`, `accessories`) and `budget` in EUR. Zero means no budget limit. Set `mode: choose` for requests such as “help me choose the best grinder”: matching products show their specifications immediately, without comparison checkboxes or a duplicate comparison panel. `browse` keeps manual comparison available; `auto` (the default) shows specifications immediately when there are two or three matches. The model chooses the mode; Drupal validates it and renders the same components.

The ordinary website is `/shop`. It uses the same component tree, Twig templates and component JavaScript. The authenticated user's basket is shared with the MCP App.

For local browser QA only:

```sh
vendor/bin/drush en mcp_apps_commerce_dev -y
```

Open `/shop/app?category=grinders&budget=150`. This uses the shipped app bundle with a local CSRF-protected transport. It does not verify the MCP host handshake. Neither development route is necessary for the MCP App.

## Reuse and rendering

`commerce_demo_catalog`, `commerce_demo_cart`, `commerce_demo_cart_update` and `commerce_demo_checkout` are Tool API operations. The shop orchestrator calls those tools with validation and access checks; it does not bypass them to mutate entities.

Those adapters delegate to Commerce's price resolvers, availability checkers, Cart Provider, Cart Manager, order refresh, state-machine transition and payment gateway APIs. They are not a general replacement for Commerce checkout.

The shared SDCs are `shop`, `product`, `comparison`, `basket_summary`, `basket` and `receipt`. Discover them through `sdc_component_catalog` with provider `mcp_apps_commerce_demo`. They can also be composed independently with `Page([DrupalComponent(id, props, namedSlots)])`. `Page` is a composition container, not a saved Drupal page.

OpenUI uses its official parser and React renderer for the composition wrapper. Drupal renders the actual components. The component JavaScript supplies variation selection, comparison, quantity controls and order review on both surfaces. In the app, actions pass through the host's existing MCP connection. No further model call is required for each interaction.

The app resource is `ui://drupal/commerce-shop`, with MIME type `text/html;profile=mcp-app`. HTML and asset bytes travel in app presentation metadata, outside model-visible structured results. Attached files are restricted to installed extensions and access-checked Media, bounded by size and count. The native preview uses an opaque-origin sandbox with a CSP blocking network requests.

## Checkout boundaries

The demo uses a separate order type and store. Cart and checkout operations require the authenticated owner, even for administrators. Products and variations must be published and accessible; field access and Commerce availability are checked.

Quantity writes use absolute quantities, customer-scoped locks and database transactions. Checkout recalculates prices, requires the server-issued reviewed basket revision, and rejects changed totals. It records payment through a dedicated gateway that only supports **test mode**, then uses Commerce's order-placement transition. Failed operations roll back; repeated successful checkout returns the same order instead of recording another payment.

This is a deliberately limited simulated checkout: no address collection, shipping, external payment processor, real inventory fulfilment or production checkout-pane integration. A real storefront integration should use Commerce's configured checkout and gateway behaviour.

Preview sessions expire after one hour and belong to the authenticated account. A failed render keeps the last successful app view. General SDC previews support static JSON-compatible props and local assets; arbitrary OpenUI state, Query/Mutation expressions, remote assets and PHP-object props remain unsupported.

## Contrib integration

| Project | Contribution |
| --- | --- |
| [MCP Server !89](https://git.drupalcode.org/project/mcp_server/-/merge_requests/89) | SDK extension registration and native metadata preservation |
| [Tool API !206](https://git.drupalcode.org/project/tool/-/merge_requests/206) | Generic definition and execution-result metadata |
| [MCP Server Tool Bridge !28](https://git.drupalcode.org/project/mcp_server_tool_bridge/-/merge_requests/28) | Forward Tool API's `meta['mcp']` namespace |

Native ResourceProvider and Tool API plugins own registration. This project does not replace the server factory or implement JSON-RPC. WebMCP and hosted OpenUI services are not used.

Commerce MCP was investigated before building adapters; its public repository advertised no branches at implementation time. The demo uses Commerce Core's existing services rather than a second commerce engine.

## Development and tests

Production needs no Node server. Rebuild the shipped bundles reproducibly:

```sh
cd web/modules/contrib/mcp_apps
npm ci
npm run build
```

Install Drupal core-dev matching your core version and Commerce for the demo tests. Configure PHPUnit's database and base URL, then run:

```sh
vendor/bin/phpunit web/modules/contrib/mcp_apps/tests/src
python3 web/modules/contrib/mcp_apps/tests/protocol_smoke.py
vendor/bin/drush php:script web/modules/contrib/mcp_apps/tests/composer_smoke.php
vendor/bin/drush php:script web/modules/contrib/mcp_apps/tests/commerce_smoke.php
cd web/modules/contrib/mcp_apps
npm run test:ui
npm run test:commerce
```

The protocol smoke uses Drush's local `admin` account (`DRUPAL_USER` overrides it). It exercises the real PHP SDK transport; it is separate from HTTP OAuth verification. Frontend tests use the shipped bundles, official MCP Apps SDK and fixtures generated by native Drupal rendering.

Tests cover independent app registration, SDC schemas and asset boundaries, permission and field denials, account isolation, real Commerce carts, unavailable variations, stale prices, test-only gateways, rollback, repeated checkout, CSRF, SDK initialization and app actions.

## Licenses and credits

Module code and the original SVG product illustrations are **GPL-2.0-or-later**. Codex assisted in producing the SVG source. No stock product photographs or third-party theme component templates are bundled in the Commerce demo.

OpenUI is MIT licensed. Full notices for the locally bundled frontend dependencies are retained in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md). The installed site theme keeps its own licenses and supplies the native preview's fonts and base styles.

Historical showcase screenshots remain under `docs/screenshots`. The retired sample photography and its original credits remain recoverable in Git history; existing imported Media files are preserved on the site.
