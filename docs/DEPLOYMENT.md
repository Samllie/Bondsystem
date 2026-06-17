# Bond System — Production Deployment Guide

This document describes requirements and steps for deploying the Sterling Bond System on a dedicated server inside Sterling Insurance Company's Active Directory environment.

## Overview

The application is a Laravel 12 + Inertia React system. Production deployment uses:

- A company-managed internal DNS hostname (for example `https://sici-bonds.sterling.local`)
- A trusted HTTPS certificate issued by Sterling IT
- PHP behind Apache or IIS with the document root set to `public/`
- MySQL/MariaDB for the application database
- A separate read-only connection to the KYC obligee database

User accounts are provisioned by Super Admin inside the application. **Public self-registration is disabled.**

For a printable handoff to Sterling IT, use [STERLING_IT_CHECKLIST.md](./STERLING_IT_CHECKLIST.md).

---

## PHP requirements

| Requirement | Version / detail |
|-------------|------------------|
| PHP | **8.2 or newer** (`composer.json` requires `^8.2`) |
| Composer | Latest stable |
| Memory | 512 MB minimum; 1 GB+ recommended for certificate generation |

### Required PHP extensions

| Extension | Purpose |
|-----------|---------|
| `pdo_mysql` | Application and KYC database connections |
| `gd` | QR code image generation |
| `zip` | DOCX template processing |
| `openssl` | HTTPS and encryption |
| `mbstring` | String handling |
| `xml` | DOCX / Word processing |
| `curl` | HTTP client operations |
| `fileinfo` | File upload validation |
| `tokenizer`, `json`, `ctype`, `bcmath` | Laravel framework |

Verify extensions:

```bash
php -m
```

---

## LibreOffice requirements

Certificate PDF generation converts filled DOCX files to PDF using **LibreOffice headless**.

| Item | Detail |
|------|--------|
| Software | LibreOffice (current stable) |
| Windows path | `C:\Program Files\LibreOffice\program\soffice.exe` |
| Linux path | `soffice` or `/usr/bin/libreoffice` on `PATH` |
| Usage | Invoked automatically during certificate generation |

If LibreOffice is missing or not on `PATH`, certificate generation will fail when producing PDF output.

---

## APP_URL requirements

`APP_URL` is the canonical public URL of the application. It drives:

- QR code verification URLs embedded in generated certificates
- Absolute URLs in emails (password reset, verification)
- Public storage URLs (`/storage/...`)
- HTTPS enforcement defaults

### Rules

1. Set `APP_URL` to the **final production hostname** before generating production certificates.
2. Use `https://` in production.
3. Do **not** include a trailing slash.
4. Treat the hostname as **stable** — printed QR codes embed this URL at generation time.

### Example

```env
APP_URL=https://sici-bonds.sterling.local
APP_FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
```

Changing `APP_URL` after certificates are issued does **not** update QR codes already printed on PDFs. Plan DNS and hostname before go-live.

---

## DNS requirements

| Item | Detail |
|------|--------|
| Record type | Internal **A** (or **AAAA**) record |
| Hostname | IT-approved FQDN, e.g. `sici-bonds.sterling.local` |
| Resolution | All client PCs must resolve the hostname via Sterling AD DNS |
| Public access | Not required; system is internal |

Client PCs reach the application at the same hostname configured in `APP_URL`.

---

## SSL requirements

| Item | Detail |
|------|--------|
| Certificate | Company-managed, trusted by all domain-joined PCs |
| Termination | Apache, IIS, or reverse proxy |
| Minimum | TLS 1.2+ |
| Camera / QR scanning | Requires HTTPS trusted by client browsers |

Laravel does **not** read certificate files directly. TLS is configured on the web server (or load balancer).

### Reverse proxy / load balancer

When TLS terminates in front of PHP, configure trusted proxies so Laravel detects HTTPS correctly:

```env
TRUSTED_PROXIES=*
```

Or list specific proxy IPs:

```env
TRUSTED_PROXIES=10.0.0.10,10.0.0.11
```

Leave `TRUSTED_PROXIES` empty when Apache/IIS terminates TLS directly on the app server.

---

## Storage permissions

The web server user must be able to read and write:

