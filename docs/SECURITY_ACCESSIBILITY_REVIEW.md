# Security and accessibility review

Version: 1.4.0
Date: 2026-07-18

## Security checklist

- Admin REST routes require `manage_woocommerce`.
- REST writes require WordPress REST cookie authentication and nonce middleware.
- AJAX cart mutations require the plugin nonce.
- Public analytics accepts only allowlisted events, derives cart state on the
  server, deduplicates events in bounded time windows, and rate-limits writes
  per WooCommerce session.
- Settings writes are allowlisted and normalized through `Settings::sanitize`.
- Product, coupon, relationship, reward, add-on, analytics, and migration inputs
  are sanitized, bounded, and validated before persistence.
- SQL writes use Starfiniti-owned tables and WooCommerce CRUD/HPOS APIs where
  order data is involved.
- CSV export escapes formula-leading characters to reduce spreadsheet injection
  risk.
- Upload handling is limited to WordPress media selection by attachment ID; URLs
  are derived from WordPress rather than trusted from request payloads.
- The plugin stores no payment credentials and implements no payment or order
  processing.
- Source isolation tests prevent FunnelKit runtime identifiers outside the
  explicit migration boundary.

## Accessibility checklist

- Drawer uses dialog semantics, labelled controls, and focus trapping.
- Focus returns to the triggering element after close.
- Escape closes the drawer.
- Overlay and responsive layout support keyboard and pointer flows.
- Cart count and notices update through owned drawer state events.
- Reduced-motion styles are included.
- Configurable foreground/background color pairs are validated against the
  WCAG AA 4.5:1 contrast threshold, including alpha-transparent colors.
- RTL styles are built.
- Checkout remains the normal WooCommerce checkout for established accessible
  payment and form behavior.

## Remaining release-candidate verification

Before production rollout on a client store, run the staging acceptance matrix
with that store's real theme, multilingual/currency stack, gateways,
subscription/bundle plugins, caching layer, tax/shipping setup, and checkout
configuration.
