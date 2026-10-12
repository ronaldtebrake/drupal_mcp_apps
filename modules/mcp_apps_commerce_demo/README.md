# Fieldwork Supply Commerce showcase

See [the project README](../../README.md) for installation on Agent Access, explicit Commerce dependencies, seeding, role permissions, rendering and checkout boundaries.

The demo owns six ordinary SDCs and four reusable Commerce Tool API operations. The website at `/shop` and MCP App at `ui://drupal/commerce-shop` use the same components and customer cart.

The website has a dedicated page template and storefront stylesheet, scoped to `/shop`. It keeps Drupal's main content and status messages inside a shop header and footer, with larger typography and a responsive catalog grid. The app retains its compact presentation. Themes can override `page--mcp-apps-shop.html.twig`; other site pages are unaffected.

Try: **“Help me choose a coffee grinder under €150. Show the shopping app.”**

Choice requests open with product specifications expanded. Set `mode: choose` explicitly, or let `auto` compare two or three matches. For manual comparison use `mode: browse`. Choose a variation, add it to your basket, choose **Proceed** in the compact summary, then **Review test order** and **Place test order**. The basket is a separate view; **Back to products** keeps your filters and shortlist. The dedicated simulated gateway records a real Commerce test payment and completed order. No real charge, email or delivery occurs.

Product illustrations are original SVG source distributed under GPL-2.0-or-later, created with Codex assistance. Products, copy and store branding are fictional demo content.
