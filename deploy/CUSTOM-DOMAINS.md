# Custom domains: step-by-step guide

Everything on the server is done with **one script**: `/opt/inventory/deploy.sh`.
Run it as `root` (or put `sudo` in front).

| Name | Value |
|---|---|
| Platform domain | `inspiresoft.online` |
| Server IP | `92.4.91.207` |
| Routing target for clients | `domains.inspiresoft.online` |
| Script | `/opt/inventory/deploy.sh` |

This guide has five parts:

1. **One-time server setup** (repeat only on a new server or a new IP)
2. **Command runbook**: get a client domain to HTTPS
3. **Connect a new client domain** (what the client does)
4. **Cloudflare for SaaS preparation** (optional, not used by the app yet)
5. **Troubleshooting**

---

## Part 1: One-time server setup

### 1.1 Find the server's real IP
```
curl -4 ifconfig.me
```
Currently `92.4.91.207`. Check every digit (`94` instead of `92` breaks every custom domain).
It must NOT start with `104.21.` or `172.67.`: those are Cloudflare addresses.

### 1.2 Cloudflare (`inspiresoft.online` → DNS → Records)
Both records **DNS only** (grey cloud):

| Type | Name | Content | Proxy status |
|---|---|---|---|
| A | `@` | `92.4.91.207` | DNS only |
| A | `*` | `92.4.91.207` | DNS only |

`*` also covers `domains.inspiresoft.online`. If you ever turn the proxy (orange cloud) on for `*`,
add a separate `A domains 92.4.91.207` record that stays **DNS only**.

### 1.3 Update the server and give the app the server IP

**First time after this change (once):** the copy at `/opt/inventory/deploy.sh` is an
older version that cannot update itself, so run the one in the checkout:
```
cd /opt/inventory
bash src/deploy/deploy.sh update
```

**From then on, always:**
```
SERVER_IP=92.4.91.207 /opt/inventory/deploy.sh update     # SERVER_IP only needed once
/opt/inventory/deploy.sh status
```
The IP is remembered, so later runs are just `/opt/inventory/deploy.sh update`.
If a pull changes `deploy.sh` itself, `update` finishes with the new version automatically.

---

## Part 2: Command runbook

Use this every time a client domain is not on **HTTPS on** yet. Replace `inspiresoft.store`
with the client's domain.

### Step 1: Check everything in one go
```
/opt/inventory/deploy.sh domain-check inspiresoft.store
```
This checks and reports:

- **A record:** must be only the server IP. Cloudflare IPs are flagged.
- **AAAA records:** there must be none.
- **TXT record:** `_inventory-verify` must exist.
- **HTTP:** `/.well-known/acme-challenge/` must reach **nginx on this server**.
- **The app's view:** status, routing, HTTPS state, attempts, renewal and last error.

Fix anything marked `!` in the client's DNS panel (Part 3), wait a few minutes, and run it again.

### Step 2: Let the app re-check DNS
In the app: **Settings** → **Custom domains** → **Check DNS now**.
Wait 20 seconds between clicks; faster clicks are ignored.

### Step 3: Get or renew the certificate (platform admin)
```
/opt/inventory/deploy.sh domain-renew inspiresoft.store
```
It queues a renewal, skipping any retry wait, then requests the certificate straight away and prints the result.
You can do the same from **Platform console** → **Domains** → **Renew SSL**.

- It refuses, with the reason, if the domain is not active or does not point at the server yet.
- Let's Encrypt allows only **5 certificates for the same domain per week**. Normal renewals happen automatically, so use this only when needed.
- Tenants can only **see** "renewal in progress"; they cannot renew.

### Step 4: Check
```
/opt/inventory/deploy.sh domain-status inspiresoft.store
curl -sI https://inspiresoft.store/up | head -n 1        # must be HTTP/... 200
```
In the app the domain shows **HTTPS on**.

### Other commands
```
/opt/inventory/deploy.sh domain-status          # all custom domains
/opt/inventory/deploy.sh sync-domains           # run the certificate step now (also every 2 min)
/opt/inventory/deploy.sh logs api               # follow app logs
/opt/inventory/deploy.sh help                   # every command
```

If `domain-renew` reports a certbot failure:
```
tail -n 40 /var/log/letsencrypt/letsencrypt.log
```

---

## Part 3: Connect a new client domain (what the client does)

