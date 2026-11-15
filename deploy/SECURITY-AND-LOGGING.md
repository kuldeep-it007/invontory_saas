# Security scanning (VAPT), centralized logging and log retention

This guide covers:

1. [What runs and when](#1-what-runs-and-when)
2. [One-time GitHub setup](#2-one-time-github-setup)
3. [The deployment gate: severities, exceptions, overrides](#3-the-deployment-gate)
4. [Production scanning: scope and limits](#4-production-scanning-scope-and-limits)
5. [Centralized logging and Telescope](#5-centralized-logging-and-telescope)
6. [Log archive in the master bucket (365+ days)](#6-log-archive-in-the-master-bucket)
7. [Performance changes](#7-performance-changes)
8. [Configuration reference](#8-configuration-reference)
9. [Testing evidence](#9-testing-evidence)
10. [Rollback](#10-rollback)

Everything on the server goes through `/opt/inventory/deploy.sh`, for you and for GitHub Actions alike (`deploy.sh help` lists every command).

---

## 1. What runs and when

> **Currently switched OFF.** The workflow does nothing until you set the repository
> variable `VAPT_ENABLED` to `true` (Settings → Secrets and variables → Actions →
> Variables). While it is off there are no scans, **no automatic deploys** and no reports;
> deploy by hand with `/opt/inventory/deploy.sh update`. To switch it back on, finish the
> setup in section 2 first, then set `VAPT_ENABLED=true`. No code change is needed.

Workflow: `.github/workflows/vapt.yml`

| Job | When | What |
|---|---|---|
| **scan** | every push, pull request, manual run | gitleaks (secrets, full history) · `composer audit` · `npm audit` · Semgrep (OWASP Top 10, PHP, TypeScript, secrets) · Trivy (Dockerfile/compose misconfiguration, both images: OS + library CVEs, secrets) → **gate** |
| **deploy** | push to `main`, gate passed | SSH to the server → `deploy.sh update` (the CI key can run nothing else) |
| **dast** | after a successful deploy | OWASP ZAP active scan (production, scoped) · testssl.sh (TLS) · nmap (open ports) · Lynis (server hardening, via `deploy.sh server-audit`) |
| **report** | always (not for PRs) | one HTML + JSON report → emailed (Mailgun) → GitHub artifact (90 days) → master bucket `vapt-reports/YYYY/MM/DD/<run>-<commit>/` (kept for your history) |

Pull requests get the scan and gate and a summary on the run page. Nothing is deployed or emailed.

If `main` moves on while a run is scanning, that run does not deploy. The newer run scans and deploys the newer commit, so unscanned code is never deployed.

---

## 2. One-time GitHub setup

Repository → **Settings** → **Secrets and variables** → **Actions**.

### 2.1 Deploy access

On the server, once:

```
/opt/inventory/deploy.sh github-setup
```

It creates an SSH key for GitHub Actions and registers it on the server. The key is **restricted**: it can only run `deploy.sh update` and `deploy.sh server-audit`. No shell, no port forwarding, no other commands. The output includes the private key and the exact values to paste:

| Kind | Name | Value |
|---|---|---|
| Secret | `DEPLOY_SSH_KEY` | the private key printed by `github-setup` (all lines) |
| Secret | `DEPLOY_KNOWN_HOSTS` | the host-key line printed by `github-setup` |
| Secret | `DEPLOY_HOST` | `92.4.91.207` |
| Secret | `DEPLOY_USER` | `root` |

The server IP the app uses comes from the server's own saved settings (Part 1.3 of `CUSTOM-DOMAINS.md`), so GitHub doesn't need it.

To revoke CI access, delete the key's line (`github-actions@…`) from `/root/.ssh/authorized_keys`, or run `github-setup` again after deleting `/root/.ssh/inventory_github_actions*` to issue a new key.

Optional but recommended: **Settings** → **Environments** → `production` → **Required reviewers**. Every deploy then waits for a named person to approve it.

### 2.2 Scan targets and gate

| Kind | Name | Example / default |
|---|---|---|
| Variable | `VAPT_TARGET_URLS` | `https://inspiresoft.online,https://company.inspiresoft.online` |
| Variable | `VAPT_FAIL_ON` | `CRITICAL,HIGH` (default) |
| Variable | `VAPT_OVERRIDE_USERS` | GitHub usernames allowed to override, e.g. `kuldeep-it007` |
| Variable | `VAPT_ALLOWED_PORTS` | `22,80,443` (default); any other open port is a Medium finding |
| Variable | `VAPT_ZAP_MAX_MINUTES` | `45` (default) |
| Variable | `VAPT_TRIVY_IGNORE_UNFIXED` | `true` (default); report only CVEs that have a fix |
| Variable | `VAPT_SERVER_AUDIT` | `true` (default). Set `false` to skip Lynis. The first `deploy.sh server-audit` installs the `lynis` package on the server. |

### 2.3 Report email (Mailgun)

| Kind | Name | Example |
|---|---|---|
| Secret | `MAILGUN_API_KEY` | your Mailgun private API key |
| Variable | `MAILGUN_DOMAIN` | `mg.inspiresoft.online` |
| Variable | `MAILGUN_ENDPOINT` | `api.mailgun.net` (`api.eu.mailgun.net` for EU accounts) |
| Variable | `VAPT_RECIPIENTS` | `security@yourco.com,cto@yourco.com` |
| Variable | `VAPT_FROM` | `VAPT <vapt@mg.inspiresoft.online>` |

### 2.4 Report archive (master bucket)

The same Oracle Object Storage bucket the app uses for uploads:

| Kind | Name | Value |
|---|---|---|
| Secret | `OCI_ACCESS_KEY_ID` | Customer Secret Key: access key |
| Secret | `OCI_SECRET_ACCESS_KEY` | Customer Secret Key: secret |
| Variable | `OCI_BUCKET` | bucket name |
| Variable | `OCI_REGION` | e.g. `ap-mumbai-1` |
| Variable | `OCI_ENDPOINT` | `https://<namespace>.compat.objectstorage.<region>.oraclecloud.com` |

If email or bucket settings are missing, those steps are skipped with a warning. Scanning and the gate still run.

---

## 3. The deployment gate

**Severity.** Every finding is classified as **Critical, High, Medium or Low**.

- ZAP rates findings High/Medium/Low, so it never produces a Critical.
- `composer audit` sometimes publishes an advisory with no severity. These are treated as **High**.

**Blocking.** The deploy is blocked by any Critical or High finding (`VAPT_FAIL_ON`) that has no valid exception. A scanner that did not run shows as **not run**, never as clean.

**Dev-only npm packages.** Findings in test tooling (vitest, vite, …) are reported but do not block. These packages are not in the production image.

**Exceptions.** Exceptions live in `security/vapt-exceptions.json` and need all four fields:

```json
{ "id": "npm:GHSA-xxxx-xxxx-xxxx:some-package",
  "reason": "Not reachable: we never call the affected function",
  "approved_by": "Kuldeep",
  "expires": "2027-02-15" }
```

- Copy `id` from `report.json`. End it with `*` to match a prefix.
- An entry that is expired or missing a field is ignored and listed under "invalid_or_expired" in the report.
- Make changes to this file reviewable, e.g. a `CODEOWNERS` line: `security/vapt-exceptions.json @kuldeep-it007`.

**Authorized override.** An override deploys once despite blocking findings:

1. Open **Actions** → **VAPT and deploy** → **Run workflow**.
2. Enter a reason in `override_reason`.
3. The override applies only if your GitHub username is listed in `VAPT_OVERRIDE_USERS`. The actor and reason are written into the report and the email. Anyone else is still blocked.

### Current state: the first run will be blocked (on purpose)

gitleaks scans the whole git history and found two items in the **initial commit** (`d8d07a2`):

| File | What | Action |
|---|---|---|
| `RUN.md` line 63 | an example admin password in a command | Replaced with a placeholder. If you ever used that exact password, change it. |
| `rosetta/.env.example` line 8 | an `AIRBRAKE_PROJECT_KEY` | `rosetta` was removed, but the key remains in history. **Rotate it** if it was real. |

Both are already listed in `security/vapt-exceptions.json` with `approved_by` left **empty**, so they do **not** count yet. To let deploys through:
- either put your name in `approved_by` after you've checked or rotated the values,
- or rewrite git history to remove them.

The decision is yours.

---

## 4. Production scanning: scope and limits

As agreed, ZAP runs an **active** scan against production after each deploy. To avoid real damage, `security/zap/production.context` excludes:

- `/api/sign-up` (would create real prospect sign-ups)
- `/api/forgot-password` (would send real emails)
- `/api/login` and `/api/platform/login` (brute force would lock accounts and flood the audit log)
- `/api/telescope*`, `/_next/static/*`, `/api/media/*`

Limits to be aware of:
- The scan is **unauthenticated**. Endpoints behind login are tested only for how they reject unauthenticated requests.
- Excluded endpoints are not attacked.
- The scan runs 2 threads per host and stops after `VAPT_ZAP_MAX_MINUTES`. Expect some extra load and 4xx entries in the logs while it runs.
- Post-deploy findings cannot "un-deploy". They mark the run failed and are reported and emailed.

---

## 5. Centralized logging and Telescope

### What is captured

| Source | Where it goes |
|---|---|
| Every API request (method, path, host, status, duration, user agent) | archive log (`http.request`) |
| All app logs and exceptions (`Log::…`, reported exceptions) | text log + archive log |
| Tenant audit events: login, failed login, logout, OTP, password reset, approvals, settings, integrations | tenant DB **and** archive log (`audit.*`) |
| Model changes (owen-it auditing) | tenant DB **and** archive log (`model.*`) |
| Platform-admin actions (plans, subscriptions, tenants, SSL renewal, log access) | central DB **and** archive log (`platform_audit.*`) |
| Background jobs: processed / failed | archive log (`job.processed` / `job.failed`) |
| Slow database queries (≥ `LOG_SLOW_QUERY_MS`, SQL only, no values) | archive log (`db.slow_query`) |
| Requests, exceptions, queries, jobs, mail, notifications, schedules, gates, outgoing HTTP | **Telescope** (live view) |

**Context on every archive line:** `ts` in **IST** (`+05:30`), `tenant_id`, `user_id`, `request_id`, `ip` and `event`.
- `request_id` is the `X-Request-Id` response header. It is also carried into queued jobs, so one request can be traced end to end.
- The app and database stay on UTC (changing that would shift existing data). Only log output uses IST.

**Masking.** Before anything is written, a masking step replaces passwords, tokens, `Authorization` headers, cookies, OTPs, API and secret keys, Sanctum tokens and private keys with `[MASKED]`. It applies to the text log, the archive and the audit exports. Telescope also hides those request fields, headers and response tokens.

### Telescope (Platform Admin only)

**Opening it.** Platform console → **Logs** → **Open Telescope**. The button opens a signed link that works once and only for 60 seconds. It starts a browser session on the platform domain only. Issuing and opening the link are both recorded in the platform audit log.

**Access control:**
- Telescope is not reachable on any tenant or custom domain: it returns 404 there.
- The `viewTelescope` gate allows only the `Platform_Admin` role.
- Tenants have no log routes at all.

**Storage.** Entries are stored in the **central** database, a Docker volume, so they survive restarts, deploys and rebuilds. Each entry is tagged `tenant:<id>` and `request:<id>`.

**Recording.** It records everything the enabled watchers see (`TELESCOPE_RECORD_ALL=true`). Watchers that only multiply volume are off by default: model hydration, cache hits, Redis commands, Blade views and dumps. Turn any of them on with `TELESCOPE_CACHE_WATCHER=true` and the like.

**Pruning.** Entries are pruned hourly after `TELESCOPE_PRUNE_HOURS` (72). Telescope is the live window; the archive is the long-term record.

---

## 6. Log archive in the master bucket

**Layout.** Daily, gzip-compressed JSON-lines files in the existing master (uploads) bucket:

```
tenant-logs/{tenant_id}/{tenant_name}/{YYYY}/{MM}/{DD}/app-{date}.jsonl.gz     application + access log
tenant-logs/{tenant_id}/{tenant_name}/{YYYY}/{MM}/{DD}/audit-{date}.jsonl.gz   that day's tenant audit rows
platform-logs/{YYYY}/{MM}/{DD}/app-{date}.jsonl.gz                             platform/system log
platform-logs/{YYYY}/{MM}/{DD}/audit-{date}.jsonl.gz                           platform-admin audit rows
vapt-reports/{YYYY}/{MM}/{DD}/{run}-{commit}/                                   VAPT reports (from CI)
```

`{tenant_name}` is the company name as a folder-safe slug (falls back to the subdomain). `{tenant_id}` is the stable key, so renaming a tenant never loses history.

**How it works**

- **Shipping.** `logs:archive` runs **hourly** in the existing scheduler container. It ships every *finished* IST day and exports the audit tables for recent days. It is idempotent, so each run also retries anything earlier runs could not upload.
- **Verification.** Each file is SHA-256 checksummed, uploaded, read back and verified. It is indexed in the central `log_archives` table, and only then deleted locally.
- **Failures.** A failed upload is retried 3 times with backoff. It is then kept locally for the next run, logged, recorded in the platform audit log (`logs.archive_failed`), and emailed to `LOG_ALERT_EMAIL` (or to all Platform_Admin accounts if unset).
- **No overwrites.** Objects are never overwritten. A differing file with the same name is stored beside the original.
- **Retention.** `LOG_ARCHIVE_RETENTION_DAYS` (default 400) can **never** be set below 365. `logs:prune` (daily 03:30 IST) deletes only older files and records each prune.
- **Immutable index.** The `log_archives` index is append-only: rows cannot be edited, and can be deleted only by the retention job.
- **Encryption.** In transit: HTTPS to Object Storage. At rest: OCI encrypts all objects (Oracle-managed keys by default; optionally your own Vault key on the bucket).
- **Durability.** Archives live in the bucket, so they survive container rebuilds, redeploys and moving to a new server. On a new server, point `UPLOADS_DISK`/`OCI_*` at the same bucket.

**Limit of using the shared master bucket (your choice).** OCI's tamper-proof *retention rules* apply to a whole bucket, which would also lock the logos and avatars stored there. So the 365-day protection is enforced by the application, not by OCI. Anyone holding the bucket's keys or console access could still delete objects. To harden further without a second bucket:

- Keep the bucket's access keys only on the server and in GitHub secrets.
- Optional safety net: OCI → bucket → **Lifecycle policy rules**, prefix `tenant-logs/` and `platform-logs/`: *Infrequent Access after 30 days*. Do **not** use the Archive tier (restores take hours).

If `UPLOADS_DISK` is not set to the OCI bucket, archives go to the local uploads disk (a Docker volume on the server). That survives rebuilds but not a server loss.

### Searching and exporting (Platform Admin)

Platform console → **Logs** → **Log archive**:

- **List files:** by date range (IST), scope, tenant and type, with a download for each file.
- **Search entries:** free text, event prefix (`audit.login`), user id, request id or level. Up to 31 days per search and 1,000 matches.
- **Export for audit (zip):** up to 93 days. The zip contains the original `.jsonl.gz` files and a `manifest.json` with each file's SHA-256 and whether it verified, so an external auditor can confirm nothing was altered.

Every search, download and export is recorded in the platform audit log (`logs.searched`, `logs.downloaded`, `logs.exported`) with the filters used.

---

## 7. Performance changes

All in `backend/` (image + entrypoint).

| Change | Effect |
|---|---|
| `PHP_CLI_SERVER_WORKERS=4` (Dockerfile) | `artisan serve` handles 4 requests in parallel instead of **1 at a time** |
| OPcache for the CLI server, timestamp checks off | PHP code compiled once per worker instead of every request |
| `php artisan optimize` at container start when `APP_ENV=production` | config, routes, events and views served from cache; never fatal |
| Telescope: noisy watchers off, hourly pruning | keeps "record everything" affordable |
| Archive writes: one append per log line, shipped hourly in the background | no request waits on the bucket |

After a deploy, a changed `.env` needs a container restart to apply, because config is cached. `deploy.sh update` already rebuilds and restarts.

### Dependency fixes made in this change

| Package | Before | After | Why |
|---|---|---|---|
| laravel/framework | 13.34.0 | 13.42.0 | security update batch |
| symfony/http-foundation, http-kernel, routing, mime | 7.4.x | 8.1.x | 8 advisories (email header injection, proxy trust, access-control bypasses) → `composer audit`: **0** |
| next | 14.2.35 | 15.5.29 | **unauthenticated remote code execution** in the image optimizer (GHSA-2464-fhr4-w9xw, GHSA-2xp9-vwfh-vxw4) and more |
| react / react-dom | 18.3.1 | 19.1.10 | required by Next 15 |
| @tanstack/react-query | 5.51.1 | 5.107.1 | React 19 support (same major) |
| postcss (inside Next) | 8.4.31 | 8.5.33 | arbitrary file read via source maps (npm `overrides`) |

Production npm dependencies: **0** known vulnerabilities (was 3 critical and 5 high). Dev-only test tooling still has advisories (vitest/vite); fixing them needs Vitest 5, which conflicts with the current test setup. It is reported and non-blocking.

---

## 8. Configuration reference

All optional; defaults shown. Change one with `deploy.sh config` (saved across updates; the app restarts to apply it):

```n/opt/inventory/deploy.sh config set LOG_ALERT_EMAIL ops@yourco.com
/opt/inventory/deploy.sh config list       # secrets are masked
/opt/inventory/deploy.sh config unset LOG_ALERT_EMAIL
```n
Settings that `deploy.sh` already writes (database, mail, storage…) are refused here: re-run `deploy.sh` and answer its prompt instead.

| Variable | Default | Meaning |
|---|---|---|
| `LOG_ARCHIVE_ENABLED` | `true` | write the archive log |
| `LOG_ARCHIVE_LEVEL` | `info` | archive log level (text log keeps `LOG_LEVEL`) |
| `LOG_ARCHIVE_RETENTION_DAYS` | `400` | retention, minimum 365 |
| `LOG_ARCHIVE_ACCESS_LOG` | `true` | one line per API request |
| `LOG_SLOW_QUERY_MS` | `500` | slow-query threshold (0 = off) |
| `LOG_ALERT_EMAIL` | (all Platform_Admins) | who gets failed-upload alerts |
| `TELESCOPE_ENABLED` | `true` | Telescope on/off |
| `TELESCOPE_RECORD_ALL` | `true` | `false` = only exceptions, failed requests/jobs, schedules |
| `TELESCOPE_PRUNE_HOURS` | `72` | live window |
| `TELESCOPE_PATH` | `api/telescope` | keep under `/api` so nginx routes it |
| `TELESCOPE_DB_CONNECTION` | `central` | never change to a tenant connection |
| `PHP_CLI_SERVER_WORKERS` | `4` | parallel request workers (image default; `.env` value wins) |
| `APP_OPTIMIZE` | (on in production) | `false` skips `php artisan optimize` at container start |

Useful commands:

```
/opt/inventory/deploy.sh archive-logs          # ship to the bucket now (also retries failures)
/opt/inventory/deploy.sh prune-logs            # apply retention now (never under 365 days)
/opt/inventory/deploy.sh prune-telescope 72    # trim Telescope to the last 72 hours
/opt/inventory/deploy.sh server-audit          # Lynis hardening report
/opt/inventory/deploy.sh artisan <command>     # any other Laravel command
```

---

## 9. Testing evidence

Run locally before delivery:

| Check | Result |
|---|---|
| Backend test suite | **365 passed**, 0 failed (258 skipped: need a live MySQL) |
| New logging/archive tests (`tests/Feature/LogArchiveTest.php`) | 18 passed: masking, X-Request-Id, IST per-tenant files, path-traversal guard, upload + verify, retry and alert on failure, audit export idempotency, retention + 365-day floor, append-only index, search, zip manifest checksum, 31-day limit, Platform_Admin-only routes, Telescope gate, platform-domain-only, one-time link reuse |
| Middleware order (HTTPS before tenancy) | restored and passing: `telescope:install` had re-sorted `bootstrap/providers.php` |
| Frontend | `tsc` 0 errors · ESLint clean · 27 tests passed · **production build on Next 15 succeeded** (31 pages) |
| `composer audit` | **No security vulnerability advisories found** |
| `npm audit --omit=dev` | **0** vulnerabilities |
| Workflow lint (`actionlint` + shellcheck) | no findings |
| Gate script on real output (composer, npm, gitleaks) + samples (trivy, zap, testssl, nmap, lynis) | pending exceptions → **BLOCKED** (exit 2); approved → **PASS**; expired → **BLOCKED** and listed; override by non-listed user → **BLOCKED**; by listed user → **OVERRIDDEN** (exit 0) |
| OPcache step in `php:8.5-cli` | `enable_cli=1`, `validate_timestamps=0`, 192 MB |
| `php artisan optimize` | config, routes (incl. closures), events, views all cache |

**Not run locally** (need GitHub runners or network access this machine didn't have): Semgrep, Trivy, ZAP, testssl, nmap and Lynis against real targets, the SSH deploy, email and the bucket upload. The first GitHub run is their live test. Check its job summary.

---

## 10. Rollback

| To undo | Do this |
|---|---|
| Automatic deploys | Disable the workflow (Actions → VAPT and deploy → ⋯ → Disable) and deploy by hand as before |
| Archive logging | `deploy.sh config set LOG_ARCHIVE_ENABLED false` (already-archived files stay) |
| Telescope | `deploy.sh config set TELESCOPE_ENABLED false` |
| Production caches | `deploy.sh config set APP_OPTIMIZE false` |
| Parallel workers | `deploy.sh config set PHP_CLI_SERVER_WORKERS 1` |
