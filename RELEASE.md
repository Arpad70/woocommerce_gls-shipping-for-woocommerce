# Release

## 1.4.4

Follow-up release pro checkout opravu GLS výběru pickup pointu a spolehlivého schování dobírky.

### Změny

- opraven frontend GLS checkout flow po `updated_checkout`,
- odstraněn pád na neexistujícím `#gls-map`,
- výběr pickup pointu se po refreshi checkoutu zachová, dokud se opravdu nezmění dopravní metoda,
- zónové GLS shipping method ID se normalizují bez `:instance_id`, takže COD filtr pracuje korektně i pro shipping zones.

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