| Path | Purpose |
|------|---------|
| `storage/` | Logs, sessions, cache, uploads, generated files |
| `storage/app/private/certificates/` | Generated PDF certificates |
| `storage/app/private/generated-docx/` | Generated DOCX files |
| `storage/app/private/qr-codes/` | QR code PNG files |
| `storage/app/public/` | Signatures, notary seals, deposit receipts |
| `bootstrap/cache/` | Compiled config/routes/views |

After deployment:

```bash
php artisan storage:link
```

Ensure `public/storage` symlink exists for public disk URLs.

---

## Production environment variables

Copy `.env.example` to `.env` and configure at minimum:

```env
APP_NAME="Sterling Bond System"
APP_ENV=production
APP_DEBUG=false
APP_KEY=                         # php artisan key:generate
APP_URL=https://sici-bonds.sterling.local
APP_FORCE_HTTPS=true
TRUSTED_PROXIES=                 # or * / comma-separated IPs behind a proxy

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bondsystem
DB_USERNAME=
DB_PASSWORD=

KYC_DB_HOST=
KYC_DB_PORT=3306
KYC_DB_DATABASE=kycsystem
KYC_DB_USERNAME=
KYC_DB_PASSWORD=
```

### KYC database (existing on Sterling server)

The bond app connects to a **separate read-only KYC database** for obligee lookup. On Sterling’s server, this database typically **already exists** (e.g. `kycsystem`). Do **not** run migrations against it or overwrite it — only create and migrate the **application** database (e.g. `bondsystem`).

When the KYC database uses the **same MySQL host, username, and password** as the app database, set `KYC_DB_HOST`, `KYC_DB_USERNAME`, and `KYC_DB_PASSWORD` to the same values as `DB_HOST`, `DB_USERNAME`, and `DB_PASSWORD`. Only `KYC_DB_DATABASE` must differ. Laravel’s KYC connection also falls back to `DB_*` values when `KYC_DB_*` entries are omitted (see `config/database.php`).

