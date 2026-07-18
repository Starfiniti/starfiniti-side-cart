# Changelog

All notable changes to Starfiniti Cart for WooCommerce are documented here.

## 1.4.0 - 2026-07-18

- Added native WordPress update discovery from stable public GitHub Releases.
- Added strict owned-repository URL validation, bounded release caching, and no-token update requests that transmit no store or customer data.
- Added fail-closed SHA-256 verification using GitHub asset digests or the published checksum file before WordPress installs an update.
- Added automated tagged-release publishing only after the full JavaScript, PHP, Playground, and MySQL test matrix passes.

## 1.3.8 - 2026-07-18

- Tagged only cacheable pages that render Starfiniti Cart with the dedicated LiteSpeed `sfcart` cache tag.
- Replaced the global LiteSpeed purge after settings changes with a targeted purge of the `sfcart` tag.
- Kept cart mutations entirely purge-free because visitor cart state continues to load through non-cacheable WooCommerce AJAX.

## 1.3.7 - 2026-07-18

- Registered the custom cart nonce with LiteSpeed Cache so ESI can refresh it independently from public page cache.
- Marked every Starfiniti Cart REST and WooCommerce AJAX response non-cacheable through both standard WordPress headers and the LiteSpeed API.
- Purged LiteSpeed page cache only when the effective cart settings document changes, preventing stale drawer markup without purging on ordinary requests.
- Documented the recommended LiteSpeed configuration: enable ESI where supported and leave Vary for Mini Cart disabled because Starfiniti Cart refreshes through JavaScript.

## 1.3.6 - 2026-07-18

- Hardened public cart analytics with server-owned event identifiers, allowlisted actions, server-resolved cart state, time-bucket deduplication, and a WooCommerce-session rate limit.
- Enforced WCAG AA contrast for configurable text, buttons, badges, cart triggers, and special add-ons, including eight-digit colors with alpha transparency.
- Required an actual boolean confirmation before a FunnelKit migration can run.
- Removed the external Google Fonts request from the administration screen and retained a local system-font stack.

## 1.3.5 - 2026-07-17

- Serialized drawer refreshes and mutations so out-of-order network responses cannot restore stale cart state.
- Made analytics ledger upserts atomic and linked recommendation impressions to their conversion and session rows.
- Exported every matching analytics event in bounded CSV batches instead of truncating exports at 100 rows.
- Limited refund subtraction to side-cart-attributed line items while retaining the full order-refund amount for audit metadata.
- Updated stale end-to-end expectations and strengthened slow administration and sold-individually test handling.

## 1.3.4 - 2026-07-17

- Matched the administration reward preview to the storefront reward card structure, spacing, colors, border, typography, progress fill, and milestone markers.
- Moved preview rewards into the same scrollable cart-body position used by the storefront.
- Matched Bar, Steps, and Compact reward designs in pending and completed states.

## 1.3.3 - 2026-07-17

- Made the View cart and Continue shopping visibility toggles authoritative, with translated default labels when overrides are blank.
- Grouped both optional navigation links together below the checkout action for a clearer drawer footer.
- Constrained footer recommendation regions so Above totals and Below checkout never overlap coupons, totals, checkout, notes, or navigation links.
- Added layout-boundary regression checks for all four recommendation positions.

## 1.3.2 - 2026-07-17

- Corrected multicurrency rewards so stored thresholds are converted exactly once while WooCommerce cart totals remain untouched.
- Rebuilt FunnelKit analytics migration as bounded, cursor-based batches that resume safely across REST requests and browser reloads.
- Removed unsafe implicit support claims for subscriptions, bundles, and composites; complex product types now require an explicit complete adapter.
- Connected WPML, Polylang, and TranslatePress product mapping to rewards, recommendations, and special add-ons.
- Corrected analytics UTC storage, site-local date filtering, display timestamps, and DST-safe daily grouping.
- Added real WordPress, WooCommerce, HPOS, and MySQL assertions to the CI integration matrix.
- Added regression coverage for currency hook boundaries, translated products, complex product opt-in, DST, and 201-row resumable migration.
- Cleared the accumulated PHP coding-standard gate violations across the plugin.

