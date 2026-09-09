# Imprint Customs Costing System

Internal Laravel application for apparel-printing cost calculation and quotations: a server-side pricing engine for DTF, embroidery, silkscreen, sublimation, stickers and caps, with admin-controlled rates, artwork versioning, costing snapshots and quotation PDFs.

## Quick start on Windows

1. Install PHP 8.2+, Composer, and the required PHP extensions (`pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`).
2. Copy `.env.example` to `.env` and configure the database.
3. Run `composer install`.
4. Run `php artisan key:generate`.
5. Run `php artisan migrate --seed`.
6. Double-click `start.bat`. The launcher opens `http://127.0.0.1:8002/login`.

Development Super Admin:

- Email: `admin@imprintcustoms.ph`
- Password: `imprint123`

Change this password before any real deployment.

## Production installation

Configure `.env` without committing it:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://costing.example.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=costing
DB_USERNAME=costing_user
DB_PASSWORD=use-a-strong-secret
FILESYSTEM_DISK=local
```

Then run:

```text
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
npm install
npm run build
```

Point the web server document root at `public/`. Ensure `storage/` and `bootstrap/cache/` are writable. Use HTTPS, set `APP_DEBUG=false`, and replace the seeded password immediately.

## Roles

| Role | Can | Cannot |
| --- | --- | --- |
| `SUPER ADMIN` | Everything, including users and system settings | — |
| `ADMIN` | All pricing, products, materials, costs and profit | Manage users or system settings |
| `SALES / STAFF` | Create customers and quotations, upload artwork, generate PDFs | See costs, change pricing |
| `PRODUCTION` | View and sign off artwork | See any money at all |


## Seeders

Two seeders exist and they serve different purposes:

- `DatabaseSeeder` (run by `--seed`) creates **structure only**: roles, permissions, the Super Admin, customer types, print methods, print locations, product categories, sample products, the initial pricing version, and company settings. It deliberately seeds **no prices**, so a fresh install refuses to calculate until real rates are entered.
- `DevelopmentPricingSeeder` fills in a complete set of **placeholder rates** so the engine can be exercised end to end on a development machine:

  ```text
  php artisan db:seed --class=DevelopmentPricingSeeder
  ```

  Every peso value in it is invented for testing and is **not** real company pricing. Never run it on a production install.

## Pricing configuration order

The system deliberately refuses to calculate when required configuration is missing. Configure:

1. Materials and effective costs.
2. Products, sizes, materials, and permitted methods.
3. A method rate (DTF, embroidery, silkscreen, sublimation, sticker, or cap).
   - Silkscreen offers two print-cost models, chosen per rate row under Admin → Pricing. **Quantity × colour matrix** prices the first colour higher than each additional colour, with both falling as the run lengthens (the trade-standard model). **Flat ink + labour per colour** charges every colour the same; only that model uses the ink and labour fields. Under the matrix model a quantity outside every band is a configuration error, not a free print.
   - Embroidery supports an **included colours** allowance: only colours beyond it are charged at the per-colour rate. Leave it at `0` to charge from the first colour.
   - DTF additionally supports optional **area size multiplier** bands (e.g. `0–100 cm² = 1.00x`, `601–1000 cm² = 1.30x`) and a separate pressing rate for the second and subsequent locations on the same garment. Every DTF rate explicitly references its DTF film/media material; the selected garment material is never used to choose a DTF media rate. With no bands configured the multiplier is 1.00x.
4. Product BOM/recipe for every physical product. A selectable primary component must use an Admin-managed material group; only active materials in that group can replace the default recipe material.
5. At least one applicable margin or markup rule.
6. Optional tiers, labor, effects, add-ons, location rates, size surcharges, wastage, overhead, minimum charges, and rounding.

Quotation requests contain specifications only. Monetary columns are guarded and recalculated by `PricingEngine` on the server. Calculations retain rate snapshots for historical explanation.

Method rate tables own their embedded operations (for example DTF pressing, silkscreen printing labor, or embroidery machine work). A central labor/location rule marked for an already-owned operation is skipped and recorded as a duplicate in the pricing snapshot. Use `supplemental_location` only for a genuinely separate location-handling cost.

The former `special_effects.selling_price` and `cap_rates.blank_cost_per_piece` inputs are no longer live pricing fields. Incremental migrations preserve any old values under `legacy_*` columns, while Special Effects use the generic internal charge engine and cap blanks always come from the Cap Type’s Material Master record.

## Backups

Back up all three areas together:

- MySQL: `mysqldump --single-transaction --routines --triggers costing > costing-YYYY-MM-DD.sql`
- Artwork: `storage/app/private/artwork/`
- Configuration: securely back up `.env`; never commit it.

Test restoration regularly on a separate server:

```text
mysql costing_restore < costing-YYYY-MM-DD.sql
php artisan migrate --force
php artisan optimize
```

For SQLite development, copy `database/database.sqlite` only while the application is stopped. Production automation should encrypt backups, store them off-host, and enforce a retention policy.

## Testing

Run `php artisan test`. The suite covers authentication, inactive users, role boundaries, protected pricing administration, immutable material history, discount separation of duties, server-only pricing, and snapshots.

## Storage and future S3 migration

Artwork uses the private `artwork` filesystem disk. To migrate to S3-compatible storage later, change that disk's configuration while retaining each artwork record's `disk` and `stored_path`.
