# Sterling IT Deployment Checklist — Bond System

Use this one-page checklist when deploying the Sterling Bond System to a company server. For full technical detail, see [DEPLOYMENT.md](./DEPLOYMENT.md).

**Application:** Sterling Bond System (Laravel 12 + Inertia React)  
**Access:** Internal only (AD DNS + HTTPS)  
**Example URL:** `https://sici-bonds.sterling.local`

---

## 1. Server software (install before deploy)

- [ ] **Windows Server** (or Linux) with admin access
- [ ] **PHP 8.2+** with extensions: `pdo_mysql`, `gd`, `zip`, `openssl`, `mbstring`, `xml`, `curl`, `fileinfo`, `json`, `ctype`, `bcmath`, `tokenizer`
- [ ] **Composer** (latest stable)
- [ ] **Node.js 18+** and **npm** (for one-time frontend build)
- [ ] **MySQL / MariaDB** (app DB + existing KYC DB on same or reachable server)
- [ ] **LibreOffice** (for confirmation PDF generation)
  - Windows: `C:\Program Files\LibreOffice\program\soffice.exe`
- [ ] **Apache** or **IIS** with URL rewrite enabled

Verify PHP:

```bash
php -v
php -m
```

---

## 2. DNS & SSL

- [ ] Internal **A record** for app hostname (e.g. `sici-bonds.sterling.local` → app server IP)
- [ ] **HTTPS certificate** trusted by domain-joined PCs (required for camera/QR scan)
- [ ] Web server **document root** = `...\Bondsystem\public` (not project root)
- [ ] HTTP → HTTPS redirect configured

Health check after deploy:

```text
https://sici-bonds.sterling.local/up
```

---

## 3. MySQL databases

The app uses **two databases**:

| Database | Purpose | On Sterling server |
|----------|---------|-------------------|
| `bondsystem` (or chosen name) | Bond app data — users, requests, confirmations, deposits | **New** — create empty DB, run migrations |
| `kycsystem` | Obligee lookup (read-only) | **Already exists** — point config at it; do not migrate or overwrite |

- [ ] **Create new database** for the bond app (e.g. `bondsystem`)
- [ ] **Confirm existing KYC database** (e.g. `kycsystem`) is reachable
- [ ] MySQL user has:
  - **Full access** on `bondsystem` (read/write)
  - **Read-only** on `kycsystem` (SELECT only — recommended)
- [ ] Firewall: app server → MySQL on port **3306** (if DB is on another host)

**KYC credentials:** If the KYC database uses the **same MySQL host, username, and password** as the app database, set `KYC_DB_HOST`, `KYC_DB_USERNAME`, and `KYC_DB_PASSWORD` to the same values as `DB_HOST`, `DB_USERNAME`, and `DB_PASSWORD`. Only `KYC_DB_DATABASE` must differ (typically `kycsystem`).

---

## 4. Deploy application files

- [ ] Copy project to server (Git clone or zip)
- [ ] **Do not** copy from dev PC: `vendor/`, `node_modules/`, `.env`, `public/hot`
- [ ] Create production `.env` from `.env.example`

On the server (in project folder):

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Edit .env (see section 5)
php artisan key:generate
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 5. Production `.env` (minimum)

```env
APP_NAME="Sterling Bond System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sici-bonds.sterling.local
APP_FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=bondsystem
DB_USERNAME=<MySQL user>
DB_PASSWORD=<MySQL password>

KYC_DB_HOST=127.0.0.1
KYC_DB_DATABASE=kycsystem
KYC_DB_USERNAME=<same as DB_USERNAME if shared>
KYC_DB_PASSWORD=<same as DB_PASSWORD if shared>

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

- [ ] `APP_DEBUG=false`
- [ ] No `VITE_DEV_SERVER_URL` in production
- [ ] No `public/hot` file on server
- [ ] Store `.env` securely (secrets manager / restricted permissions)

---

## 6. Folder permissions

Web server user (e.g. `IIS_IUSRS` or Apache service account) needs **read/write** on:

- [ ] `storage/`
- [ ] `bootstrap/cache/`

Then:

```bash
php artisan storage:link
```

---

## 7. Firewall / network

| From | To | Port | Purpose |
|------|-----|------|---------|
| Client PCs | App server | 443 | HTTPS |
| App server | MySQL server | 3306 | App + KYC DB |
| App server | SMTP (optional) | 587/25 | Email |

---

## 8. First login & security

- [ ] Public registration is **disabled** — Super Admin creates users in-app
- [ ] Create or verify first **Super Admin** account after seed
- [ ] Confirm **Audit Logs** visible to Super Admin only

---

## 9. Post-deploy verification

- [ ] Login at `https://{hostname}/login` — no certificate warnings on domain PCs
- [ ] **Obligee search** on bond request form returns KYC records
- [ ] Submit a **test deposit** — bank accounts list loads
- [ ] Generate a **test confirmation** — PDF + QR created
- [ ] Open `/verify-certificate` — public verification works
- [ ] **Scan QR** on Confirmations page (HTTPS required)

---

## 10. Backup plan

| Asset | Priority |
|-------|----------|
| `bondsystem` MySQL database | Critical |
| `storage/app/private/` (generated confirmations) | Critical |
| `storage/app/public/` (receipts, seals, signatures) | High |
| `.env` / `APP_KEY` | Critical — keep same key on restore |

**KYC database:** Owned by the existing KYC system — follow Sterling’s existing KYC backup policy; the bond app only reads it.

---

## What IT does **not** need from the dev PC

- Local `.env` or `APP_KEY` (generate new on server unless migrating an existing install)
- `node_modules/`, `vendor/` (reinstall on server)
- XAMPP dev scripts (`scripts/setup-xampp-https.ps1`, local SSL certs)
- SQLite database (production uses MySQL)
