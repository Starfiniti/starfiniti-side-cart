# Compatibility matrix

Version: 1.3.8
Date: 2026-07-18

| Area | Status | Notes |
|---|---|---|
| PHP 8.1–8.4 | Supported target | Static analysis and code style target PHP 8.1+; staging validation runs on PHP 8.1 and CI covers PHP 8.1–8.4. |
| WordPress 6.6–current | Supported target | Playground uses latest WordPress for smoke validation. |
| WooCommerce 9.0–current | Supported target | WooCommerce is the only required plugin. |
| HPOS | Supported | Declares custom order tables compatibility and uses WooCommerce CRUD for order/refund reads. |
| Classic themes/templates | Supported | Classic AJAX add-to-cart and normal product/cart flows are tested. |
| WooCommerce Blocks | Supported | Listens for native Blocks add-to-cart events and exposes Store API extension data. |
| Checkout Blocks/classic checkout | Unchanged | Drawer assets are not loaded on checkout and checkout processing remains WooCommerce-owned. |
| Mobile | Supported | Drawer uses responsive layout, overlay, focus trap, and tested mobile viewport behavior. |
| RTL | Supported target | Build emits RTL CSS assets. |
| WPML/Polylang/TranslatePress | Adapter support | Product ID mapping hooks are included where plugin filters/functions are available. |
| Multicurrency plugins | Adapter support | Stored reward thresholds are converted once through the first detected currency provider and expose `sfcart_compatible_reward_amount`; WooCommerce cart totals are never converted again. |
| Subscriptions/bundles/composites | Explicit adapter required | Complex product types fail closed unless an integration implements their complete drawer selection/configuration contract and opts in through `sfcart_*` hooks. |
| Official WooCommerce Stripe | Adapter support | Only relocates official classic-cart express rendering where already registered. |
| Official WooCommerce PayPal Payments | Adapter support | Uses the gateway's mini-cart renderer hook when available. |
| Breakdance/classic/block builders | Supported target | Drawer is mounted independently and can be opened by shortcode, block, floating toggle, or `#starfiniti-cart` link. |
| LiteSpeed Cache for WordPress | Adapter support | Registers the custom nonce for ESI, marks REST/AJAX responses non-cacheable, tags pages containing the drawer, and purges only that cache tag when effective settings change. |
| Other page/cache optimization plugins | Supported target | Dynamic Starfiniti Cart REST/AJAX responses use standard WordPress no-cache controls. |

## LiteSpeed Cache configuration

- Enable **LiteSpeed Cache > Cache > ESI > Enable ESI** when the server is
  LiteSpeed Enterprise or the site uses QUIC.cloud. OpenLiteSpeed does not
  provide ESI.
- Leave **LiteSpeed Cache > Cache > WooCommerce > Vary for Mini Cart** off.
  Starfiniti Cart loads the visitor's cart through its own WooCommerce AJAX
  state endpoint, so it does not render cached cart contents into public HTML.
- Keep WooCommerce Cart, Checkout, and My Account pages excluded from public
  page cache. LiteSpeed Cache applies these exclusions by default.
- Pages that render the drawer receive the dedicated LiteSpeed cache tag
  `sfcart`. After changing Starfiniti Cart settings, the plugin purges only
  that tag so unrelated cached pages remain untouched.

References: [LiteSpeed Cache settings](https://docs.litespeedtech.com/lscache/lscwp/cache/)
and [LiteSpeed Cache API](https://docs.litespeedtech.com/lscache/lscwp/api/).

## Compatibility principle

Starfiniti Cart integrates through WooCommerce APIs, Starfiniti-owned
`sfcart_*` hooks, and gateway-owned rendering hooks. It does not load FunnelKit
runtime code and does not change checkout or payment processing.
