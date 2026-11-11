# Running the Inventory & Distribution Platform

This document lists every command needed to install, configure, run, and test
the platform. It has two parts — a **Laravel/MySQL/Redis backend** and a
**Next.js frontend** — that run as separate processes.

For a one-shot setup, use the helper scripts at the repo root:

- Windows (PowerShell): `./setup.ps1`
- macOS / Linux (bash):  `./setup.sh`

Everything below is what those scripts automate, step by step.

---

## Quickest path: Docker (no local toolchain)

If you have Docker Desktop (or Docker Engine + Compose) installed, you do **not**
need PHP, Composer, Node, MySQL, or Redis locally. From the repo root:

```bash
docker compose up --build
```

This starts five containers — MySQL, Redis, the Laravel API (which migrates and
seeds on first boot), the queue worker, and the Next.js frontend. Then open:

- Frontend: http://localhost:3000
- API health: http://localhost:8000/api/health

Common commands:

```bash
docker compose up --build        # build + start everything (foreground)
docker compose up -d --build     # same, detached
docker compose logs -f api       # tail a service (api | worker | frontend | mysql | redis)
docker compose down              # stop + remove containers
docker compose down -v           # also drop the MySQL volume (full data reset)
```

The rest of this document covers running the stack **without** Docker.

---

## Superadmin (Platform_Admin) bootstrap

There is **no pre-seeded superadmin**. The platform has a `Platform_Admin` role
that gates the central `/api/admin/*` routes (tenant provisioning, impersonation),
but no such user is created automatically. Create one and get a usable API token
with the bundled artisan command:

```bash
# Docker:
docker compose exec api php artisan platform:admin
# Local (no Docker), from backend/:
php artisan platform:admin
```

Defaults: `superadmin@platform.test` / `password123`. Override with options:

```bash
docker compose exec api php artisan platform:admin \
  --email=admin@yourco.com --password='StrongPass123' --name="Super Admin"
```

The command prints a **Bearer token**. Use it against the central admin API
(these routes use the bare host, no tenant subdomain):

```bash
# Verify the token
curl -H "Authorization: Bearer <TOKEN>" http://localhost:8000/api/admin/ping
# -> {"status":"ok","scope":"platform.admin"}

# Provision a tenant
curl -X POST http://localhost:8000/api/admin/tenants \
  -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"subdomain":"acme","domain":"acme","plan_id":1,"company_name":"Acme Corp","admin_name":"Acme Admin","admin_email":"admin@acme.test","admin_password":"password123"}'
```

> Tenant login (`/api/login`) is different: it is tenant-scoped, so send the
> request with the tenant subdomain host (e.g. `Host: demo.localhost`), not the
> bare `localhost`. See the login notes in this file.

### Unified login (one page decides who you are)

There is a **single** login page at `/login`. It authenticates in two layers
and routes by the result:

1. **Central (Platform_Admin)** via `POST /api/platform/login` — succeeds for a
   superadmin in the central database → redirected to the **/platform console**.
2. **Tenant** via `POST /api/login` — if central auth returns 401, it falls back
   to the tenant login resolved by the request host (subdomain) → redirected to
   **/dashboard**.

So the superadmin and tenant users use the same login box; the backend + host
decide the destination.

### Superadmin console (/platform)

After signing in as a Platform_Admin, the console provides full master config:

- **Tenants** — provision (creates DB + Company + Company_Admin), list, toggle
  backorders / dealer-stock, delete (drops the tenant DB).
- **Plans** — create, activate/deactivate, delete; shows quota limits + feature
  flags.
- **Subscriptions** — list across all tenants, change lifecycle status.

Bootstrap the first superadmin (no UI needed the first time):

```bash
docker compose exec api php artisan platform:admin
# -> superadmin@platform.test / password123  (override with --email/--password)
```

Then sign in at `/login` with those credentials.

---

## Wildcard host routing (nginx)

The stack includes an nginx reverse proxy that serves the apex and every tenant
subdomain from one entry point on **port 80**, forwarding the original `Host` so
the backend resolves the correct tenant (`stancl/tenancy`):

| URL                               | Resolves to                          |
|-----------------------------------|--------------------------------------|
| `http://localhost/`               | apex → platform/central (superadmin) |
| `http://acme.localhost/`          | tenant `acme` workspace              |
| `http://localhost/api/...`        | central API                          |
| `http://acme.localhost/api/...`   | tenant `acme` API                    |