Grant the MySQL user **SELECT only** on the KYC database if possible; full read/write is required only on the bond application database.

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp                  # configure for password reset if used
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
```

Do **not** set `VITE_DEV_SERVER_URL` in production. Build frontend assets with `npm run build` and ensure `public/hot` does not exist on the server.

---

## Deployment steps

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Edit .env with production values
php artisan key:generate
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder   # initial install only
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Web server

- Document root: `/path/to/bondsystem/public`
- Enable URL rewriting (Apache `mod_rewrite` or IIS URL Rewrite)
- Redirect HTTP to HTTPS at the web server or via Laravel `APP_FORCE_HTTPS`

### Health check

```bash
curl -f https://sici-bonds.sterling.local/up
```

---

## Network and firewall

| Source | Destination | Port | Purpose |
|--------|-------------|------|---------|
| Client PCs | App server | 443 | HTTPS application access |
| App server | MySQL server | 3306 | Application database |
| App server | KYC DB server | 3306 | Obligee lookup |
| App server | SMTP relay | 587/25 | Email (optional) |

---

## Queue and scheduler

| Component | Status |
|-----------|--------|
| Queue worker | Optional today — `QUEUE_CONNECTION=database` is configured but no async jobs are dispatched |
| Scheduler | No scheduled tasks defined — cron not required unless added later |

If queue usage is introduced later, run:

```bash
php artisan queue:work --tries=3
```

---

## Security notes

- **Public registration is disabled.** Users are created by Super Admin only.
- **Public certificate verification** is rate limited (60 reads/minute, 20 searches/minute per IP).
- **Audit logs** are visible to Super Admin only.
- Set `APP_DEBUG=false` in production.

---

## Backup & disaster recovery

The application includes a built-in **Backup Management** module (Maintenance → Backups, Super Admin only). It creates local archives under:

```
storage/app/private/backups/
├── database/     # backup_YYYY_MM_DD_HHMMSS.sql
├── files/        # files_YYYY_MM_DD_HHMMSS.zip
└── full/         # full_backup_YYYY_MM_DD_HHMMSS.zip
```

### Backup types

| Type | Contents |
|------|----------|
| **Database only** | Full SQL dump of the application database (bond requests, deposits, certificate versions, templates metadata, audit logs, users, roles, permissions) |
| **Files only** | Certificates (PDF), generated DOCX, QR images, deposit receipts, signatures, notary seals, uploaded template DOCX, fallback templates |
| **Full backup** | SQL dump (`database/backup.sql`) plus all protected files in one ZIP |

Backups are created through the UI or Artisan:

```bash
php artisan backups:create database
php artisan backups:create files
php artisan backups:create full
php artisan backups:cleanup
```

On MySQL/MariaDB production servers, configure `BACKUP_MYSQLDUMP_PATH` in `.env` if `mysqldump` is not on the default PATH. When `mysqldump` is unavailable, the system falls back to a PHP-based exporter.

### Retention policy

Default retention is **30 days** (`BACKUP_KEEP_DAYS` in `.env`, see `config/backups.php`).

- `php artisan backups:cleanup` deletes **completed** backups older than the retention period.
- **Failed** backups are never auto-deleted.

Example schedule for Sterling IT (not enabled automatically — add to the server crontab if desired):

| Schedule | Command |
|----------|---------|
| Daily 01:00 | `php artisan backups:create database` |
| Weekly Sunday 02:00 | `php artisan backups:create full` |
| Monthly 1st 03:00 | `php artisan backups:cleanup` |

### Restore strategy (manual only)

Automatic restore is **intentionally disabled** to prevent accidental data loss.

1. Put the application in maintenance mode.
2. Download the required backup from **Maintenance → Backups**.
3. Restore the SQL file with `mysql` or your DBA tooling.
4. Extract file archives into the matching storage paths on the server.
5. Verify permissions and run `php artisan storage:link` if needed.
6. Confirm sample bond requests, receipts, and confirmation PDFs open correctly.
7. Disable maintenance mode after Sterling IT validates the restore.

Preserve the same `APP_KEY` when restoring to avoid invalidating sessions and encrypted data.

### Storage requirements

Plan disk space for:

| Asset | Notes |
|-------|-------|
| MySQL application database | Grows with bond requests, audit logs, versions |
| `storage/app/private/backups/` | Retained archives (monitor free space) |
| `storage/app/private/certificates/` | Generated PDFs |
| `storage/app/private/generated-docx/` | Generated DOCX |
| `storage/app/private/qr-codes/` | QR PNG files |
| `storage/app/public/` | Receipts, signatures, seals |
| Uploaded certificate templates | `storage/app/private/certificate-templates/` |

Copy completed backup archives off-server (NAS, tape, or Sterling IT backup jobs) for true disaster recovery. The module does not upload to cloud services.

### Legacy asset summary

| Asset | Priority |
|-------|----------|
| MySQL application database | Critical |
| `storage/app/private/certificates/` | Critical |
| Certificate templates (DB + uploaded DOCX) | Critical |
| `storage/app/public/` (signatures, seals, receipts) | High |
| `.env` (store in a secure secrets manager) | Critical |

---

## QR URL stability

Verification QR codes encode the full URL:

```
https://{APP_URL host}/verify-certificate/{64-char-token}
```

Confirmation numbers (`SICI-BOND-2026-XXXXXXXX-V1`) remain valid regardless of hostname. If the hostname must change after certificates are printed, coordinate with Sterling IT for DNS aliases or certificate re-generation.

---

## Post-deployment verification

1. Open `https://{hostname}/login` — trusted certificate, no browser warnings on domain PCs.
2. Log in as Super Admin.
3. Generate a test certificate — confirm PDF and QR code are created.
4. Scan QR code or visit `/verify-certificate` — public verification works.
5. Test camera scan on Certifications page (requires trusted HTTPS).
6. Confirm obligee search works (KYC database connectivity).
7. Create a test backup from **Maintenance → Backups** and download it to confirm archive integrity.

---

## Development-only artifacts

The following are for local XAMPP development and are **not required in production**:

- `xampp-vhost.conf`, `xampp-vhost-ssl.conf`
- `scripts/setup-xampp-https.ps1`, `scripts/generate-local-ssl-cert.ps1`
- `storage/certs/local/server.crt`, `server.key`
- `add-hosts-entry.ps1`
- `public/hot` (Vite dev mode marker)
