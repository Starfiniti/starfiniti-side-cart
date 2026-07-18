# Installation guide

Version: 1.4.0
Date: 2026-07-18

## Requirements

- PHP 8.1 or newer.
- WordPress 6.6 or newer.
- WooCommerce 9.0 or newer.
- A staging site for first installation when replacing another side-cart
  plugin.

WooCommerce is the only required runtime dependency. Starfiniti Cart does not
require FunnelKit, Funnel Builder, payment gateways, page builders, or licensing
services.

## Fresh installation

1. In WordPress admin, go to **Plugins → Add Plugin → Upload Plugin**.
2. Upload `starfiniti-cart-1.4.0.zip`.
3. Activate **Starfiniti Cart for WooCommerce**.
4. Open **WooCommerce → Starfiniti Cart**.
5. Review Cart, Design, Upsells, Rewards, Special Add-on, Analytics, and Tools.
6. Add a test product to the cart from a product archive and a single product
   page.
7. Confirm the checkout button opens the normal WooCommerce checkout.

## Updates

After version 1.4.0 is installed, stable releases appear in the normal
WordPress Plugins screen. WordPress can update the plugin manually or through
its standard per-plugin auto-update toggle. Updates come directly from the
public Starfiniti GitHub repository and require no account, license key, or
token. The downloaded ZIP must match the release's SHA-256 digest or WordPress
stops the installation.

## Replacing FunnelKit Cart

1. Back up the database.
2. Deactivate FunnelKit Cart. Starfiniti Cart refuses activation while
   FunnelKit Cart is active and never deactivates another plugin automatically.
3. Install and activate Starfiniti Cart.
4. Open **WooCommerce → Starfiniti Cart → Tools**.
5. Preview the FunnelKit migration.
6. Confirm the migration by checking the acknowledgement and typing `MIGRATE`.
7. Review the audit log and migrated settings.
8. Test add/update/remove/coupon, upsell, reward, add-on, and checkout-link
   flows.

Rollback consists of deactivating Starfiniti Cart and reactivating FunnelKit
Cart. The migration does not delete FunnelKit data.

## Uninstall

Plugin data is retained by default. To delete Starfiniti-owned data during
uninstall, enable **Delete Starfiniti Cart data when the plugin is uninstalled**
in Tools before uninstalling. Deactivation never deletes data.
