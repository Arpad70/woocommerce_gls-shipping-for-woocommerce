# Release

## 1.4.3

První AR Design standalone release pluginu `gls-shipping-for-woocommerce`.

### Změny

- převzetí pluginu do AR Design release kostry,
- přidán `VERSION`, `CHANGELOG.md`, build script a release workflow,
- zavedena kontrola verzí před buildem,
- doplněno skrytí dobírky podle capability vybraného GLS pickup pointu,
- po výběru GLS pickup pointu se checkout okamžitě obnoví.

### Kontrola před vydáním

- `php scripts/verify-version-consistency.php`
- `find . -path './build' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l`
- `scripts/build-plugin.sh`

### GitHub release

Workflow `.github/workflows/release.yml` publikuje release asset `gls-shipping-for-woocommerce.zip`.
