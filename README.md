# Thrift Store Platform (Laravel)

Built from the specs in:

- `ThriftStore_System_Requirements.docx`
- `ThriftStore_Build_Order.docx`

This repo contains the Laravel app in `thriftstore/`.

## Requirements

- PHP 8.2+
- Composer 2.x
- Node.js 18+ (or 20/22) + npm
- MySQL 8+ (or MariaDB 10.6+)

## Setup (local)

From the repo root:

```bash
cd thriftstore
```

1) Install PHP dependencies:

```bash
composer install
```

2) Configure env:

```bash
copy .env.example .env
php artisan key:generate
```

Update `.env` to match your DB:

- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=shop_db`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`

3) Migrate + seed:

```bash
php artisan migrate --seed
```

4) Storage symlink (for uploaded images):

```bash
php artisan storage:link
```

5) Install frontend deps + run Vite:

```bash
npm install
npm run dev
```

PowerShell note (Windows): if `npm` fails with “running scripts is disabled”, either run `npm.cmd` instead of `npm`,
or change your execution policy (e.g. `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`).

6) Run the app:

```bash
php artisan serve
```

App URL: `http://127.0.0.1:8000`

Optional (recommended): run the queue worker in a second terminal:

```bash
php artisan queue:work
```

## Default admin account

Seeded by `Database\\Seeders\\AdminSeeder`:

- URL: `http://127.0.0.1:8000/admin/login`
- Email: `admin@thriftstore.local`
- Password: `admin12345`

## Legacy database SQL (optional)

The repo also includes SQL dumps:

- `shop_db.sql`
- `order_flow_alter.sql`

They’re not required if you use `php artisan migrate`. If you import SQL manually, you’ll still want to run:

```bash
php artisan db:seed
```

## Common commands

```bash
php artisan optimize:clear
php artisan view:clear
php artisan route:list
php artisan test
```
