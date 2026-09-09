# KKK OS V1 - Stage 1 Setup

## Goal
Create one business order, persist it in MySQL, generate a unique Order ID, issue a secure dashboard link, and prove both 1-package and 2-package paths create exactly one `orders` record.

## 1. Create Laravel project
Run on the developer machine:

```bash
composer create-project laravel/laravel kkk-os
cd kkk-os
```

Copy the Stage 1 files from this package into the matching Laravel paths.

## 2. Configure MySQL
Create a development database and user, then set `.env`:

```env
APP_NAME="KKK OS"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kkk_os_dev
DB_USERNAME=kkk_os_dev
DB_PASSWORD=CHANGE_ME
```

Never commit real credentials.

## 3. Run migrations

```bash
php artisan migrate
```

Expected tables include:
- `order_number_sequences`
- `orders`
- `order_access_tokens`

## 4. Start development server

```bash
php artisan serve
```

## 5. Create a 1-package test order

```bash
curl -X POST http://127.0.0.1:8000/dev/orders \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"package_count":1,"customer_name":"Test Lelaki","customer_email":"TEST@EXAMPLE.COM"}'
```

Expected: HTTP 201, one `orders` row and a `dashboard_url`.

## 6. Create a 2-package test order

```bash
curl -X POST http://127.0.0.1:8000/dev/orders \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"package_count":2,"customer_name":"Test Dua Pakej"}'
```

Expected: HTTP 201 and exactly ONE additional `orders` row, with `package_count = 2`.

## 7. Run tests

```bash
php artisan test --testsuite=Feature
```

Stage 1 is DONE only when all Order tests pass.

## Security notes
- `/dev/orders` is deliberately limited to `local` and `testing` environments.
- Dashboard access uses a random secret token; only the SHA-256 hash is stored.
- Order ID is a public/business identifier, not an authorization secret.
- Operational orders are never permanently deleted in normal application workflows.

## Next stage
After Stage 1 passes, implement Customer Dashboard domain tables/migrations for couple, parents, design, event, contacts, fulfilment and confirmation, then Save & Resume and Final Review.