## 1.3.1 - 2026-07-17

- Restored Compact cards as a static three-column recommendation grid without a horizontal scrollbar.
- Added a separate Slider recommendation layout with accessible previous and next controls.
- Connected the recommendation Display limit to the live administration preview.
- Added an independent Continue shopping visibility setting, a working drawer-footer action, and an empty-cart shop link fallback.
- Advanced the owned settings and database schema idempotently to version 11.

## 1.3.0 - 2026-07-17

### Added

- Added four independently configurable recommendation positions: before cart
  items, after cart items, above sticky totals, and below checkout.
- Added an accessible visual selector for three recommendation layouts, giving
  stores twelve placement/layout combinations instead of FunnelKit's five
  coupled templates.
- Added field-level validation summaries that name every invalid setting and
  automatically open the relevant administration section.

### Changed

- FunnelKit styles 4 and 5 now migrate to their matching card layout below the
  checkout action; styles 1 to 3 migrate inside the scrollable cart body.
- Save settings remains actionable and explains invalid values instead of
  silently disabling the primary button or showing only a generic error.

## 1.2.16 - 2026-07-17

### Changed

- Made a lone final color control span the full settings grid so odd-numbered
  color groups finish flush with the right edge instead of leaving an empty
  half-column.

## 1.2.15 - 2026-07-17

### Added

- Added a clearly labelled Header / shortcode cart container-border width
  control from 0 to 4 pixels, including guidance that 0 removes the outline.

### Changed

- Isolated shortcode border width in the rendered CSS custom properties so
  Breakdance and theme button borders cannot restore an outline after it is
  disabled.

## 1.2.14 - 2026-07-17

### Added

- Added a professional WordPress color picker to every color setting in the
  Design, cart-trigger, and Special Add-on sections while retaining precise
  hexadecimal input.
- Added alpha-channel controls and an explicit “Make transparent” action for
  backgrounds, hover surfaces, and borders that can be visually removed.

### Changed

- Extended validated color storage to safe six- and eight-digit hexadecimal
  values so transparency persists through REST saves and renders identically
  in admin previews and on the storefront.

## 1.2.13 - 2026-07-17

### Added

- Added fully independent FunnelKit-style controls for the floating cart and
  the header/shortcode cart, including separate Lucide/custom icons, surfaces,
  hover states, icon colors, count badges, sizing, borders, and radii.
- Added optional item-count and cart-subtotal output for `[starfiniti_cart]`
  and the `starfiniti/cart-toggle` block.

### Changed

- Removed the forced visible “Cart” title from shortcode and block output;
  accessible labels remain available to screen readers.
- Rendered all four bundled Lucide cart icons server-side, so storefront icons
  no longer depend on JavaScript hydration.
- Migrated the earlier shared icon and color settings into both independent
  trigger configurations without discarding existing store choices.

### Fixed

- Made floating-button background changes and header/shortcode background
  changes affect only their intended trigger and live preview.

## 1.2.12 - 2026-07-17

### Added

- Added a dedicated Design section for independently styling the floating cart
  trigger and the `[starfiniti_cart]` shortcode/block trigger.
- Added configurable trigger backgrounds, hover colors, icon/text colors,
  count badge colors, floating size, shortcode border, and corner radius.

### Changed

- Made both cart-trigger previews use the selected Lucide or custom cart icon.
- Changed the default floating trigger to a white surface with a dark icon and
  red count badge, isolated from Breakdance and theme button styles.
- Hidden the View cart link and calculation note when their language fields are
  blank, and synchronized that behavior with the live preview.
- Isolated quantity, coupon, recommendation, and secondary action controls on
  white surfaces so theme and drawer background colors cannot bleed into them.
- Added stylesheet content hashes to asset versions so every deployment
  invalidates stale browser and LiteSpeed CSS caches, including reproducible packages.

## 1.2.11 - 2026-07-17

### Fixed

- Removed the dialog-level focus outline that appeared as a blue vertical line
  along the drawer edge while preserving focus indicators on interactive controls.
- Republished the sticky-footer grid fix under a fresh asset version so cached
  staging styles cannot retain the earlier oversized footer layout.