In production, point a wildcard DNS record `*.example.com` (plus the apex) at
this proxy and terminate TLS there — every tenant subdomain and the platform
console at the apex are served by the same stack. The nginx config lives at
`nginx/default.conf`; add your real domain to its `server_name` line.

> Local note: `*.localhost` resolves to 127.0.0.1 automatically on most
> systems, so `http://acme.localhost` works with no hosts-file edits.

---

## 0. Prerequisites

Install these once on your machine:

| Tool        | Version        | Notes                                             |
|-------------|----------------|---------------------------------------------------|
| PHP         | 8.5+           | with `pdo_mysql` extension                        |
| Composer    | 2.x            | PHP dependency manager                            |
| Node.js     | 18.18+ / 20+   | for the Next.js frontend                          |
| npm         | 9+             | ships with Node                                   |
| MySQL       | 8.x            | central DB + one DB per tenant                    |
| Redis       | 6+             | queue + cache                                     |

Make sure MySQL and Redis are running before starting the backend.

```bash
# Verify versions
php --version
composer --version
node --version
npm --version
```

---

## 1. Backend (Laravel) — `backend/`

### 1.1 Install dependencies

```bash
cd backend
composer install
```

### 1.2 Create the environment file and app key

```bash
# Copy the template (every optional feature defaults OFF, so it boots as-is)
# Windows PowerShell:
Copy-Item .env.example .env
# macOS / Linux:
cp .env.example .env

php artisan key:generate
```

### 1.3 Configure the central database

Create the central database in MySQL, then set the credentials in `.env`
(`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Default DB name is
`inventory_central`.

```sql
CREATE DATABASE inventory_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 1.4 Run migrations and seed base data

```bash
php artisan migrate --force
php artisan db:seed            # seeds roles/permissions + starter plan data
```

> Tenant databases are created per-tenant at provisioning time by
> `stancl/tenancy`; you do not create them by hand.

### 1.5 Start the backend (choose one)

```bash
# Simple built-in server (http://localhost:8000)
php artisan serve

# OR the all-in-one dev runner (server + queue worker + log tail), if configured
composer dev
```

### 1.6 Run the queue worker (separate terminal)

Order confirmations, notifications, exports, and tenant provisioning run on
Redis-backed queues.

```bash
cd backend
php artisan queue:work
```

### 1.7 (Optional) Tail logs

```bash
php artisan pail
```

---

## 2. Frontend (Next.js) — `frontend/`

### 2.1 Install dependencies

```bash
cd frontend
npm install
```

### 2.2 Create the environment file

```bash
# Windows PowerShell:
Copy-Item .env.example .env.local
# macOS / Linux:
cp .env.example .env.local
```

Set in `.env.local`:

```dotenv
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000
# DEV ONLY: pin a provisioned tenant host so the backend resolves a tenant
# while running on localhost (no real subdomain). Example:
# NEXT_PUBLIC_TENANT_HOST=acme.localhost
```

### 2.3 Start the dev server (http://localhost:3000)

```bash
npm run dev
```

### 2.4 Production build + start

```bash
npm run build
npm run start
```

---

## 3. Running tests

### Backend (Pest / PHPUnit — includes property-based tests)

```bash
cd backend
php artisan test
# or: composer test
# a single file:  php artisan test tests/Feature/SomeTest.php
```

### Frontend (Vitest + accessibility)

```bash
cd frontend
npm run typecheck      # tsc --noEmit
npm run lint           # next lint
npm test               # vitest run
npm run test:a11y      # accessibility suite only
```

---

## 4. Typical local run (summary)

Open **three terminals**:

```bash
# Terminal 1 — backend API
cd backend && php artisan serve

# Terminal 2 — queue worker
cd backend && php artisan queue:work

# Terminal 3 — frontend
cd frontend && npm run dev
```

Then open http://localhost:3000 and sign in. Provision a tenant via the
`POST /api/admin/tenants` platform-admin endpoint (requires a Platform_Admin
token) before tenant login works.

---

## 5. Resetting the database (destructive)

```bash
cd backend
php artisan migrate:fresh --seed    # drops all central tables and re-migrates
```

> This drops and recreates the **central** schema. Tenant databases are managed
> by `stancl/tenancy`; remove them separately if you need a full reset.
