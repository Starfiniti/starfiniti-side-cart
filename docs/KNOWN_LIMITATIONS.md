# Known limitations

Version: 1.4.0
Date: 2026-07-18

- This release is intended for Starfiniti-controlled staging/internal use before
  broad commercial distribution.
- The migration maps known side-cart settings conservatively. Unknown legacy
  FunnelKit fields are ignored rather than stored as arbitrary plugin data.
- Open shopper carts are not migrated; after switching plugins, active visitors
  start with a clean drawer.
- Express payment buttons are rendered only through supported official gateway
  hooks. If a gateway does not expose a compatible mini-cart/classic-cart
  renderer, the normal checkout button remains the fallback.
- Cart fragments are intentionally not used. Integrations should listen for
  `sfcart:updated` or use the Store API extension data.
- An abandoned cart is a non-empty anonymous cart session that has no paid
  order attributed within the selected reporting period. Delayed payments
  outside that period can therefore change the result in a later report.
- Subscriptions, bundles, composites, and other products that require custom
  configuration fields are hidden from drawer offers unless a complete
  integration explicitly opts them in. Each third-party adapter still needs
  staging verification with custom pricing, multicurrency, multilingual, and
  aggressive caching stacks.
- The plugin has no commercial license system or proprietary update service.
  Native updates depend on availability of the public GitHub Releases service.