## 1.2.10 - 2026-07-17

### Fixed

- Anchored the coupon, totals, and checkout footer to the bottom of the drawer
  before and after cart notices appear.
- Assigned explicit drawer grid areas so an empty notices region can no longer
  shift the footer into the flexible content track.

## 1.2.8 - 2026-07-17

### Added

- Added a Design cart-icon picker with exactly four Lucide shopping icons.
- Added a WordPress media-library custom cart icon option.

### Changed

- Synchronized the selected icon across the live preview, floating button,
  shortcode/block/menu toggles, and empty-cart state.
- Added strict icon-setting validation, sanitization, and an idempotent schema
  migration.

## 1.2.7 - 2026-07-17

### Fixed

- Made the Special Add-on live preview visible while its settings section is
  open, with a clear disabled state before the feature is published.
- Synced the preview with the selected product, product/custom image, image
  size, price, heading, description, checkbox/toggle control, and preselection.
- Added guidance when an enabled add-on is missing its required product and
  decoded WooCommerce currency entities in administration product summaries.

## 1.2.6 - 2026-07-17

### Fixed

- Synced the administration reward preview with the active milestone settings,
  including configured messages, placeholder expansion, completion state,
  progress amount, progress design, and milestone count.
- A single enabled reward milestone now renders exactly one marker at its
  threshold instead of the previous three hard-coded markers.

## 1.2.5 - 2026-07-17

### Fixed

- Isolated the recommendation heading from Breakdance and theme heading
  typography so the complete storefront drawer uses the owned Starfiniti Cart
  type system.

## 1.2.4 - 2026-07-17

### Fixed

- Version frontend CSS independently from the JavaScript build hash so browsers,
  page builders, and caches receive CSS-only drawer releases immediately.

## 1.2.3 - 2026-07-17

### Changed

- Matched the real storefront drawer to the owned administration preview with
  the same compact header, product rows, quantity controls, coupon field,
  totals hierarchy, and footer spacing.
- Replaced the visible coupon label with an accessible label plus input
  placeholder to match the preview layout.

### Fixed

- Isolated drawer typography, links, buttons, inputs, and selects from theme and
  page-builder global styles, including Breakdance heading and button rules.
- Forced the checkout action to retain readable white text on the configured
  accent background.

## 1.2.2 - 2026-07-17

### Fixed

- Restyled the coupon apply action as a neutral secondary button in both the
  live drawer and administration preview. The store accent color remains
  reserved for checkout and other primary actions.

## 1.2.1 - 2026-07-17

### Fixed

- Kept the admin section navigation as a compact horizontal scroller on phone
  and tablet widths instead of stretching it into a tall sidebar.
- Made the admin layout respond to the actual WordPress content width, including
  narrow canvases beside the WordPress admin menu.
- Reset the preview panel's legacy grid placement in the one-column layout to
  prevent an unintended second column and document-level overflow.

## 1.2.0 - 2026-07-17

### Added

- Supplied Starfiniti brand icon in the owned administration header.
- Interactive, realistic live-cart preview with products, recommendations,
  quantity controls, totals, coupons, and empty-cart state.
- Visual QA evidence for Cart, Design, Analytics, and Tools at the reference
  1440 by 1000 pixel viewport.

### Changed

- Rebuilt the complete administration UI to match the supplied Starfiniti
  dashboard design: typography, header, tabs, cards, controls, spacing, color
  system, responsive layout, and preview panel.
- Restored the live preview on Analytics while retaining cart-focused metrics.
- Restructured FunnelKit migration preview counts into branded summary cards.

## 1.1.0 - 2026-07-17

### Added

- Anonymous side-cart event collection for cart opens/closes, successful and
  failed cart interactions, checkout clicks, and paid-order funnel attribution.
- Cart funnel metrics for non-empty sessions, interactions, checkout sessions,
  paid conversions, checkout rate, cart conversion rate, and abandonment.
- Daily cart-activity visualization and interaction-level reporting.

### Changed

- Analytics now prioritizes cart engagement and funnel performance; order and
  revenue reporting remains available as a secondary outcome section.
