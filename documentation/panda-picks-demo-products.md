# Panda Picks: temporary Checkpoint 1 products

These are temporary screenshot/test records, not real stock. The original store was resolved by both name `Panda Picks` and slug `panda-picks`: store ID 1, seller user ID 8, seller profile ID 1. The other same-name store (`demo-shop-1`) was not changed.

| ID | Product | PHP price | Stock | Existing category |
| --- | --- | --- | --- | --- |
| 24 | Wireless Bluetooth Earbuds | 599.00 | 25 | Audio (4) |
| 25 | Portable Mini Fan | 349.00 | 30 | Home & Living (15) |
| 26 | Insulated Tumbler | 449.00 | 20 | Kitchen (16) |
| 27 | USB Rechargeable Desk Lamp | 399.00 | 18 | Decor (17) |

## Creation and reruns

Run only this seeder, from the SHOPPICK project:

```sh
php artisan db:seed --class=PandaPicksDemoProductsSeeder
```

The seeder resolves the existing store and categories from the database. It refuses missing/inactive stores, missing categories/images and conflicting demo slugs. Stable `itel304-demo-*` slugs, batch-prefixed SKUs and the existing specifications JSON identify this batch as `ITEL304-CHECKPOINT1`. Descriptions explicitly mark the records as temporary demo data. Reruns skip existing records (including soft-deleted ones); they do not reset stock, modify products, or restore archived demo products. No schema changes or DatabaseSeeder changes were made.

Products use the existing store relationship, images relation, public storage disk, image-created event and moderation service. All four images were approved by the configured LOCAL development image-validation provider. This is not a claim of AI image-content screening. Products are published and marketplace-visible with zero fabricated ratings or sales.

## Local illustrative assets

- Earbuds: existing `storage/app/public/products/sonic-wireless-earbuds-pro-catalog.png`.
- Fan: new `storage/app/public/products/itel304-portable-mini-fan.png`, a simple local illustration.
- Tumbler: existing `storage/app/public/products/ecobrew-stainless-bottle-1l-catalog.png` (illustrative bottle placeholder).
- Lamp: existing `storage/app/public/products/sparkled-desk-lamp-catalog.png`.

Existing assets are reused in place, not overwritten or copied from external websites. Do not delete shared images when cleaning up these records. The new fan asset is stored on the normal public disk; preserve it when transferring the database to another machine because uploaded storage may be ignored by Git.

## Verification performed

- Four records exist under the correct store and existing seller relationship; all four are approved, published and returned by `Product::active()`.
- Seeder executed twice: second run skipped all four; count remained four.
- Existing CartService successfully added the earbuds to an existing buyer's cart inside a database transaction, and the resulting cart item was verified.
- A request exceeding stock was rejected; product stock remained unchanged. All cart-test writes were rolled back. No customer cart was left modified and no orders were placed.
- Microsoft Edge browser checks returned HTTP 200 for the homepage, catalog, shop and all four detail pages. The four names appeared on the homepage, catalog search and original shop listing. Product images decoded successfully on detail pages; no JavaScript page errors were observed.
- Cart verification was at the service/database layer, not a signed-in browser click test. Sign in as your buyer to capture the Add to Cart screenshot.

## Screenshot URLs

A local SHOPPICK development server was started at `http://127.0.0.1:8010` (port 8000 may be used by Sipbrew).

- Homepage: http://127.0.0.1:8010/
- All four demo products: http://127.0.0.1:8010/products?q=ITEL%20304
- Original shop: http://127.0.0.1:8010/shop/panda-picks
- Earbud details: http://127.0.0.1:8010/product/itel304-demo-wireless-bluetooth-earbuds
- Fan details: http://127.0.0.1:8010/product/itel304-demo-portable-mini-fan
- Tumbler details: http://127.0.0.1:8010/product/itel304-demo-insulated-tumbler
- Lamp details: http://127.0.0.1:8010/product/itel304-demo-usb-rechargeable-desk-lamp

For a database screenshot, use this read-only query:

```sql
SELECT p.id, p.store_id, s.name AS shop, s.user_id AS seller_id,
       p.name, p.price, p.stock, p.sku, p.publication_status, p.moderation_status
FROM products p JOIN stores s ON s.id = p.store_id
WHERE s.slug = 'panda-picks'
  AND p.slug IN ('itel304-demo-wireless-bluetooth-earbuds',
                 'itel304-demo-portable-mini-fan',
                 'itel304-demo-insulated-tumbler',
                 'itel304-demo-usb-rechargeable-desk-lamp');
```

## Optional cleanup later (NOT executed)

Safest UI method: sign in as the original Panda Picks seller and archive only the four demo products identified by the IDs, names and SKUs above. Archiving uses the existing soft-delete behavior and leaves historical records and shared images intact.

Alternatively, after reviewing the records, run `php artisan tinker` and paste the following. It resolves the store again, requires all four exact marked records and refuses products with order history before archiving. It does not delete files, unrelated products or carts. Remove these products from your screenshot buyer's cart through the normal cart UI first.

```php
Illuminate\Support\Facades\DB::transaction(function () {
    $store = App\Models\Store::where('slug', 'panda-picks')->where('name', 'Panda Picks')->sole();
    $slugs = ['itel304-demo-wireless-bluetooth-earbuds', 'itel304-demo-portable-mini-fan', 'itel304-demo-insulated-tumbler', 'itel304-demo-usb-rechargeable-desk-lamp'];
    $products = $store->products()->whereIn('slug', $slugs)->lockForUpdate()->get();
    if ($products->count() !== 4) throw new RuntimeException('Expected exactly four demo products; inspect manually.');
    foreach ($products as $product) {
        if (($product->specifications['Demo batch'] ?? null) !== 'ITEL304-CHECKPOINT1'
            || !str_starts_with($product->sku, 'ITEL304-CHECKPOINT1-')
            || $product->orderItems()->exists()) {
            throw new RuntimeException('Marker mismatch or order history; no products archived.');
        }
    }
    foreach ($products as $product) $product->delete();
});
```

## Task files

Added only:
- `database/seeders/PandaPicksDemoProductsSeeder.php`
- `storage/app/public/products/itel304-portable-mini-fan.png`
- `documentation/panda-picks-demo-products.md`

The database now contains the four products and their normal related image/moderation records and moderation audit entries. Existing source edits in SHOPPICK were left untouched. No commits or pushes were made. No Sipbrew files were modified.
