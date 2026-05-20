# Changelog

## 1.4.4 - 2026-05-20

- Fixed GLS checkout JavaScript so parcel locker / parcel shop dialog opening no longer breaks on checkout refresh because of missing `#gls-map` lookup.
- Rebound GLS map change listeners after WooCommerce checkout refresh and preserved selected pickup point unless the shipping method truly changes.
- Normalized WooCommerce shipping method IDs before GLS session checks so zone-based GLS methods like `gls_shipping_method_parcel_locker_zones:12` correctly hide COD when the selected point does not accept cash.

## 1.4.3 - 2026-05-20

- AR Design standalone release skeleton: added `VERSION`, release docs, build script, version consistency checks, `.distignore`, and GitHub release workflows.
- Checkout fix: cash on delivery is now hidden when the selected GLS parcel locker / parcel shop does not support cash payments.
- Checkout UX improvement: selecting GLS pickup point immediately refreshes checkout so available payment methods update without extra user action.
- Plugin metadata updated for AR Design maintenance and GitHub updater support.