- Analytics uses the full administration content width without the live drawer
  preview and exposes only date controls in the primary toolbar.
- Owned database schema advanced idempotently to version 8.

## 1.0.0 - 2026-07-17

### Added

- Release-candidate package with installation guide, migration guide,
  compatibility matrix, known limitations, and security/accessibility review
  notes.
- Final validation for the WooCommerce-only side-cart scope: normal checkout
  pages and payment processing remain unchanged.

### Changed

- Plugin version advanced to `1.0.0`.
- Playground smoke validation now asserts schema version 7.

## 0.9.0-dev - 2026-07-17

### Added

- Guided FunnelKit Cart migration tool in the Tools section with preview,
  explicit `MIGRATE` confirmation, resumable state, and bounded audit log.
- Migration import for legacy `fkcart_settings`, `fk_cart`, and
  `fk_cart_products` into Starfiniti-owned settings and analytics storage while
  leaving all legacy data untouched.
- Idempotent legacy analytics import using stable event keys so repeated runs
  update owned rows without duplicating conversions or items.
- Cart-only compatibility manager for multicurrency reward thresholds,
  multilingual product-id mapping, subscription/bundle product-type support,
  and non-cacheable dynamic cart REST/AJAX responses.
- Unit coverage for migration preview, rerunnable imports, legacy data
  retention, schema version 7, and executable source isolation.

### Changed

- Settings/schema migration advanced idempotently to version 7.
- Plugin development version advanced to `0.9.0-dev`.

## 0.8.0-dev - 2026-07-17

### Added

- Owned analytics ledger tables for conversions and conversion items with
  indexed order, refund, date, product, type, and currency fields.
- Idempotent reconciliation for paid WooCommerce orders, delayed status
  changes, repeated hooks, recommendation impressions and acceptances, rewards,
  special add-ons, and refunds through WooCommerce CRUD/HPOS APIs.
- Authenticated `starfiniti-cart/v1/analytics/*` REST endpoints for overview,
  conversions, popular attributed items, daily performance, filter values, and
  CSV export.
- React analytics dashboard with date, type, currency, product, and coupon
  filters plus summary metrics, daily bars, item performance, conversion rows,
  and CSV download.
- CSV injection protection and browser coverage for paid-order/refund
  reconciliation, dashboard rendering, export content, and anonymous-route
  denial.

### Changed

- Settings/schema migration advanced idempotently to version 6.
- Plugin development version advanced to `0.8.0-dev`.

## 0.7.0-dev - 2026-07-16

### Added

- Cart-only Special Add-on offers with checkbox or toggle selection, optional
  preselection, simple and variable products, product or custom imagery, and
  owned color and image-size controls.
- Server-authoritative add-on eligibility, duplicate prevention, fixed
  quantity, shopper opt-out persistence, orphan cleanup, and hidden WooCommerce
  order-item provenance.
- Owned React administration controls and live preview for all add-on content,
  product, image, selection, and design settings.
- Version-guarded adapters for the official WooCommerce PayPal Payments
  mini-cart renderer hook and the official WooCommerce Stripe classic-cart
  express callback.
- Browser coverage for simple, preselected, and variable add-ons, order
  metadata, anonymous-route denial, and the normal-checkout fallback when no
  supported gateway control is available.
- Unit coverage proving that gateway-owned callbacks move only into the drawer
  on their supported contexts.

### Changed

- Settings schema migrated idempotently to version 5.
- Shared product serialization now serves recommendations and add-ons.
- Plugin development version advanced to `0.7.0-dev`.

## 0.6.0-dev - 2026-07-16

### Added

- Idempotent threshold rewards for WooCommerce coupons, variable/simple free
  gifts, and an owned zero-cost shipping rate.
- Multiple ordered milestones with subtotal/total modes, translated message
  placeholders, completion messaging, and bar/steps/compact progress designs.
- Reversible coupon, gift, and shipping reconciliation across quantities,
  coupons, shipping packages, customer sessions, and extension-filtered reward
  amounts without a geolocation dependency.
- Optional shopper gift removal with suppression until eligibility is crossed
  again; non-removable gifts have fixed quantity and no removal control.
