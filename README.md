# SHOPPICK Marketplace

SHOPPICK is a Laravel multi-vendor marketplace connecting Buyers, Sellers, Admins, Super Admins, Logistics managers, and Riders. It includes seller-created products, cart and checkout, COD collection, multi-seller fulfillment, order tracking, moderation, shop management, and logistics operations.

## Requirements

- PHP 8.2+
- Composer
- Node.js and npm
- SQLite or MySQL

## Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure the `DB_*` values in `.env`. For SQLite, create `database/database.sqlite` and set:

```env
DB_CONNECTION=sqlite
```

For MySQL, set `DB_CONNECTION=mysql` plus the host, port, database, username, and password. Never commit `.env` or a local database file.

Build and initialize the application:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
npm run build
php artisan serve
```

Run the regression suite with:

```bash
php artisan test
```

## Development accounts

Development users and RBAC permissions are created by the project seeders. Review `database/seeders/UserSeeder.php` for the current local-only accounts and change credentials outside local development.

The Logistics demo is explicitly development data. It uses the same Order, SellerOrder, Shipment, notification, tracking, and dispatch architecture as normal seller-created products.

## Optional integrations

`.env.example` documents optional Google authentication, image moderation, routing, maps, and live tracking settings. Empty keys safely leave external integrations unconfigured. Critical order, notification, and shipment creation runs synchronously and does not depend on a queue worker.

## Git workflow

The consolidated marketplace and logistics work is prepared on:

```text
shoppick-latest-update
```

Clone that branch directly for testing:

```bash
git clone -b shoppick-latest-update https://github.com/prnzz19/Shoppick.git
```

A normal `git clone https://github.com/prnzz19/Shoppick.git` checks out the repository's default branch, usually `main`, and will not include this branch until it is explicitly checked out or merged.

Do not merge into `main` without first fetching `origin`, reviewing newer team commits, running migrations on an isolated database, and passing the full test/build suite.

## Safe reconciliation commands

The following commands are idempotent and support `--dry-run` where applicable:

```bash
php artisan logistics:reconcile-ready-orders --dry-run
php artisan orders:reconcile-buyer-progress-notifications --dry-run
php artisan payments:reconcile-premature-cod --dry-run
```

These are intended for repairing older local records after deployment of the relevant migrations. They do not replace normal fulfillment workflows.
