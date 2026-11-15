# Custom domains: step-by-step guide

This guide has four parts:

1. **Fix `inspiresoft.store` now** (one time)
2. **Connect a new client domain** (every time)
3. **Cloudflare for SaaS preparation** (optional, not used by the app yet)
4. **Troubleshooting**

Replace `<SERVER-IP>` everywhere with your server's real public IP (step 1.1).

---

## Part 1: Fix `inspiresoft.store` now

### 1.1 Find your server's real IP
Log in to the Ubuntu server and run:

```
curl -4 ifconfig.me
```

Write the result down. This is `<SERVER-IP>`.

> It must NOT start with `104.21.` or `172.67.`. Those are Cloudflare addresses,
> and pointing a domain at them does not reach your server.

### 1.2 Cloudflare: make `domains.inspiresoft.online` point straight at the server
1. Open the Cloudflare dashboard, select **inspiresoft.online**, then **DNS** → **Records**.
2. Add a record (or edit it if `domains` already exists):

   | Type | Name      | Content       | Proxy status                |
   |------|-----------|---------------|-----------------------------|
   | A    | `domains` | `<SERVER-IP>` | **DNS only** (grey cloud)   |

3. Do **not** change your existing `*` and `@` records.

### 1.3 Tell the app your server IP
On the server, in the folder that contains `deploy.sh`:

```
sudo SERVER_IP=<SERVER-IP> ./deploy.sh update
```

Check that it worked:

```
sudo grep CUSTOM_DOMAIN /opt/inventory/backend.env
```

You should see `CUSTOM_DOMAIN_TARGET_IPS='<SERVER-IP>'`.

### 1.4 Hostinger: fix the records for `inspiresoft.store`
Open Hostinger hPanel → **Domains** → **inspiresoft.store** → **DNS / Nameservers**.

1. **Delete** these records if they exist:
   - `CNAME  @` (it causes the "ALIAS must not be used with A" error)
   - every `A  @` that points to `104.21.88.254` or `172.67.155.51`
   - every `AAAA  @` record
2. **Keep** the TXT record `_inventory-verify`.
3. **Add**:

   | Type | Name | Points to     | TTL |
   |------|------|---------------|-----|
   | A    | `@`  | `<SERVER-IP>` | 300 |

4. For `www`, choose one:
   - **Use it:** change it to `CNAME  www  →  domains.inspiresoft.online`, then add `www.inspiresoft.store` in the app as a second domain.
   - **Don't need it:** delete the `www` record.

### 1.5 Wait for DNS, then check
DNS can take 5 to 30 minutes, sometimes longer. From any computer:

```
nslookup inspiresoft.store 1.1.1.1
```

It must show **only** `<SERVER-IP>`.

On the server, confirm the request reaches nginx (not Cloudflare):

```
curl -I http://inspiresoft.store/.well-known/acme-challenge/test
```

Expected: `HTTP/1.1 404 Not Found` with `Server: nginx`. If it says `Server: cloudflare`, step 1.4 is not done or DNS has not updated yet.

### 1.6 Get the HTTPS certificate
1. In the app, open **Settings** → **Custom domains** and click **Check DNS now**.
2. On the server, run the certificate step right away (it also runs automatically every 2 minutes):

   ```
   sudo /opt/inventory/deploy.sh sync-domains
   ```

3. Refresh the app. The domain should show **HTTPS on**.
4. Open `https://inspiresoft.store` in a browser.

### 1.7 If it still says "Issuing HTTPS certificate…"
Earlier attempts may have failed and used up the retries. Reset them on the server:

```
cd /opt/inventory
sudo docker compose -p inventory --env-file compose.env -f docker-compose.prod.yml exec api \
  php artisan tinker --execute 'App\Models\Domain::where("domain","inspiresoft.store")->update(["ssl_status"=>"pending","ssl_attempts"=>0,"ssl_checked_at"=>null]);'
```

Then repeat step 1.6.

---

## Part 2: Connect a new client domain (every time)

