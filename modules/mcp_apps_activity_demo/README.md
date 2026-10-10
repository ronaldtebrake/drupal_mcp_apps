# Editorial pulse: one interface, two surfaces

**AI disclosure:** OpenAI Codex assisted with this demo's code, fictional content,
tests, and documentation.

This optional showcase demonstrates the shared-tools/shared-components story:
an editorial dashboard on a Drupal page and inside an MCP App, using the same
Tool API report and the same SDCs. It complements the Mercury/Byte composition
demo and requires neither of those themes nor Canvas or Media.

## Try it

Install the parent project on Agent Access as described in the root README.
Enable `mcp_apps_activity_demo`, grant **Use MCP Apps composer** to the intended
authenticated role, and refresh the host's MCP connection. Ask:

> Show Editorial pulse for the last 30 days in the MCP App.

The open tool is `tool_api__activity_dashboard_open`; optional inputs are
`days: 1–30` (any whole number), `section: all | guides | culture`, and
`view: dashboard | readership | stories | activity | metrics`. Filters and Refresh run
through the host's existing MCP connection. Refresh reloads a fixed sample,
not live site metrics. No content is saved.

Choose 7, 14 or 30 days, or select **Custom period**, enter a whole number from
1 to 30, and press **Apply**. Typing does not call a tool. The bundled 60-day
dataset reserves an equally long previous period for every comparison.
Explore the period and section filters, switch the chart between Trend and
Bars, expand its data table, open a story, and compare mobile/full widths.
**Open in Drupal** opens the same selection on the normal website.

For the ordinary Drupal page, use `/admin/content/mcp-apps/activity`.
Browser QA is an optional development module, documented below.

For a focused, filterable component, ask:

> Show Stories making an impact for the last 30 days in the MCP App.

> Show Readership over time for the last 7 days in the MCP App.

The agent calls `activity_dashboard_open` with `view: stories` or
`view: readership`. These use the same app resource and toolbar as the full
dashboard, but render only the selected SDCs. The view is stored in the
account-bound session, so filtering never returns to the full dashboard.
The matching Drupal link also preserves the view and filters. Append
`?view=stories` or `?view=readership` to the website or development route.

Use this tool for interactive data views; `component_composer_open` remains
available for arbitrary static SDC compositions and prop editing.

## Theme comparison

Publication screenshots, captions and alt text are in
[docs/screenshots](../../docs/screenshots/CAPTIONS.txt). The app captures show
the browser preview, not a live MCP host conversation.

The app uses the site's default front-end theme, not its administration theme.
Its native preview loads that theme's libraries, including inherited base-theme
libraries, fonts and library overrides. The compact app controls reuse the
transported Drupal stylesheets. No theme assets are fetched from local URLs by
the host.

The activity components inherit fonts and use theme custom properties such as
`--background`, `--foreground`, `--card`, `--border`, `--primary`, and `--radius`.
Byte and Mercury both provide these tokens. Other themes use the component's
fallback colours, or can override the shared `--pulse-*` tokens or component
library. Chart.js resolves the same palette and font from the rendered component.

Install and enable Byte/Mercury as described in the root guide. Change the
default theme in **Appearance**, then press **Refresh** in an open activity app.
The selected view and filters stay intact. These are the same module-owned
activity SDCs in either theme; the separate Rotterdam demo compares the themes'
own hero, card and layout components.

## Local browser testing

The MCP App is served through `resources/read`; it needs neither a browser app
route nor an HTTP tool-call endpoint. On a development site only, enable:

```sh
vendor/bin/drush en mcp_apps_activity_dev -y
```

Open `/admin/content/mcp-apps/activity/app?view=stories`. The separate development
module serves the same bundle with a local transport at
`/admin/content/mcp-apps/activity/call`. Both require an authenticated account
with **Use MCP Apps composer**; calls are CSRF protected and allowlisted.
Disabling the development module removes both routes without affecting MCP.

## What is reused

```text
editorial_activity (Tool API)
    → DashboardComposition → normal Drupal SDC render array → website
    → DashboardComposition → OpenUI Page/DrupalComponent → validated native SDC preview → MCP App
```

The `editorial_activity` tool returns structured data without MCP App metadata.
The dashboard adapter invokes it through Tool API with input validation and
access checks; it does not bypass the plugin to read the dataset directly.

Five module-owned SDCs are discoverable through `sdc_component_catalog` with
provider `mcp_apps_activity_demo`: `dashboard`, `metric`, `readership`, `stories`,
and `activity`. The dashboard has named slots; each child can also be rendered
independently with ordinary JSON props. Twig is rendered only by Drupal.
React supplies the app toolbar and OpenUI wrapper, with no copies of the chart
or activity templates.

Chart.js handles chart drawing and hover interactions. Native HTML `details`
provides story drilldowns and a table alternative. These interactions live in
the components and work on both surfaces. Range/section filters call Tool API
and produce a new static OpenUI composition without an LLM round trip.

The UI resource is `ui://drupal/editorial-activity`, served through MCP Server's
ResourceProvider API. The demo reuses the parent project's SDK host helper,
OpenUI parser/renderer, native SDC validation, session ownership checks, and
bounded attached-asset transport. CSP denies network access in the preview;
there is no CDN dependency.

The dataset in `content/editorial.json` contains eight fictional stories,
fictional authors/activity, and sixty daily readership observations. The
snapshot date is fixed. Engaged minutes are a labelled estimate of 2.7 minutes
per read. These are bundled fixtures, not Drupal nodes, real users, or analytics.

## Community integration

[Drupal Charts](https://www.drupal.org/project/charts) offers Views and field
charting integrations. This small SDC showcase bundles the underlying
[Chart.js](https://www.chartjs.org/) library directly; it does not recreate a
Views/field chart framework. An existing analytics tool or Charts-backed SDC
could replace the demo adapter while retaining the same MCP App rendering path.
Canvas placement is a future integration; no Canvas component configuration is
installed by this demo.

The module code and sample content are GPL-2.0-or-later. Chart.js is MIT
licensed; its bundled dependencies' full notices are in the parent project's
`THIRD_PARTY_NOTICES.md`. No photographs or external image assets are used.
