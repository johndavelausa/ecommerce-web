# Thrift Store Platform (Laravel)

Built from the specs in:

- `ThriftStore_System_Requirements.docx`
- `ThriftStore_Build_Order.docx`

## Database (no migrations)

This project uses **MySQL database `shop_db`** and a single SQL script instead of Laravel migrations:

- `shop_db.sql`

### Import the schema

1) Create/import the database schema:

```sql
SOURCE /absolute/path/to/shop_db.sql;
```

Or in phpMyAdmin, import the `shop_db.sql` file.

2) Configure Laravel:

- `thriftstore/.env`
  - `DB_CONNECTION=mysql`
  - `DB_DATABASE=shop_db`
  - `DB_USERNAME=...`
  - `DB_PASSWORD=...`
  - `FILESYSTEM_DISK=public`

3) Run seeders (roles, admin user, system settings):

```bash
cd thriftstore
php artisan db:seed
```

Default seeded admin credentials:

- Email: `admin@thriftstore.local`
- Password: `admin12345`

## Run the app

```bash
cd thriftstore
npm install
npm run dev
php artisan serve
php artisan queue:work
```