- Hidden gift-line and achieved-milestone order metadata through WooCommerce
  checkout creation hooks and CRUD objects.
- Owned React milestone editor with authenticated product/coupon search and
  variable-gift selection support.
- Browser coverage for forward/reverse thresholds, total-mode coupon feedback,
  variable gifts, shipping rates, order metadata, all progress designs, and
  gift removal/re-entry.

### Changed

- Settings schema migrated idempotently to version 4.
- Plugin development version advanced to `0.6.0-dev`.

## 0.5.0-dev - 2026-07-16

### Added

- Server-authoritative recommendations from WooCommerce-native upsell and
  cross-sell relationships, owned defaults, exclusions, display limits, and
  relevance/name/price ordering.
- Three responsive owned recommendation layouts matching the audited one-card,
  two-column, and stacked upstream presentation modes.
- Drawer add flows for simple products and validated variable-product
  attributes without leaving the cart.
- Session-de-duplicated impression events and accepted recommendation cart,
  order-item, paid revenue, and refund attribution through WooCommerce CRUD.
- Authenticated relationship endpoints and a centralized React product
  relationship manager using native WooCommerce product setters.
- Browser coverage for all three layouts, upsell/cross-sell/both modes,
  ordering, exclusions, default fallback behavior, variation adds, REST
  permissions, and idempotent order/refund attribution.

### Changed

- Settings schema migrated idempotently to version 3.
- Plugin development version advanced to `0.5.0-dev`.

## 0.4.0-dev - 2026-07-16

### Added

- Owned TypeScript/React administration application under WooCommerce with
  Cart, Design, Upsells, Rewards, Special Add-on, Analytics, and Tools sections.
- Strict versioned `sfcart_settings` schema with bounded values, allowlisted
  fields, translated fallbacks, and derived media URLs.
- Authenticated `starfiniti-cart/v1` REST endpoints for settings and
  WooCommerce-native product and coupon search.
- Live drawer preview, responsive administration layout, WordPress media
  selection, and product/coupon selection controls.
- Storefront application of drawer position, width, behavior, visibility,
  design tokens, media, and language overrides.
- Browser coverage for REST permissions and validation, all administration
  sections, search, media, save/reload, preview, and storefront synchronization.

### Changed

- Settings schema migrated idempotently to version 2.
- Shared Playground tests now use one worker because they intentionally share a
  single WooCommerce site and cart session.

## 0.3.0-dev - 2026-07-16

### Added

- Accessible responsive side-cart drawer with focus trap, Escape handling,
  overlay, focus restoration, and reduced-motion support.
- Nonce-protected WooCommerce AJAX state, quantity, removal, and coupon actions
  that preserve guest sessions and cart-item metadata.
- Simple, variable, stock-managed, backordered, and sold-individually product
  rendering with sale savings and WooCommerce totals.
- Floating, shortcode, custom-menu-link, and `starfiniti/cart-toggle` block
  entry points.
- Classic and native WooCommerce Blocks add-to-cart listeners and namespaced
  Store API extension data without a `wc-cart-fragments` dependency.
- Browser coverage for classic and Blocks flows, desktop/mobile layouts,
  mouse/keyboard interaction, coupons, metadata, stock caps, and product types.

## 0.2.0-dev - 2026-07-16

### Added

- WooCommerce-only activation and runtime requirement validation.
- FunnelKit Cart activation conflict guard that never changes another plugin's
  state.
- Versioned, idempotent plugin settings migrations and HPOS declaration.
- WooCommerce-backed structured logging and caught-initialization error notices.
- Retain-by-default uninstall policy with an explicit delete-data setting.
- Multisite-aware activation, conflict checks, migrations, and uninstall cleanup.

## 0.1.0-dev - 2026-07-16

### Added

- Independent plugin repository and WooCommerce-only bootstrap.
- PHP, TypeScript, React, WordPress Scripts, PHPUnit, PHPStan, PHPCS, Playwright,
  WordPress Playground, and GitHub Actions foundations.
- Reproducible versioned ZIP packaging with SHA-256 output.
- GPL license, upstream source fingerprints, and attribution policy.
