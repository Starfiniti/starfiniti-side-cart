# Starfiniti Cart for WooCommerce

This directory is an independent WordPress plugin repository.

## Non-negotiable boundaries

- The plugin implements a side cart only.
- Do not modify checkout pages or implement checkout, funnels, order bumps, payment processing, licensing, telemetry, or automatic updates.
- WooCommerce is the only required runtime dependency.
- Public PHP identifiers use `sfcart_`; PHP classes live under `Starfiniti\Cart`; CSS classes use `.sfcart-*`; the text domain is `starfiniti-cart`.
- Preserve GPL attribution in `LICENSE`, `NOTICE.md`, and any substantially derived source headers.
- Never commit the supplied upstream archives, license credentials, secrets, generated dependencies, or local WordPress data.
- Complete one approved implementation batch at a time and stop at its gate.

## Supported baseline

- PHP 8.1+
- WordPress 6.6+
- WooCommerce 9.0+

## Verification

- JavaScript: `npm run check`
- PHP: `composer check`
- Playground smoke test: `npm run test:e2e`
- Release artifact: `npm run package`
