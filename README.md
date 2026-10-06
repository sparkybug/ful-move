# FUL Move - Campus Shuttle Booking

A Laravel 12 monolith for the Federal University Lokoja coursework prototype. Blade, Tailwind CSS, a small amount of JavaScript, and MySQL/MariaDB. It implements the supplied eight-page requirements document: student, driver, and admin accounts; live boarding buses; one-seat reservations; internal transport wallets; check-in; walk-ins; and cancellation refunds.

## Open the prepared local demo

The project on this computer is configured at **http://127.0.0.1:8000**. Its isolated MariaDB instance listens on **127.0.0.1:3307**, with its data under `tmp/mysql`. It does not use XAMPP's existing data directory.

If the computer or services restart:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\Start-Demo.ps1
```

This script only starts the already configured local database and application. It does not reset data, seed users again, change system services, or open a firewall port.

## Fresh installation

Requirements: PHP 8.2+ with PDO MySQL, mbstring, XML, ctype, curl, fileinfo, zip and OpenSSL; Composer; Node.js 22+; pnpm 11+; MySQL 8+ or compatible MariaDB with InnoDB.

1. Run `composer install`.
2. Copy `.env.example` to `.env`, create an empty database, and enter its connection details.
3. Run `php artisan key:generate`.
4. Run `php artisan migrate --seed` for a local demonstration.
5. Run `pnpm install --frozen-lockfile` then `pnpm build`.
6. Run `php artisan demo:boarding` to populate the board with sample runs, or open a run manually as a driver.
7. Run `php artisan serve` and visit http://127.0.0.1:8000.

On this Windows machine PHP is `C:\xampp\php\php.exe`. Composer is `C:\ProgramData\ComposerSetup\bin\composer.phar`. Add PHP to PATH or invoke those absolute paths. Node and pnpm are also available through the bundled workspace runtime.

A driver-provided estimate may expire while boarding remains open. The app correctly displays “Departure time pending update”; sign in as the driver to revise the estimate or depart. It never silently invents a new estimate.

## Five-minute demonstration

See [docs/DEMO.md](docs/DEMO.md) for the walkthrough and expected numbers.

## Tests

```sh
php artisan test
php vendor/phpunit/phpunit/phpunit -c phpunit.mysql.xml --testdox
```

The first command uses in-memory SQLite for fast feature tests; the real row-lock test is skipped there. The second runs all tests and independent-process concurrency races against the dedicated `ful_shuttle_test` database on port 3307. Edit only the test connection values in `phpunit.mysql.xml` for another machine. The test harness refuses MySQL database names that do not end in `_test`.

**The MySQL test suite recreates the test database tables. Never point it at application data.**

Verified on the prepared computer: 25 tests, 155 assertions against MariaDB, including:

- Two students race for the final seat.
- One student submits the same booking twice concurrently.
- One student tries to book two different boarding buses concurrently.
- A walk-in update competes with an online reservation.
- Two requests cancel and refund the same run.
- Insufficient balance and full-bus failures move no credits.
- Boarded and departed ticket rules; fare/capacity snapshots; role and ownership restrictions.
- The complete ₦1,000 → ₦800 → ₦1,000 assessment example and admin ledger totals.

## How the integrity rules work

All state-changing booking operations live in `app/Services/ShuttleService.php`. A booking locks the student, then its run, then affected wallets in ascending wallet ID order. Walk-in updates lock that same run. Cancellations lock the run and all affected wallets before writing refunds. Transactions retry deadlocks up to five times.

Currency is integer kobo. Every successful booking creates a transfer with a student debit and driver credit in the same transaction as the ticket. Refunds create opposite ledger entries with a unique original-transfer reference. Unique operation keys make booking/credit retries idempotent. Generated unique columns prevent two active runs per driver or bus and duplicate active student/run bookings. The student lock serializes cross-run booking checks.

No withdrawals exist, so driver earnings remain available to reverse eligible bookings. Walk-ins affect capacity only. Reports explicitly describe online booking revenue and exclude cash.

The public list polls every 12 seconds. Booking refreshes seat availability and wallet balance before displaying a confirmation dialog, then revalidates both on the server when submitted. Run fares and capacity are snapshots; admin changes apply to later runs.

## Deployment

For the Docker image and free Render + Aiven setup, follow [docs/RENDER.md](docs/RENDER.md).
The root `Dockerfile` builds the app; `render.yaml` supplies the Render service settings.

Deployment needs a hosting account and database; this delivery is configured locally. Use an HTTPS Laravel/PHP host with the web root pointing to `public/`.

- Install production dependencies with `composer install --no-dev --optimize-autoloader`.
- Build assets with `pnpm install --frozen-lockfile && pnpm build`.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, the HTTPS `APP_URL`, database credentials, and `SESSION_SECURE_COOKIE=true`.
- Make `storage` and `bootstrap/cache` writable by the PHP process.
- Run `php artisan migrate --force`, then `php artisan app:create-admin` to create your real admin securely via the terminal.
- Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
- Configure real terminals and fares through the admin area. Drivers register their buses for approval.

For the hosted coursework demo, set `RUN_SEEDERS=true`: Docker seeds the sample records and opens missing boarding runs automatically after migrations. Repeated startups preserve accounts, credits, bookings and active runs. Only the four sample bus/driver pairs are opened. Set `RUN_SEEDERS=false` for normal operations with unique account passwords; production demo commands require explicit demo mode (`RUN_SEEDERS=true` or `ALLOW_DEMO_SEED=true`). This application contains demonstration credits, not real money or externally funded wallets. See [docs/RENDER.md](docs/RENDER.md) for details.

No queue worker, websocket server, GPS, payment gateway, map service, SMS provider, or separate API is needed.

## Main files

- `app/Services/ShuttleService.php`: seat and wallet rules.
- `database/migrations/2026_10_05_000001_create_shuttle_tables.php`: schema, foreign keys, uniqueness rules.
- `app/Http/Controllers` and `routes/web.php`: role-protected workflows.
- `resources/views`, `resources/css/app.css`, `resources/js/app.js`: responsive interface.
- `database/seeders/DatabaseSeeder.php`: repeatable local demo setup.
- `tests/Feature/ShuttleTest.php` and `tests/Feature/ConcurrencyTest.php`: acceptance checks.

Implementation references: [Laravel transactions](https://laravel.com/docs/12.x/database#database-transactions) and [pessimistic locking](https://laravel.com/docs/12.x/queries#pessimistic-locking).
