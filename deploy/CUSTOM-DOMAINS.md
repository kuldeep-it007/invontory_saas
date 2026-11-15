# Custom domains: step-by-step guide

This guide has five parts:

1. **One-time server setup** (already done; repeat only on a new server or a new IP)
2. **Command runbook**: every command, in order, to get HTTPS on a client domain
3. **Connect a new client domain** (what the client does)
4. **Cloudflare for SaaS preparation** (optional, not used by the app yet)
5. **Troubleshooting**

Current values for this platform:

| Name | Value |
|---|---|
| Platform domain | `inspiresoft.online` |
| Server IP | `92.4.91.207` |
| Routing target for clients | `domains.inspiresoft.online` |
| Install folder on the server | `/opt/inventory` |

All server commands below are run as `root`. If you log in as another user, put `sudo` in front.

---

## Part 1: One-time server setup

### 1.1 Find the server's real IP
```
curl -4 ifconfig.me
```
This prints the server IP (currently `92.4.91.207`). Check it carefully: one wrong digit
(e.g. `94` instead of `92`) breaks every custom domain.

> It must NOT start with `104.21.` or `172.67.`. Those are Cloudflare addresses.

### 1.2 Cloudflare (`inspiresoft.online` → DNS → Records)
These records must exist and be **DNS only** (grey cloud):

| Type | Name | Content | Proxy status |
|---|---|---|---|
| A | `@` | `92.4.91.207` | DNS only |
| A | `*` | `92.4.91.207` | DNS only |

The `*` record also covers `domains.inspiresoft.online`. If you ever turn the proxy
(orange cloud) back on for `*`, add a separate `A domains 92.4.91.207` record that stays
**DNS only**, or client domains stop working.

### 1.3 Give the app the server IP (and install the certificate timer)
`deploy.sh` replaces its own files while it runs, so always run it from a copy:

```
cd /opt/inventory
git -C src log --oneline -1                      # shows the deployed commit
cp src/deploy/deploy.sh /root/deploy.sh
SERVER_IP=92.4.91.207 bash /root/deploy.sh update
```

Check:
```
grep CUSTOM_DOMAIN /opt/inventory/backend.env    # must show CUSTOM_DOMAIN_TARGET_IPS='92.4.91.207'
ls -l /opt/inventory/deploy.sh                   # must exist
systemctl status inventory-domains.timer --no-pager   # must say active (waiting)
```

> Run `update` again (same commands) after every `git push` you want on the server.
> If the first run used an old `deploy.sh`, run it a second time.

---

## Part 2: Command runbook (copy and paste in order)

Use this every time a client domain is not on **HTTPS on** yet.

### Step 0: Set the values and a shortcut (once per login)
Change `DOMAIN` to the client domain you are working on:

```
DOMAIN=inspiresoft.store
SERVER_IP=92.4.91.207
cd /opt/inventory
dc() { docker compose -p inventory --env-file /opt/inventory/compose.env -f /opt/inventory/docker-compose.prod.yml "$@"; }
```

`dc` is only a shortcut for the long `docker compose …` command. It lasts until you log out.

### Step 1: The app has the right server IP
```
grep CUSTOM_DOMAIN_TARGET_IPS backend.env
```
Must show `'92.4.91.207'`. If not, go back to step 1.3, then run:
```
dc restart api worker
```

### Step 2: The domain's DNS points at the server
```
dig +short A    $DOMAIN @1.1.1.1      # must print ONLY 92.4.91.207
dig +short AAAA $DOMAIN @1.1.1.1      # must print nothing
dig +short TXT  _inventory-verify.$DOMAIN @1.1.1.1   # must print the inventory-verify=... value from the app
```
If the first line shows `104.21.…` or `172.67.…`, the domain still points at Cloudflare.
Fix the records in the client's DNS panel (Part 3), wait, and repeat.

### Step 3: Requests reach nginx on this server
```
curl -sI http://$DOMAIN/.well-known/acme-challenge/test | head -n 3
```
Must show `404 Not Found` and `Server: nginx`. `Server: cloudflare` means DNS is still wrong (step 2).

### Step 4: Ask the app to re-check DNS
In the app: **Settings** → **Custom domains** → **Check DNS now** on the domain.
(Wait 20 seconds between clicks; faster clicks are ignored.)

Then see what the app knows about the domain:
```
dc exec -T -e D="$DOMAIN" api php artisan tinker --execute 'echo App\Models\Domain::where("domain",getenv("D"))->first(["domain","status","routing_ok","ssl_status","ssl_attempts","last_error"])?->toJson();'
```
You want `"status":"active"` and `"routing_ok":true`.