### What the client does
1. Log in as **Company Admin** → **Settings** → **Custom domains**.
2. Type the domain, for example `app.clientdomain.com`, and click **Add domain**.
3. The page shows the records to add. Copy each value with the **Copy** button.
4. In their domain provider's DNS panel, add:

   | Purpose        | Type  | Name / Host                   | Value                                    |
   |----------------|-------|-------------------------------|------------------------------------------|
   | Prove owner    | TXT   | `_inventory-verify.app`       | `inventory-verify=…` (shown in the app)  |
   | Send traffic   | CNAME | `app`                         | `domains.inspiresoft.online`             |

   For a **root domain** (`clientdomain.com`), use `A  @  <SERVER-IP>` instead of the CNAME.

   Tips:
   - Most panels add the domain automatically, so enter only the short name (`app`, `_inventory-verify.app`).
   - Remove any old A, AAAA or CNAME record with the same name first.
   - On Cloudflare-managed client domains, set the record to **DNS only** (grey cloud).

5. Wait a few minutes and click **Check DNS now**. The status goes:
   **Waiting for DNS** → **Active** → **Issuing HTTPS certificate…** → **HTTPS on**.

### What you (platform admin) do
Nothing. Verification and HTTPS are automatic. To stop a domain serving
immediately, use **Platform console** → **Domains** → **Disable**.

> A claim that is not verified within 72 hours is marked **Failed**. Clicking
> **Check DNS now** starts a new 72-hour window.

---

## Part 3: Cloudflare for SaaS preparation (optional, NOT used by the app yet)

The app does **not** use Cloudflare for SaaS yet. Do this part only if you
approve that change. It lets client subdomains go through Cloudflare (with
Cloudflare-issued HTTPS) instead of straight to the server.

- Cost: 100 hostnames included, then $0.10 per hostname per month.
- Root domains (like `inspiresoft.store`) are not supported on non-Enterprise plans.

1. Cloudflare → **inspiresoft.online** → **SSL/TLS** → **Custom Hostnames** → enable **Cloudflare for SaaS**.
2. Add a DNS record and keep it **Proxied** (orange cloud):

   | Type | Name             | Content       | Proxy status |
   |------|------------------|---------------|--------------|
   | A    | `proxy-fallback` | `<SERVER-IP>` | Proxied      |

3. On the **Custom Hostnames** page, set **Fallback Origin** to `proxy-fallback.inspiresoft.online` and wait for **Active**.
4. Create an API token: **My Profile** → **API Tokens** → **Create Token** → **Custom token**:
   - Permission: **Zone** → **SSL and Certificates** → **Edit**
   - Zone resources: **Include** → **Specific zone** → `inspiresoft.online`
5. Copy the token and the **Zone ID** (shown on the zone's **Overview** page). Keep them for later.
   **Never** put them in code or send them in chat. They will go into the server's
   environment file when the feature is built.

---

## Part 4: Troubleshooting

| What you see | Cause | Fix |
|---|---|---|
| "ALIAS must not be used with A on the same name" (Hostinger) | A `CNAME @` already exists | Delete the `CNAME @`, then add `A @` |
| Domain stays **Waiting for DNS** | TXT record missing or value wrong | Copy the TXT value again from the app; enter only the short name |
| "Routing record not found" | Domain doesn't point at the server | Step 1.4 (`A @ <SERVER-IP>`) or the CNAME in Part 2 |
| Stuck on "Issuing HTTPS certificate…" | Domain points at Cloudflare, or retries used up | Check step 1.5, then step 1.7 |
| `curl` shows `Server: cloudflare` | DNS still points at Cloudflare IPs | Remove the `104.21.` / `172.67.` and AAAA records |
| Browser shows a certificate warning | Certificate not issued yet | Run `sudo /opt/inventory/deploy.sh sync-domains` |
| Domain opens but shows a 403 | Domain not **Active** in the app | Click **Check DNS now**; check it isn't **Disabled** |

Useful server commands:

```
sudo /opt/inventory/deploy.sh status          # health + certificate dates
sudo /opt/inventory/deploy.sh sync-domains    # issue/renew/remove domain certificates now
sudo /opt/inventory/deploy.sh logs api        # app logs
systemctl status inventory-domains.timer      # is the 2-minute certificate timer running?
```
