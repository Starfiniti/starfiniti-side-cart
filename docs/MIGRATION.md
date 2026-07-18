# FunnelKit migration guide

Version: 1.4.1
Date: 2026-07-17

## What is migrated

- Side-cart settings from `fkcart_settings`, normalized into `sfcart_settings`.
- Historical cart analytics from `fk_cart` and `fk_cart_products`, normalized
  into `sfcart_conversions` and `sfcart_conversion_items`.

## What is not migrated

- Transient WooCommerce sessions or currently open shopper carts.
- Checkout builder settings.
- Funnel settings.
- Order bumps or post-purchase upsells.
- Payment, order-processing, licensing, telemetry, updater, or Funnel Builder
  shared-service data.
- Runtime `fkcart_*` aliases.

## Safety model

The migration is copy-only. Legacy FunnelKit tables and options are left in
place. Starfiniti-owned analytics rows use stable event keys, so rerunning the
migration updates owned records instead of duplicating imported conversions.
Large analytics datasets are copied in bounded 200-row requests. The persisted
cursor, counters, and step state let the Tools screen continue an interrupted
migration without reloading all legacy tables into memory.

Native WooCommerce `_upsell_ids` and `_crosssell_ids` relationships remain
native product metadata and are not duplicated.

## Recommended staging checklist

1. Back up the staging database.
2. Deactivate FunnelKit Cart.
3. Activate Starfiniti Cart 1.4.1.
4. Run the migration preview.
5. Confirm and run migration.
6. Review the audit log.
7. Verify:
   - drawer position, width, colors, labels, coupons, and visible totals;
   - recommendation defaults and native product relationships;
   - free-shipping, coupon, and gift milestones;
   - special add-on product and selection behavior;
   - analytics overview and CSV export;
   - normal WooCommerce checkout remains unchanged.

## Rollback

Deactivate Starfiniti Cart and reactivate FunnelKit Cart. Because the migration
does not delete legacy data, FunnelKit can resume from its original storage.
