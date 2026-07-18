# Upstream source record

Recorded on 2026-07-16 for the Starfiniti Cart clean-room implementation.

| Archive | Declared version | SHA-256 | Purpose |
|---|---:|---|---|
| `cart-for-woocommerce.1.9.1.2 (1).zip` | 1.9.1.2 | `F12DD58D7054932D3A59DDE04168CC94BF75039F6267571B2A88FA1DFC1470AE` | Side-cart functional and compatibility baseline |
| `funnel-builder.3.15.0.9.zip` | 3.15.0.9 | `8B1EB7A9BFA4607C102D80CCF60A77ACDA8E4580EC1F5A839471F47DD3CF29FC` | Required only to understand shared dependencies and migration boundaries |
| `funnel-builder-pro-v3.15.0.14.zip` | 3.15.0.14; bundled cart-pro module 0.9.1 | `87DAA3E628E619B51990F9E5AD1B7DDA1F57A9ECAD43510411FB88571673702A` | Premium side-cart features: upsells, rewards, special add-on, and cart analytics |

The second supplied `cart-for-woocommerce.1.9.1.2.zip` archive was byte-identical
to the `(1)` copy and had the same SHA-256 fingerprint.

## Scope boundary

Only side-cart behavior is a functional baseline. The following Funnel Builder
areas are explicitly excluded from Starfiniti Cart:

- checkout building or checkout-page changes;
- funnels, order bumps, and post-purchase/one-click upsells;
- payment or order processing;
- FunnelKit licensing, telemetry, updates, branding, and shared admin services.

No source archive is committed. The repository begins with independently named,
WooCommerce-only bootstrap and build code. Any later file substantially adapted
from an upstream file must identify the upstream work and the date/nature of the
Starfiniti modifications in its header.

## Batch 2 implementation note

The drawer state serializer, mutation endpoints, frontend modules, styles,
markup, block, and test suite were independently implemented under Starfiniti
identifiers. The upstream Cart 1.9.1.2 release was used to enumerate expected
side-cart behavior such as quantity controls, metadata, coupons, totals,
backorders, and empty-state handling; no upstream executable module or branded
asset was copied into the runtime.

## Batch 3 implementation note

The versioned settings schema, React administration application, REST
controllers, WordPress media integration, and live preview are owned Starfiniti
implementations. No upstream administration, licensing, telemetry, shared
navigation, or updater code is used. Settings for later feature engines remain
dormant until their approved batches.

## Batch 4 implementation note

The Cart 1.9.1.2 `upsell-style1`, `upsell-style2`, and `upsell-style3`
templates were reviewed to identify the one-card carousel, two-column card, and
stacked panel presentation modes. Starfiniti's recommendation resolver,
TypeScript renderers, CSS, AJAX add and impression endpoints, order attribution,
REST relationship controller, and React manager are independent owned
implementations. Runtime relationships are read and written through native
WooCommerce product CRUD APIs; no FunnelKit Pro class, analytics table, shared
loader, or runtime identifier is used.

## Batch 5 implementation note

The bundled Cart Pro reward module was reviewed to enumerate its coupon,
free-gift, free-shipping, progress-message, threshold-reversal, and variable
gift behaviors. Starfiniti's settings schema, React milestone editor,
WooCommerce-session reconciler, shipping-rate filter, drawer renderer, order
metadata, and tests are independent owned implementations. The runtime uses
WooCommerce customer and shipping APIs directly; no upstream geolocation,
shared loader, SQL helper, session key, or executable identifier is used.

## Batch 6 implementation note

The bundled Cart Pro special-add-on class and template were reviewed to
enumerate checkbox/toggle selection, preselection, product/custom imagery,
variable-product, and presentation behaviors. Starfiniti's settings, React
controls, cart reconciler, AJAX endpoint, drawer renderer, order metadata, and
tests are independent owned implementations.

The official WooCommerce Stripe 10.8.4 and WooCommerce PayPal Payments 4.1.1
sources were reviewed to establish gateway-owned rendering boundaries. PayPal
is connected only through its public mini-cart renderer-hook filter. Stripe is
connected only by relocating its official classic-cart callback when that
callback is already registered on a supported classic cart page; block-based
gateway controls remain native. Starfiniti Cart stores no credentials and
contains no payment, order-processing, checkout-page, or gateway-control
implementation.

## Batch 7 implementation note

The bundled Cart Pro analytics surfaces were reviewed as a feature inventory
only. Starfiniti's conversion tables, idempotent order/refund recorder, report
queries, CSV exporter, REST routes, React dashboard, and tests are independent
owned implementations. Runtime analytics are based on WooCommerce CRUD/HPOS
order and refund objects plus Starfiniti-owned recommendation, reward, and
special-add-on metadata. No upstream SQL helper, admin chart, telemetry,
licensing, or Funnel Builder runtime service is used.

## Batch 8 implementation note

Cart 1.9.1.2 and the bundled Cart Pro module were reviewed to identify legacy
storage boundaries: `fkcart_settings`, `fk_cart`, `fk_cart_products`, and
cart-only compatibility behavior around currency, multilingual products,
subscriptions, bundles, and caching. Starfiniti's migration service, REST
controller, React Tools flow, compatibility manager, and tests are independent
owned implementations.

The migration code copies legacy settings and analytics into Starfiniti-owned
storage without deleting or mutating legacy data. It does not migrate transient
WooCommerce sessions and does not provide runtime `fkcart_*` aliases. Checkout,
funnel, order-bump, payment, licensing, telemetry, updater, and shared Funnel
Builder services remain excluded.