1. Log in as **Company Admin** → **Settings** → **Custom domains**.
2. Type the domain, for example `app.clientdomain.com`, and click **Add domain**.
3. The page shows the records to add. Copy each value with the **Copy** button.
4. In the domain provider's DNS panel (Hostinger, GoDaddy, Namecheap, Cloudflare…), add:

   | Purpose | Type | Name / Host | Value |
   |---|---|---|---|
   | Prove owner | TXT | `_inventory-verify.app` | `inventory-verify=…` (from the app) |
   | Send traffic (subdomain) | CNAME | `app` | `domains.inspiresoft.online` |
   | Send traffic (root domain only) | A | `@` | `92.4.91.207` |

   Rules:
   - Enter only the short name (`app`, `_inventory-verify.app`, `@`). The panel adds the domain itself.
   - First delete any old A, AAAA or CNAME record with the same name.
   - A root domain (`@`) cannot have a CNAME; use the A record. Hostinger shows
     "ALIAS must not be used with A on the same name" if both exist.
   - If the domain's DNS is on Cloudflare, set the record to **DNS only** (grey cloud).
   - Want `www` too? Add `CNAME www → domains.inspiresoft.online` **and** add
     `www.clientdomain.com` in the app as a second domain.

5. Wait a few minutes and click **Check DNS now**. The status goes:
   **Waiting for DNS** → **Active** → **Issuing HTTPS certificate…** → **HTTPS on**.

The platform admin normally does nothing; checks, certificates and renewals are automatic.

- To stop a domain serving immediately: **Platform console** → **Domains** → **Disable**.
- To force a new certificate: `deploy.sh domain-renew <domain>` or **Renew SSL** in the console.

> A claim not verified within 72 hours is marked **Failed**. **Check DNS now** starts a new 72-hour window.

---

## Part 4: Cloudflare for SaaS preparation (optional, NOT used by the app yet)

The app does **not** use Cloudflare for SaaS yet. Do this only if you decide to move client
subdomains behind Cloudflare.

- Cost: 100 hostnames included, then $0.10 per hostname per month.
- Root domains (like `inspiresoft.store`) are not supported on non-Enterprise plans.

1. Cloudflare → **inspiresoft.online** → **SSL/TLS** → **Custom Hostnames** → enable **Cloudflare for SaaS**.
2. Add a **Proxied** (orange cloud) record: `A proxy-fallback 92.4.91.207`.
3. On **Custom Hostnames**, set **Fallback Origin** to `proxy-fallback.inspiresoft.online` and wait for **Active**.
4. Create an API token: **My Profile** → **API Tokens** → **Create Token** → **Custom token**
   - Permission: **Zone** → **SSL and Certificates** → **Edit**
   - Zone resources: **Include** → **Specific zone** → `inspiresoft.online`
5. Keep the token and the **Zone ID** (zone **Overview** page) safe. **Never** put them in code or chat.

---

## Part 5: Troubleshooting

| What you see | Cause | Fix |
|---|---|---|
| `/opt/inventory/deploy.sh` missing, or `update` fails with "are the same file" | Older installed copy | `cd /opt/inventory && bash src/deploy/deploy.sh update` once (Part 1.3) |
| `domain-check`: **A** shows the wrong IP | DNS points elsewhere, or a typo in the IP | Fix the A record; `SERVER_IP=<ip> /opt/inventory/deploy.sh update` if the app has the wrong one |
| `domain-check`: **A** shows `104.21.*` / `172.67.*` | Domain points at Cloudflare's proxy | Point it at the server IP and remove those records |
| `domain-check`: **AAAA** listed | IPv6 records send traffic elsewhere | Delete all AAAA records for that name |
| `domain-check`: **TXT** not found | Ownership record missing or wrong name | Copy it again from the app; enter only the short name |
| `domain-check`: **HTTP** not nginx | Traffic doesn't reach this server yet | Fix DNS, wait, run `domain-check` again |
| App: "No server address configured" | App has no server IP | Part 1.3 |
| Hostinger: "ALIAS must not be used with A" | A `CNAME @` already exists | Delete the `CNAME @`, then add `A @` |
| Stuck on "Issuing HTTPS certificate…" | Routing not passed, or retries used up | `domain-check`, then `domain-renew` |
| `domain-renew` says "already queued" | A renewal is waiting for the next run | Wait 2 minutes or run `sync-domains` |
| Domain opens with a 403 | Name not added in the app (e.g. `www`) or not **Active** | Add it in the app; **Check DNS now** |

Everyday commands:
```
/opt/inventory/deploy.sh status               # health, containers, certificate dates
/opt/inventory/deploy.sh domain-status        # every custom domain at a glance
/opt/inventory/deploy.sh logs api             # follow app logs
systemctl status inventory-domains.timer --no-pager   # 2-minute certificate timer
```
