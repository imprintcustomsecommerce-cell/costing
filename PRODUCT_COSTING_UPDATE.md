# Product Costing Update

The Create/Edit Product screen now follows this costing order:

1. **Materials** — select the actual material records and quantities. Fabric, Accessories, and Ribbings are shown as priority groups. Material prices still come from the Material Master so one supplier-price update can flow to every product using that material.
2. **Printing** — choose the product's print method and enter its printing cost per piece.
3. **Labor** — enter Production Cost and Sewing Cost per piece.
4. **Packaging** — enter Plastic, Box, and Sticker cost per piece; unused items can stay at zero.

The live summary shows Materials + Printing + Labor + Packaging = Base Product Cost, then applies the configured selling margin for the preview.

## Deploy

Back up the database, then run:

```bash
php artisan migrate
php artisan optimize:clear
```

If you are installing this source-only archive on a fresh machine, also run the normal dependency steps (`composer install` and `npm install` / `npm run build`).

## Existing products

The new migration snapshots the old per-piece production-stage cost of existing products into the new product-level fields so current prices do not suddenly change. The per-job setup charge is gone: a quotation is the sum of its lines, since what a piece costs to make is now held on the product. Quotations raised while that charge existed keep the figure they were given and still show it.

Products created or resaved from the updated Product form use the new product-level breakdown. The older per-piece stage system remains only as a fallback for legacy integrations that create products without using the Product form.
