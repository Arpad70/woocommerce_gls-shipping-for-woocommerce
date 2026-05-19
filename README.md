# AR Design GLS Shipping for WooCommerce

AR Design maintained fork pluginu `gls-shipping-for-woocommerce` se standalone release kostrou, GitHub updater metadaty a checkout opravami pro pickup pointy.

## Co plugin umí

- GLS doprava na adresu, Parcel Shop a Parcel Locker pro WooCommerce
- správu GLS účtů, pickupů, štítků a trackingu
- checkout map selection pro GLS pickup pointy
- okamžitou obnovu checkoutu po výběru GLS pickup pointu
- skrytí dobírky, pokud vybraný GLS pickup point nepodporuje hotovostní platbu
- build a GitHub release workflow pro standalone vydávání pluginu

## Požadavky

- WordPress 5.9+
- WooCommerce 5.6+
- PHP 7.1+

## Instalace

1. Nahrajte adresář `gls-shipping-for-woocommerce` do `wp-content/plugins`.
2. Aktivujte plugin `AR Design GLS Shipping for WooCommerce`.
3. V administraci WooCommerce otevřete nastavení dopravy GLS a doplňte API údaje.
4. Otestujte checkout pro Parcel Locker / Parcel Shop včetně dobírky.

## Release

```bash
php scripts/verify-version-consistency.php
scripts/build-plugin.sh
```

GitHub Actions workflow `.github/workflows/release.yml` vytváří release asset `gls-shipping-for-woocommerce.zip`.
