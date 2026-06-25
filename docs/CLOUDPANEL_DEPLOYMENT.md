# Bondsystem CloudPanel Deployment Guide

This guide covers deploying the Bondsystem Laravel application to Sterling's local Linux server using CloudPanel.

## Target environment

- Server: Sterling local Linux server
- Panel: CloudPanel
- Site type: Laravel / PHP site
- Web server: Nginx through CloudPanel
- Internal domain example: `https://sici-bonds.sterling.local`
- Database: MySQL or MariaDB on the same server
- KYC database: also on the same server

## 1. CloudPanel site setup

1. Create a new CloudPanel site for the Bondsystem application.
2. Choose the Laravel / PHP site type.
3. Set the document root to the project's `public` directory.
4. Configure the internal DNS name or hosts entry so `sici-bonds.sterling.local` resolves to the server.
5. Install or attach a trusted HTTPS certificate for the internal hostname.
6. Confirm the site is served over HTTPS before enabling QR camera usage.

## 2. Server requirements

### PHP

- PHP 8.2 or newer
- Required extensions:
  - `pdo_mysql`
  - `gd`
  - `zip`
  - `openssl`
  - `mbstring`
  - `xml`
  - `curl`
  - `fileinfo`
  - `json`
  - `ctype`
  - `bcmath`
  - `tokenizer`

### Linux packages

Install the packages required by the application and document generation workflow:

- Composer 2.5 compatible Composer installation
- Node.js and npm
- LibreOffice
- A working MySQL or MariaDB client if you need to run backups or imports from the shell

LibreOffice is required for DOCX-to-PDF conversion. On Linux, the application looks for the configured `LIBREOFFICE_PATH` first, then common system binaries.

Example path:

```env
LIBREOFFICE_PATH=/usr/bin/libreoffice
```

## 3. Database setup

The application uses two databases:

- `bondsystem` for the Laravel application
- `kycsystem` for obligee lookup

Recommended setup:

1. Create the `bondsystem` database.
2. Confirm the existing `kycsystem` database is reachable.
3. Configure the application database user with full access to `bondsystem`.
4. Configure the KYC user with read-only access to `kycsystem` if possible.
5. Do not run migrations against the KYC database.

Example database variables:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bondsystem
DB_USERNAME=
DB_PASSWORD=

KYC_DB_HOST=127.0.0.1
KYC_DB_PORT=3306
KYC_DB_DATABASE=kycsystem
KYC_DB_USERNAME=
KYC_DB_PASSWORD=
KYC_CLIENTS_TABLE=clients
KYC_OBLIGEE_TYPE=obligee
KYC_COLUMN_COMPANY_NAME=company_name
KYC_COLUMN_ID=client_id
```

## 4. Production `.env` example

```env
APP_NAME="Sterling Bond System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sici-bonds.sterling.local
APP_FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bondsystem
DB_USERNAME=
DB_PASSWORD=

KYC_DB_HOST=127.0.0.1
KYC_DB_PORT=3306
KYC_DB_DATABASE=kycsystem
KYC_DB_USERNAME=
KYC_DB_PASSWORD=
KYC_CLIENTS_TABLE=clients
KYC_OBLIGEE_TYPE=obligee
KYC_COLUMN_COMPANY_NAME=company_name
KYC_COLUMN_ID=client_id

LIBREOFFICE_PATH=/usr/bin/libreoffice

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

TRUSTED_PROXIES=

MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
```

Important notes:

- Do not set `VITE_DEV_SERVER_URL` in production.
- Build frontend assets for production instead of using the Vite dev server.
- Keep `APP_URL` aligned with the final internal DNS hostname.

## 5. Deployment commands

Run these in the project directory on the server:

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

If this is a first-time install and Sterling wants the default permissions, seed only the required baseline data:

```bash
php artisan db:seed --class=RolePermissionSeeder
```

## 6. File permissions

The CloudPanel site user must be able to read and write:

- `storage/`
- `bootstrap/cache/`

Recommended approach:

- Set ownership to the CloudPanel site user and group.
- Grant write access only where needed.
- Do not chmod the entire project to `777`.

## 7. Private storage and exposure rules

The following data must stay out of public web exposure unless the application explicitly serves it through an authorized route:

- Deposit receipts
- Supporting documents
- Generated DOCX certificates
- Generated PDF certificates
- QR code images
- Certificate templates
- Backup files and backup records

Relevant storage locations include:

- `storage/app/private/`
- `storage/app/private/certificates/`
- `storage/app/private/generated-docx/`
- `storage/app/private/qr-codes/`
- `storage/app/private/certificate-templates/`
- `storage/app/private/backups/`

The `public/storage` symlink is only for the public disk, not for private files.

## 8. HTTPS and QR scanning

QR camera scanning requires HTTPS and a trusted browser context.

- Use a trusted HTTPS certificate for the internal DNS name.
- Make sure the hostname resolves on client PCs.
- Keep `APP_FORCE_HTTPS=true` enabled.
- If CloudPanel sits behind another proxy, configure trusted proxies so Laravel sees the correct scheme.

## 9. Scheduler and queue

Currently, the application does not require an active scheduler or queue worker for normal operation.

- No scheduler task is defined by default.
- No always-on queue worker is required unless Sterling later enables automated jobs such as scheduled backups.
- If automated backups are added later, a cron entry like the following can be used:

```bash
* * * * * php /path/to/Bondsystem/artisan schedule:run >> /dev/null 2>&1
```

If queue usage is introduced later, run a worker such as:

```bash
php artisan queue:work --tries=3
```

## 10. Backup checklist

Back up the following:

- `bondsystem` database
- `kycsystem` database
- `storage/app/private/`
- `.env`
- Uploaded templates
- Generated certificates
- Backup files and backup records

Recommended backup scope includes:

- `storage/app/private/certificates`
- `storage/app/private/generated-docx`
- `storage/app/private/qr-codes`
- `storage/app/private/certificate-templates`
- `storage/app/private/backups`

## 11. Troubleshooting

### 500 errors

- Confirm the document root is set to `public`.
- Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` again.
- Check `storage/logs/laravel.log`.

### Permission errors

- Verify `storage/` and `bootstrap/cache/` are writable by the CloudPanel site user.
- Avoid broad `777` permissions.

### Missing LibreOffice

- Install LibreOffice on the server.
- Verify `LIBREOFFICE_PATH` points to a valid binary or leave it unset so the app can fall back to common Linux paths.

### Broken assets after `npm run build`

- Make sure the build completed successfully.
- Confirm `public/hot` does not exist in production.
- Confirm `APP_URL` uses the final HTTPS hostname.

### QR camera not opening

- Confirm the site is loaded over HTTPS.
- Confirm the browser trusts the certificate.
- Confirm the page is opened on a supported device/browser with camera access.

### Domain not resolving

- Verify the internal DNS record or hosts entry points to the Sterling server.
- Confirm the hostname used in the browser matches `APP_URL`.

### Database connection errors

- Verify MySQL or MariaDB is running.
- Verify `DB_*` values point to `bondsystem`.
- Verify `KYC_DB_*` values point to `kycsystem`.
- Confirm the application user has access to both databases.

## 12. Post-deploy checks

- Open the site at `https://sici-bonds.sterling.local`.
- Log in and confirm the dashboard loads.
- Confirm KYC obligee lookup works.
- Generate a test confirmation and verify DOCX/PDF output.
- Verify a QR code can be scanned over HTTPS.
- Confirm backups can be created and downloaded.
- Confirm `/up` returns a healthy response.