### Step 5: Renew / retry the certificate (platform admin only)
Failed attempts make the app wait hours before trying again. As platform admin, skip the wait:

**Platform console** → **Domains** → **Renew SSL** on the domain.

- The certificate is requested again within about 2 minutes (or right away with step 7).
- The button is greyed out while the domain does not point at the server, or while a renewal is already queued.
- Tenants only **see** "renewal in progress"; they cannot renew.
- Let's Encrypt allows only **5 renewals of the same domain per week**. Use it only when needed.
  Normal renewals before expiry happen automatically; you never need this button for those.

If you cannot use the console, the same reset from the server:```
dc exec -T -e D="$DOMAIN" api php artisan tinker --execute 'echo App\Models\Domain::where("domain",getenv("D"))->update(["ssl_status"=>"pending","ssl_attempts"=>0,"ssl_checked_at"=>null]);'
```
It prints `1` when the domain was found and reset.

### Step 6: Confirm the app wants a certificate
```
dc exec -T api php artisan domains:ssl-list
```
Must include `ISSUE <your domain>`, or `RENEW <your domain>` after **Renew SSL**. If you only see `KEEP` lines and `END`, routing has not
passed: repeat step 4.

### Step 7: Get the certificate now
```
/opt/inventory/deploy.sh sync-domains
```
Should print `requesting certificate for <your domain>` (or `renewing certificate for <your domain>` after **Renew SSL**). (The timer also does this every 2 minutes.)

### Step 8: Check HTTPS
```
curl -sI https://$DOMAIN/up | head -n 1          # must show HTTP/... 200
certbot certificates 2>/dev/null | grep -A3 "$DOMAIN"
```
Refresh the app: the domain should show **HTTPS on**.

### If step 7 printed a warning
```
tail -n 40 /var/log/letsencrypt/letsencrypt.log
ls /etc/nginx/custom-domains/
nginx -t
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
   - A root domain (`@`) cannot have a CNAME. Use the A record. (Hostinger shows
     "ALIAS must not be used with A on the same name" if both exist.)
   - If the domain's DNS is on Cloudflare, set the record to **DNS only** (grey cloud).
   - Want `www` too? Add `CNAME www → domains.inspiresoft.online` **and** add
     `www.clientdomain.com` in the app as a second domain.

5. Wait a few minutes and click **Check DNS now**. The status goes:
   **Waiting for DNS** → **Active** → **Issuing HTTPS certificate…** → **HTTPS on**.

The platform admin normally does nothing: checks, certificates and renewals are automatic.
- To stop a domain serving immediately: **Platform console** → **Domains** → **Disable**.
- To force a new certificate: **Platform console** → **Domains** → **Renew SSL** (clients cannot do this).

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
| `sudo: deploy/deploy.sh: command not found` | Wrong folder, or file not executable | Use step 1.3 (`bash /root/deploy.sh …`) |
| App shows an A record with the wrong IP | Wrong `SERVER_IP` given to `deploy.sh` | Step 1.3 with the right IP, then `dc restart api worker` |
| "Your domain does not point at our server yet" | DNS IP ≠ app's server IP, or DNS points at Cloudflare | Runbook steps 1 and 2 |
| "No server address configured" | `CUSTOM_DOMAIN_TARGET_IPS` is empty | Step 1.3 |
| Domain stays **Waiting for DNS** | TXT record missing or value wrong | Copy the TXT value again; enter only the short name |
| Hostinger: "ALIAS must not be used with A" | A `CNAME @` already exists | Delete the `CNAME @`, then add `A @` |
| Stuck on "Issuing HTTPS certificate…" | Routing not passed, or retries used up | Runbook steps 4 to 7 (platform admin: **Renew SSL**) |
| Certificate problem on a working domain | Needs a fresh certificate | Platform console → Domains → **Renew SSL** |
| **Renew SSL** says "already queued" | A renewal is waiting for the next run | Wait 2 minutes, or run step 7 |
| `curl` shows `Server: cloudflare` | DNS still points at Cloudflare IPs | Remove `104.21.` / `172.67.` A and all AAAA records |
| Domain opens with a 403 | Name not added in the app (e.g. `www`) or not **Active** | Add it in the app; click **Check DNS now** |
| Browser certificate warning | Certificate not issued yet | Runbook step 7 |

Everyday server commands:
```
/opt/inventory/deploy.sh status          # health + certificate dates
/opt/inventory/deploy.sh sync-domains    # issue/renew/remove domain certificates now
/opt/inventory/deploy.sh logs api        # app logs
systemctl status inventory-domains.timer --no-pager   # 2-minute certificate timer
```
