# Starfiniti Cart for WooCommerce

**A free, production-focused side cart built for real WooCommerce stores.**

Starfiniti Cart was developed for Starfiniti's own professional WooCommerce
projects. Our team has built more than **200 WooCommerce stores** and actively
maintains more than **80 stores**, so this plugin was designed to solve the
day-to-day requirements we encounter in real client environments: reliable
cart updates, flexible presentation, measurable conversion tools, clean
WooCommerce integration, and maintainable code.

The plugin is free and open source under the GPL, and it will always remain
free to use. We build and maintain it primarily for our own stores, but publish
it so the wider WooCommerce community can benefit as well.

## Highlights

- Fast AJAX side cart without a dependency on WooCommerce cart fragments.
- Responsive, accessible drawer for classic and block-based storefronts.
- Independent floating-cart, shortcode, block, and menu triggers.
- Live quantity changes, removal, coupons, totals, stock handling, and notices.
- Flexible colors, typography-safe styling, custom cart icons, and live preview.
- Native upsells and cross-sells with four positions and four layouts.
- Free-shipping, coupon, and free-gift reward milestones.
- Optional special add-on offers and gateway-owned express-payment controls.
- Cart-focused analytics, attributed offer revenue, refunds, and CSV exports.
- HPOS, multilingual, multicurrency, Breakdance, caching, and LiteSpeed support.
- Native WordPress updates from public GitHub Releases with SHA-256 package verification.
- No licensing server, telemetry, payment processing, account, or update token.

## Support the project

