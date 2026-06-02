# Changelog

Published release history for this Webasyst product.

## 1.1.0 — 2026-06-02

### Added

- Bulk stock management: set, increase, decrease, or make stock infinite; a warehouse can be selected when warehouse stock accounting is enabled.
- Basic product feature editing: choose a feature, set an existing value, or clear the value.
- Bulk category operations: add a product to a category, remove the category link, or replace the main category.
- Selection of all products matching the current filter with server-side reselection and `operation_limit` enforcement.

### Improved

- The operation library and confirmation modal now reflect the new stock, feature, and category modes.
- Server-side validation and the operation log were extended for the `stock`, `features`, and `categories` actions.
- Russian and English interface strings were refreshed together with the release publication materials for `1.1.0`.

### Compatibility

- The plugin still works only in the Shop-Script backend and does not modify the storefront, cart, orders, or theme.
- Basic feature editing is limited to common product features of safe types: string, text, number, and a single existing selectable value.

### Notes

- This release does not include SKU features, multiple values, colors, ranges, dimensions, or creation of new feature values.
- Product images, videos, cross-selling, similar products, and product pages are also out of scope for this version.
