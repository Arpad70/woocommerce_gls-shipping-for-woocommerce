# Release Checklist

- [ ] Upravit `VERSION`.
- [ ] Upravit header `Version` v `gls-shipping-for-woocommerce.php`.
- [ ] Upravit konstantu `GLS_SHIPPING_VERSION`.
- [ ] Upravit interní property `$version`.
- [ ] Upravit `Stable tag` v `README.txt`.
- [ ] Doplnit `CHANGELOG.md`.
- [ ] Spustit `php scripts/verify-version-consistency.php`.
- [ ] Spustit PHP lint všech souborů.
- [ ] Spustit `scripts/build-plugin.sh`.
- [ ] Commitnout změny, pushnout branch a zkontrolovat GitHub release workflow.