Starfiniti Cart does not have a paid edition or locked features. If it saves you
development time or helps your store, you can support continued testing,
compatibility work, and maintenance with an optional
[PayPal donation](https://www.paypal.com/donate?business=dejan.kletecki%40gmail.com&no_recurring=0&item_name=Support+Starfiniti+Cart&currency_code=EUR).

Donations are appreciated but never required. Every feature remains available
to everyone.

## Administration

Open **WooCommerce → Starfiniti Cart** to configure the drawer. The owned
TypeScript/React application includes Cart, Design, Upsells, Rewards, Special
Add-on, Analytics, and Tools sections. A responsive live preview is available
on every configuration screen, including the cart-focused Analytics section.

- Cart behavior, drawer position and width, visible totals and links.
- Drawer colors, radius, overlay opacity, four Lucide cart icons, a custom icon,
  and separate styling for the floating and shortcode cart triggers.
- Store-specific language overrides that fall back to normal WordPress
  translations when blank. View cart and the calculation note are hidden when
  their fields are blank.
- Authenticated WooCommerce product and coupon search.
- Explicit retain/delete data control in Tools.
- Recommendation layout, four independent cart positions, source, ordering,
  defaults, exclusions, and a central
  native product relationship manager.
- Multiple coupon, free-gift, and free-shipping milestones with subtotal/total
  modes, three progress designs, translated messages, and gift-removal policy.
- Special Add-on product, checkbox/toggle selection, optional preselection,
  product/custom image, content, sizing, and color controls.
- Cart-focused analytics for opens, interactions, checkout clicks, abandonment,
  conversion, offer performance, attributed revenue, and CSV export.
- Optional legacy side-cart migration with preview, explicit confirmation,
  resumable progress, and an audit log.

## Cart experience

- Accessible responsive drawer with overlay, Escape handling, focus trapping,
  and focus restoration.
- Quantity updates, stock limits, item removal, coupons, totals, shipping/tax
  text, sale savings, notices, and an empty state without a page reload.
- Simple, variable, backordered, and sold-individually product support while
  preserving WooCommerce cart-item metadata.
- Fixed floating toggle, `[starfiniti_cart]` shortcode, and
  `starfiniti/cart-toggle` dynamic block.
- Menu integration by adding a WordPress Custom Link whose URL is
  `#starfiniti-cart`.
- Classic `added_to_cart` and native `wc-blocks_added_to_cart` support without
  depending on `wc-cart-fragments`.
- Native WooCommerce upsell and cross-sell resolution, optional defaults,
  exclusions, four deterministic ordering modes, three layouts, and four
  independently configurable positions (twelve presentation combinations).
- Simple and variable recommendation adds inside the drawer, with server-side
  candidate and variation validation.
- De-duplicated impression events plus accepted-item, order revenue, and refund
  attribution through WooCommerce session and CRUD metadata.
- Idempotent coupon, gift, and free-shipping rewards that reverse when
  eligibility is lost, support variable gifts, and keep reward provenance in
  hidden order metadata.
- Accessible bar, steps, and compact progress views using server-evaluated
  thresholds and translated message placeholders.
- One optional simple or variable Special Add-on with shopper opt-out,
  fixed-quantity ownership, duplicate prevention, and hidden order metadata.
- Official PayPal Payments mini-cart controls can render through the gateway's
  public renderer hook. Official Stripe express controls are relocated only
  from their native classic-cart callback on supported classic cart pages.
- Express controls remain gateway-owned; unsupported or unavailable controls
  are hidden and the normal WooCommerce checkout button always remains.

The checkout button links directly to the normal WooCommerce checkout. The
drawer and its assets do not render on checkout requests, and this plugin does
not change checkout or payment processing.

Starfiniti Cart stores no payment credentials and implements no express-payment
UI, order creation, payment confirmation, or gateway JavaScript. The official
gateway plugins remain independently installed and authoritative for all such
behavior.

## Integration interfaces

The frontend dispatches these document events:

- `sfcart:opened`
- `sfcart:closed`
- `sfcart:updated`, with `cartHash` and `itemCount` in `event.detail`

WooCommerce Store API cart and cart-item responses expose read-only extension
data under `extensions["starfiniti-cart"]`. Theme and plugin integrations should
use the `sfcart_*` PHP hooks rather than internal classes.

Administration requests use the versioned `starfiniti-cart/v1` REST namespace.
Settings writes, relationship management, and product/coupon searches require
`manage_woocommerce`, REST cookie authentication, and a valid WordPress REST
nonce. Recommendation integrations can consume
`sfcart_recommendation_impression`, `sfcart_recommendation_accepted`,
`sfcart_recommendation_revenue_recorded`, and
`sfcart_recommendation_refund_recorded`. The durable analytics ledger stores
anonymous cart-session, paid-order, refund, recommendation, reward, and
special-add-on events in Starfiniti-owned tables and is read through
authenticated REST endpoints. Cart sessions use a random hashed identifier and
store no customer name, email address, IP address, or payment data.

Reward amount adapters may use `sfcart_reward_amount` to convert the evaluated
subtotal or total while preserving the owned milestone engine. The engine
stores only transient ownership and suppression data in the WooCommerce
customer session and uses native shipping packages and order CRUD metadata.

## Runtime requirements

- PHP 8.1 or newer
- WordPress 6.6 or newer
- WooCommerce 9.0 or newer

WooCommerce is the only required runtime plugin. This project does not load or
call code from other side-cart plugins and does not change checkout pages or
payment processing. Activation is refused while a known conflicting side-cart
plugin is active, and no other plugin is ever deactivated automatically.

## Data retention and uninstall

Plugin data is retained on uninstall by default. Destructive cleanup must be
explicitly enabled in the Tools section. Deactivation never deletes data, and
the uninstall handler deletes only Starfiniti-owned options and analytics
tables on sites that opted in.

## Optional legacy migration

Stores moving from a supported legacy side-cart installation can use the guided
migration in **WooCommerce → Starfiniti Cart → Tools**. It provides a preview,
explicit confirmation, resumable progress, and an audit log. The process is
repeatable, never deletes source data, and does not copy active customer
sessions. See `docs/MIGRATION.md` for the supported source and rollback steps.

Native WooCommerce upsell and cross-sell relationships remain native product
metadata and are not duplicated into plugin-specific relationship storage.

## Development setup

Install JavaScript and PHP development dependencies:

```bash
npm ci
composer install
```

Run the local checks:

```bash
npm run check
composer check
```

Run a browser smoke test in WordPress Playground:

```bash
npx playwright install chromium
npm run test:e2e
```

The Playground blueprint installs and activates WooCommerce, creates products
and coupons, verifies the lifecycle, and runs classic/Blocks, desktop/mobile,
mouse/keyboard, cart, recommendation, variation, attribution, reward
thresholds, reversible coupons/gifts/shipping, order metadata, administration,
REST authorization, search, preview, persistence, storefront settings, and
simple/preselected/variable Special Add-on scenarios. Analytics tests reconcile
a paid attributed order, refund rows, dashboard data, CSV export, and anonymous
authorization failures. CI additionally runs an activation smoke test against a
real MySQL service.

## Build and package

```bash
npm run package
```

This creates a versioned ZIP and matching SHA-256 file in `dist/`. The packaging
script uses a fixed timestamp, stable file ordering, normalized file modes, and
an explicit source allowlist so identical committed inputs produce an identical
archive. Compiled JavaScript/CSS and their TypeScript/SCSS source are both
included in the release.

## Updates

Stable releases update through the normal WordPress Plugins screen. Update
metadata and packages come directly from this public GitHub repository; no
Starfiniti account, license key, GitHub token, or paid updater library is
required. WordPress's normal per-plugin auto-update toggle remains authoritative.

Every release publishes the exact versioned ZIP and a matching SHA-256 file.
The plugin validates owned GitHub URLs, rejects drafts and prereleases, and
verifies the downloaded package before WordPress installs it. Update checks send
only the plugin name and installed version in the HTTP user agent—not the store
URL, customer data, cart data, or credentials.

## Release documentation

- `docs/INSTALLATION.md`
- `docs/MIGRATION.md`
- `docs/COMPATIBILITY.md`
- `docs/KNOWN_LIMITATIONS.md`
- `docs/SECURITY_ACCESSIBILITY_REVIEW.md`

## Repository policy

The supplied GPL archives are audit inputs, not repository contents. See
`NOTICE.md` and `docs/UPSTREAM.md`. Never commit upstream archives, license
credentials, secrets, `vendor/`, `node_modules/`, local WordPress data, or build
reports.
