# SHOPPICK MySQL integration verification

Completed 14 September 2026. Laravel now connects directly to XAMPP MariaDB through the MySQL PDO driver; phpMyAdmin displays that same server/database.

## Connection and preserved records

- Database: `shoppick`
- Host: `127.0.0.1`
- Port: `3307`
- Laravel: http://127.0.0.1:8000/
- phpMyAdmin: http://localhost/phpmyadmin/
- Laravel version: 12.68.0
- `.env` remains ignored by Git. The application key, existing credentials, session settings and other application configuration were retained.

| Record set | SQLite | MySQL |
| --- | ---: | ---: |
| Tables (excluding SQLite internal tables) | 62 | 62 |
| Users | 27 | 27 |
| Products | 27 | 27 |
| Orders | 23 | 23 |
| Stores | 9 | 9 |
| Original Panda Picks products | 21 | 21 |
| Payments | 23 | 23 |
| Cart items | 5 | 5 |
| Seller orders | 17 | 17 |
| Shipments | 16 | 16 |
| Rider profiles | 3 | 3 |
| Custom notifications | 50 | 50 |

Every source row was compared across all 62 tables, including IDs, relationship fields, timestamps, password hashes, JSON, nullable fields and soft-deleted records. Numeric representations were normalized for comparison; 118 foreign-key references were checked with zero violations. SQLite's historical auto-increment high-water marks were preserved on MySQL. No seeds were run and no duplicate shops, users, orders or products were created.

See [the per-table verification report](mysql-integration/verification.json). The `migrations` table contains the 36 original entries plus one MySQL compatibility migration, so its target count is 37. All 37 migrations are marked as run; none are pending.

## Compatibility findings

1. Historical SQLite `product_variants.stock` integer-affinity fields contain fractional values. A narrowly scoped migration uses MySQL DOUBLE for this column to retain the existing values exactly. No stock was rounded, reset or normalized. Its rollback intentionally does not narrow the column and risk data loss. The migration is a no-op on SQLite.
2. One birthday was stored by SQLite with `00:00:00` after the date. MySQL DATE retains the same calendar date. Verification explicitly accepts only the midnight-to-date representation change; it refuses non-midnight truncation.
3. The initial strict row comparison caught that date representation difference. The copy transaction rolled back before it was corrected; no partially copied business data was committed.
4. Schema creation required temporarily disabling foreign-key checks because some original migrations reference tables created later. They were re-enabled, and all 118 references were explicitly checked after transfer. No migrations were run against the populated SQLite source.
5. Fresh migrations inserted ten permission metadata rows. These were reconciled with the source permission IDs without deleting rows or seeding users; the resulting permission table matches the source.

All source records transferred successfully. No business data was deleted. The explicitly requested `optimize:clear` cleared three transient application-cache entries after transfer verification; their original copies remain in both SQLite backups. Browser login tests naturally create/update session records, so runtime cache/session counts may differ from the preserved pre-switch report after use.

## Backups retained

- Original database, untouched: `database/database.sqlite`
- Consistent SQLite snapshot: `storage/app/private/mysql-transfer-20260914-065244/database.sqlite`
- Previous environment configuration: `storage/app/private/mysql-transfer-20260914-065244/env-before-mysql`
- Original verification report: `storage/app/private/mysql-transfer-20260914-065244/verification.json`

The backup directory is ignored by Git. Keep the original and snapshot until you personally approve the MySQL setup. SQLite was compared against the snapshot immediately before the switch to ensure no records had changed during transfer. Do not switch back casually after new MySQL orders or edits, because the SQLite backup will not contain those newer changes.

## Application checks

- Homepage, catalog, Panda Picks shop, product details and login page returned HTTP 200 in Microsoft Edge.
- Existing buyer and seller accounts signed in through the actual login form using their existing demo credentials; passwords were not reset.
- Authenticated buyer cart and seller orders pages returned HTTP 200; seller login reached the Seller Dashboard.
- Existing CartService successfully added one earbud product in a rolled-back MySQL transaction. A quantity above stock was rejected. No test cart item or stock change was left behind.
- Admin/logistics/rider records and their relationships were covered by the full-table copy comparison. Their individual account logins were not exercised.
- phpMyAdmin displayed `shoppick`, its actual table list and the four demo products through a read-only joined SQL query.
- Existing compiled Vite assets were reused. No dependency reinstall or UI changes were required.
- The existing SHOPPICK Artisan server at port 8000 was reused. It is serving the MySQL-backed application.

## Database-to-website example

`products.id = 24`: Wireless Bluetooth Earbuds, price `599.00`, stock `25`, store ID `1`. That store is the original Panda Picks (`panda-picks`), seller user ID `8`. The other shop also named Panda Picks was preserved separately and was not merged or duplicated.

## Screenshots ready for the PDF

These are genuine browser captures, not mockups:

1. [Database and tables](mysql-integration/01-database.png)
2. [Products with prices, stock and seller relationship](mysql-integration/02-products.png)
3. [Matching SHOPPICK catalog](mysql-integration/03-catalog.png)
4. [Earbuds product details](mysql-integration/04-product-details.png)

To recapture:

1. Open http://localhost/phpmyadmin/, select `shoppick`, then Structure.
2. Open its SQL tab and run the read-only query below.
3. Open http://127.0.0.1:8000/products?q=ITEL%20304.
4. Open http://127.0.0.1:8000/product/itel304-demo-wireless-bluetooth-earbuds.

```sql
SELECT p.id, p.name, p.price, p.stock, p.store_id,
       s.name AS shop, s.user_id AS seller_id
FROM products p
JOIN stores s ON s.id = p.store_id
WHERE s.slug = 'panda-picks'
  AND p.slug LIKE 'itel304-demo-%'
ORDER BY p.id;
```

## Operations performed

- Read-only migration/configuration inspection and `php artisan migrate:status`.
- `php artisan down` during copy; SQLite `VACUUM INTO` created a consistent snapshot.
- Created only `shoppick`, using utf8mb4 and utf8mb4_unicode_ci.
- Ran `migrate --database=mysql --force` with a temporary in-process MySQL connection configuration; `.env` remained SQLite during schema creation and transfer.
- Copied source rows with parameterized statements in a transaction; verified every table and foreign key before commit.
- Changed only `.env` database entries after verification.
- Ran `php artisan optimize:clear`, `php artisan migrate:status`, and `php artisan up`.
- Ran read-only Eloquent/PDO counts, rolled-back cart checks, existing-account browser logins and a phpMyAdmin SELECT.

No `migrate:fresh`, `migrate:refresh`, `db:wipe`, DROP DATABASE or DROP TABLE command was run. No application modules, checkout logic, authentication logic, existing migrations or frontend styling were changed. Nothing was committed or pushed.

## Files added/changed for this task

- `.env` database entries (not tracked).
- `database/migrations/2026_09_14_000001_preserve_sqlite_variant_stock_on_mysql.php` (new, MySQL-only compatibility).
- `documentation/mysql-integration.md` (this report).
- `documentation/mysql-integration/` (verification JSON and four screenshots).
- Private backup directory listed above.
