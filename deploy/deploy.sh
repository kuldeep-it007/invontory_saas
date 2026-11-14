#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Inventory & Distribution Platform — Ubuntu deployment
#
# One script that takes a fresh Ubuntu 22.04 / 24.04 VPS to a running,
# HTTPS-secured production install:
#
#   https://inspiresoft.online          platform console + login (central app)
#   https://<tenant>.inspiresoft.online one workspace per tenant (multi-tenant)
#
# What it installs and configures (all idempotent, safe to re-run):
#   • OS packages, swap (small servers), UFW firewall, fail2ban
#   • Docker Engine + Compose plugin
#   • The stack (docker compose): MySQL 8, Redis, Laravel API, queue worker,
#     scheduler (daily DB backups), Next.js frontend — bound to 127.0.0.1 only
#   • Host nginx as the single public entry point + Let's Encrypt TLS
#     (wildcard certificate, auto-renewing)
#   • Generated secrets, first Platform Admin account
#
# USAGE (as root, or with sudo):
#   sudo ./deploy.sh              # install / re-run everything (default)
#   sudo ./deploy.sh update       # pull latest code, rebuild, migrate, restart
#   sudo ./deploy.sh status       # health, containers, certificate expiry
#   sudo ./deploy.sh logs [svc]   # follow logs (api worker scheduler frontend mysql redis)
#   sudo ./deploy.sh backup       # run a database backup now
#   sudo ./deploy.sh add-host sub1 [sub2 ...]   # TLS_MODE=http only: add tenant certs
#   sudo ./deploy.sh sync-domains # issue/renew/remove HTTPS certs for tenants' custom domains
#                                 (also runs every 2 minutes from a systemd timer)
#
# NON-INTERACTIVE: export the variables below instead of answering prompts, e.g.
#   REPO_URL=git@github.com:me/inventory.git TLS_MODE=cloudflare \
#   CF_API_TOKEN=xxxx LE_EMAIL=me@example.com sudo -E ./deploy.sh
#
# VARIABLES (all optional; asked for when missing and a terminal is attached)
#   DOMAIN         apex domain                         (default inspiresoft.online)
#   REPO_URL       git URL: https://… or git@…         (required)
#   BRANCH         branch to deploy                    (default: repo's default)
#   GIT_TOKEN      token for a private https repo      (optional)
#   TLS_MODE       cloudflare | manual | http          (see below)
#   CF_API_TOKEN   Cloudflare API token (Zone:DNS:Edit) for TLS_MODE=cloudflare
#   LE_EMAIL       email for Let's Encrypt expiry notices
#   ADMIN_EMAIL    first Platform Admin login          (default admin@DOMAIN)
#   ADMIN_NAME     its display name                    (default "Platform Admin")
#   MAILGUN_DOMAIN MAILGUN_SECRET MAILGUN_ENDPOINT MAILGUN_FROM   outgoing mail via Mailgun
#                  (MAILGUN_DOMAIN = verified sending domain; ENDPOINT api.eu.mailgun.net for EU accounts)
#   SMTP_HOST SMTP_PORT SMTP_USER SMTP_PASSWORD SMTP_FROM   or plain SMTP instead (blank = log only)
#   OCI_BUCKET OCI_NAMESPACE OCI_REGION OCI_ACCESS_KEY_ID OCI_SECRET_ACCESS_KEY
#                  optional Oracle Object Storage for uploads (blank bucket = local disk)
#   APP_DIR        install directory                   (default /opt/inventory)
#   MYSQL_LOCAL_PORT  server-loopback port MySQL is published on, for SSH-tunnelled
#                  clients such as SQLyog               (default 3307)
#
# DNS / TLS — read this once
#   Tenants live on subdomains, so you need DNS records pointing at this server:
#       A   @   <server IP>        (inspiresoft.online)
#       A   *   <server IP>        (every tenant subdomain)
#   For HTTPS on *every* tenant subdomain you need a WILDCARD certificate, and
#   Let's Encrypt only issues those via a DNS challenge:
#     TLS_MODE=cloudflare  RECOMMENDED. Keep the domain registered at Hostinger but
#                          point its nameservers to a free Cloudflare account;
#                          the script then issues and auto-renews the wildcard.
#     TLS_MODE=manual      Stay on Hostinger DNS. You add a TXT record by hand at
#                          install time and again at every renewal (~60 days).
#     TLS_MODE=http        Hostinger DNS, fully automatic, but one certificate
#                          name per tenant: run `deploy.sh add-host <sub>` after
#                          creating each tenant. Apex + www only until then.
# ─────────────────────────────────────────────────────────────────────────────
set -Eeuo pipefail

# ── Configuration ────────────────────────────────────────────────────────────
DOMAIN="${DOMAIN:-}"          # defaulted + remembered in preflight()
APP_DIR="${APP_DIR:-/opt/inventory}"
PROJECT="inventory"

SRC_DIR="$APP_DIR/src"
STATE_FILE="$APP_DIR/secrets.env"          # generated secrets + saved answers (0600)
BACKEND_ENV="$APP_DIR/backend.env"         # mounted into the containers as /app/.env
COMPOSE_FILE="$APP_DIR/docker-compose.prod.yml"
COMPOSE_ENV="$APP_DIR/compose.env"
MYSQL_INIT_DIR="$APP_DIR/mysql-init"
CERT_HOSTS_FILE="$APP_DIR/cert-hosts"      # names on the cert when TLS_MODE=http
NGINX_CONF="/etc/nginx/sites-available/inventory.conf"
ACME_ROOT="/var/www/certbot"
DEPLOY_KEY="/root/.ssh/inventory_deploy"

# ── Output helpers ───────────────────────────────────────────────────────────
if [ -t 1 ]; then C_B=$'\033[1m'; C_G=$'\033[32m'; C_Y=$'\033[33m'; C_R=$'\033[31m'; C_0=$'\033[0m'; else C_B=; C_G=; C_Y=; C_R=; C_0=; fi
step() { printf '\n%s==>%s %s%s%s\n' "$C_G" "$C_0" "$C_B" "$*" "$C_0"; }
log()  { printf '    %s\n' "$*"; }
warn() { printf '%s    ! %s%s\n' "$C_Y" "$*" "$C_0" >&2; }
die()  { printf '%s\nERROR: %s%s\n' "$C_R" "$*" "$C_0" >&2; exit 1; }
trap 'die "failed at line $LINENO (command: $BASH_COMMAND)"' ERR

# ── State (saved answers + generated secrets) ────────────────────────────────
load_state() {
  [ -f "$STATE_FILE" ] || return 0
  local line k v
  while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in ''|\#*) continue ;; esac
    k="${line%%=*}"; v="${line#*=}"; v="${v%\"}"; v="${v#\"}"
    # Real environment variables win over saved values.
    if [ -z "${!k:-}" ]; then printf -v "$k" '%s' "$v"; fi
  done < "$STATE_FILE"
}
set_state() {
  local k="$1" v="$2"
  mkdir -p "$APP_DIR"; touch "$STATE_FILE"; chmod 600 "$STATE_FILE"
  { grep -v "^${k}=" "$STATE_FILE" || true; printf '%s="%s"\n' "$k" "$v"; } > "$STATE_FILE.tmp"
  mv "$STATE_FILE.tmp" "$STATE_FILE"; chmod 600 "$STATE_FILE"
}
# Ask for VAR unless already set; remember the answer. ask VAR "Prompt" [default] [secret]
ask() {
  local var="$1" prompt="$2" def="${3:-}" secret="${4:-}" val=""
  if [ -n "${!var:-}" ]; then return 0; fi
  if [ -t 0 ]; then
    if [ -n "$secret" ]; then read -r -s -p "    $prompt: " val; echo
    else read -r -p "    $prompt${def:+ [$def]}: " val; fi
  fi
  val="${val:-$def}"
  [ -n "$val" ] || die "$var is required (export it for non-interactive runs)"
  printf -v "$var" '%s' "$val"
  [ -n "$secret" ] || set_state "$var" "$val"
}
# Like ask, but an empty answer is allowed.
ask_opt() {
  local var="$1" prompt="$2" secret="${3:-}" val=""
  if [ -n "${!var:-}" ]; then return 0; fi
  if [ -t 0 ]; then
    if [ -n "$secret" ]; then read -r -s -p "    $prompt (optional): " val; echo
    else read -r -p "    $prompt (optional): " val; fi
  fi
  printf -v "$var" '%s' "$val"
  if [ -n "$val" ] && [ -z "$secret" ]; then set_state "$var" "$val"; fi
  if [ -n "$val" ] && [ -n "$secret" ]; then set_state "$var" "$val"; fi
}
gen_hex() { openssl rand -hex "$1"; }
ensure_secret() { # ensure_secret NAME "generated value"
  local name="$1" value="$2"
  if [ -z "${!name:-}" ]; then printf -v "$name" '%s' "$value"; set_state "$name" "$value"; fi
}

compose() { docker compose -p "$PROJECT" --env-file "$COMPOSE_ENV" -f "$COMPOSE_FILE" "$@"; }

# Single-quote a value for a .env file (literal; no $-interpolation).
env_quote() {
  case "$1" in *"'"*|*$'\n'*) die "value contains a single quote or newline, which is not supported: $1" ;; esac
  printf "'%s'" "$1"
}

# ── 0. Preflight ─────────────────────────────────────────────────────────────
preflight() {
  step "Preflight checks"
  [ "$(id -u)" -eq 0 ] || die "run as root: sudo $0"
  # shellcheck disable=SC1091
  . /etc/os-release
  [ "${ID:-}" = "ubuntu" ] || warn "tested on Ubuntu; detected ${PRETTY_NAME:-unknown}"
  case "$(uname -m)" in x86_64|aarch64) ;; *) die "unsupported CPU architecture $(uname -m)" ;; esac
  local mem_mb disk_gb
  mem_mb=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
  disk_gb=$(df -BG --output=avail / | tail -1 | tr -dc '0-9')
  log "RAM ${mem_mb} MB, free disk ${disk_gb} GB, ${PRETTY_NAME:-Linux}"
  [ "$disk_gb" -ge 10 ] || die "need at least 10 GB free disk space"
  [ "$mem_mb" -ge 1500 ] || warn "under 2 GB RAM: builds will be slow; swap will be added"
  mkdir -p "$APP_DIR"; chmod 755 "$APP_DIR"
  load_state
  # Remember the domain so later runs (update/status) don't need it repeated.
  DOMAIN="${DOMAIN:-inspiresoft.online}"
  set_state DOMAIN "$DOMAIN"
  log "domain: $DOMAIN"
}

# ── 1. System packages ───────────────────────────────────────────────────────
install_packages() {
  step "Installing system packages"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y --no-install-recommends \
    ca-certificates curl gnupg git openssl ufw fail2ban dnsutils nginx \
    certbot python3-certbot-dns-cloudflare
  systemctl enable --now fail2ban >/dev/null 2>&1 || true
  # The distro "default" site would shadow ours.
  rm -f /etc/nginx/sites-enabled/default
}

install_docker() {
  if docker compose version >/dev/null 2>&1; then
    step "Docker already installed ($(docker --version))"; return
  fi
  step "Installing Docker Engine + Compose plugin"
  # shellcheck disable=SC1091
  . /etc/os-release
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${UBUNTU_CODENAME:-$VERSION_CODENAME} stable" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update -y
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  systemctl enable --now docker
  docker compose version >/dev/null
}

setup_swap() {
  local mem_mb; mem_mb=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
  if [ "$mem_mb" -ge 3900 ] || [ -n "$(swapon --show --noheadings)" ]; then return; fi
  step "Adding 2 GB swap (small server: Next.js/Composer builds need headroom)"
  fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
  chmod 600 /swapfile; mkswap /swapfile >/dev/null; swapon /swapfile
  grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
}

setup_firewall() {
  step "Configuring firewall (UFW): SSH, HTTP, HTTPS only"
  local ssh_port
  ssh_port=$(grep -hE '^\s*Port\s+[0-9]+' /etc/ssh/sshd_config /etc/ssh/sshd_config.d/*.conf 2>/dev/null | awk '{print $2}' | head -1 || true)
  ssh_port="${ssh_port:-22}"
  ufw allow 22/tcp >/dev/null
  [ "$ssh_port" = "22" ] || ufw allow "${ssh_port}/tcp" >/dev/null
  ufw allow 80/tcp >/dev/null
  ufw allow 443/tcp >/dev/null
  ufw --force enable >/dev/null
  log "open: ssh(${ssh_port}), 80, 443. App ports are bound to 127.0.0.1 and not exposed."
  log "If your host has a separate cloud firewall (Hostinger hPanel -> VPS -> Firewall), allow 80 and 443 there too."
}

# ── 2. Source code ───────────────────────────────────────────────────────────
git_env() { GIT_SSH_COMMAND="ssh -i $DEPLOY_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new" GIT_TERMINAL_PROMPT=0 "$@"; }

fetch_code() {
  step "Fetching application code"
  ask REPO_URL "Git repository URL (https://… or git@…)"
  [ -z "${GIT_TOKEN:-}" ] || set_state GIT_TOKEN "$GIT_TOKEN"   # needed again on `update`
  if [ -n "${GIT_TOKEN:-}" ] && [[ "$REPO_URL" == https://* ]]; then
    REPO_URL="https://x-access-token:${GIT_TOKEN}@${REPO_URL#https://}"
  fi

  if [[ "$REPO_URL" == git@* || "$REPO_URL" == ssh://* ]]; then
    mkdir -p /root/.ssh; chmod 700 /root/.ssh
    if [ ! -f "$DEPLOY_KEY" ]; then
      ssh-keygen -t ed25519 -N '' -C "inventory-deploy@$(hostname)" -f "$DEPLOY_KEY" >/dev/null
    fi
    until git_env git ls-remote "$REPO_URL" HEAD >/dev/null 2>&1; do
      echo
      warn "Cannot read the repository yet. Add this DEPLOY KEY (read-only) to the repo:"
      echo "      GitHub: repo -> Settings -> Deploy keys -> Add deploy key"
      echo
      cat "$DEPLOY_KEY.pub"
      echo
      [ -t 0 ] || die "repository not readable and no terminal to wait on"
      read -r -p "    Press Enter once the key is added (Ctrl+C to abort) " _
    done
  else
    GIT_TERMINAL_PROMPT=0 git ls-remote "$REPO_URL" HEAD >/dev/null 2>&1 \
      || die "cannot read $REPO_URL. For a private repo use an SSH URL (deploy key) or export GIT_TOKEN."
  fi

  if [ -z "${BRANCH:-}" ]; then
    BRANCH=$(git_env git ls-remote --symref "$REPO_URL" HEAD 2>/dev/null | awk '/^ref:/ {sub("refs/heads/","",$2); print $2; exit}')
    BRANCH="${BRANCH:-main}"
  fi
  set_state BRANCH "$BRANCH"

  if [ -d "$SRC_DIR/.git" ]; then
    git -C "$SRC_DIR" remote set-url origin "$REPO_URL"
    git_env git -C "$SRC_DIR" fetch --depth 1 origin "$BRANCH"
    git -C "$SRC_DIR" reset --hard FETCH_HEAD
    git -C "$SRC_DIR" clean -fd
  else
    rm -rf "$SRC_DIR"
    git_env git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$SRC_DIR"
  fi
  chmod 700 "$SRC_DIR/.git" 2>/dev/null || true   # may contain an embedded token
  log "deployed commit $(git -C "$SRC_DIR" rev-parse --short HEAD) on branch $BRANCH"
}

verify_app_patches() {
  step "Verifying the repository contains the production-ready changes"
  local missing=0
  grep -q "CENTRAL_DOMAINS" "$SRC_DIR/backend/config/tenancy.php"  || { warn "backend/config/tenancy.php lacks CENTRAL_DOMAINS support"; missing=1; }
  grep -q "trustProxies"    "$SRC_DIR/backend/bootstrap/app.php"   || { warn "backend/bootstrap/app.php lacks trustProxies()"; missing=1; }
  grep -q "API_BASE_URL"    "$SRC_DIR/frontend/src/lib/branding/client.ts" || { warn "frontend branding client lacks server-side API_BASE_URL"; missing=1; }
  [ "$missing" -eq 0 ] || die "commit and push those changes to '$BRANCH', then re-run."
  [ -f "$SRC_DIR/backend/Dockerfile" ] && [ -f "$SRC_DIR/frontend/Dockerfile" ] || die "backend/ and frontend/ Dockerfiles not found in the repo"
}

# ── 3. Secrets + runtime files ───────────────────────────────────────────────
prepare_secrets() {
  step "Preparing configuration and secrets"
  ensure_secret DB_ROOT_PASSWORD "$(gen_hex 24)"
  ensure_secret DB_APP_PASSWORD  "$(gen_hex 24)"
  ensure_secret REDIS_PASSWORD   "$(gen_hex 24)"
  # NEVER regenerate APP_KEY on a live system: it would invalidate encrypted data.
  ensure_secret APP_KEY          "base64:$(openssl rand -base64 32)"
  ensure_secret ADMIN_PASSWORD   "$(gen_hex 10)"
  ask ADMIN_EMAIL "First Platform Admin email" "admin@${DOMAIN}"
  ask ADMIN_NAME  "First Platform Admin name"  "Platform Admin"
  # Outgoing mail: Mailgun (preferred) or plain SMTP; neither = log only.
  ask_opt MAILGUN_DOMAIN "Mailgun sending domain, e.g. mg.${DOMAIN} (blank = use SMTP / log only)"
  if [ -n "${MAILGUN_DOMAIN:-}" ]; then
    ask MAILGUN_SECRET "Mailgun private API key" "" secret
    # The key is kept in the 0600 state file so re-runs and 'update' don't lose it.
    set_state MAILGUN_SECRET "$MAILGUN_SECRET"
    ask MAILGUN_ENDPOINT "Mailgun API endpoint (api.eu.mailgun.net for EU accounts)" "api.mailgun.net"
    ask MAILGUN_FROM "From address (must belong to the sending domain)" "no-reply@${MAILGUN_DOMAIN}"
  else
    ask_opt SMTP_HOST "SMTP host for outgoing email (blank = log only)"
    if [ -n "${SMTP_HOST:-}" ]; then
      ask SMTP_PORT "SMTP port" "587"
      ask_opt SMTP_USER "SMTP username"
      ask_opt SMTP_PASSWORD "SMTP password" secret
      ask SMTP_FROM "From address" "no-reply@${DOMAIN}"
    fi
  fi
  # Uploaded files (logo, favicon, profile pictures). Blank = local disk in the
  # app-storage volume. The bucket must already exist and should stay PRIVATE:
  # the app streams files itself via /api/media/...
  ask_opt OCI_BUCKET "Oracle Object Storage bucket for uploads (blank = local disk)"
  if [ -n "${OCI_BUCKET:-}" ]; then
    ask OCI_NAMESPACE "Object Storage namespace (Bucket details / Tenancy details)"
    ask OCI_REGION    "OCI region identifier, e.g. ap-mumbai-1"
    ask OCI_ACCESS_KEY_ID "Customer Secret Key: access key"
    # ask_opt (not ask) so the secret is remembered in secrets.env for 'update'.
    ask_opt OCI_SECRET_ACCESS_KEY "Customer Secret Key: secret" secret
    [ -n "${OCI_SECRET_ACCESS_KEY:-}" ] || die "OCI_SECRET_ACCESS_KEY is required when OCI_BUCKET is set"
  fi
}

write_runtime_files() {
  # Public IPv4 shown to customers as the A-record target for root domains.
  if [ -z "${SERVER_IP:-}" ]; then
    SERVER_IP=$(curl -4 -fsS --max-time 8 https://api.ipify.org 2>/dev/null || true)
    [ -z "$SERVER_IP" ] || set_state SERVER_IP "$SERVER_IP"
  fi
  step "Writing runtime configuration to $APP_DIR"
  mkdir -p "$APP_DIR" "$MYSQL_INIT_DIR"

  # Backend .env (mounted read-only into the containers as /app/.env).
  local mail_block
  if [ -n "${MAILGUN_DOMAIN:-}" ]; then
    mail_block="MAIL_MAILER=mailgun
MAILGUN_DOMAIN=$(env_quote "$MAILGUN_DOMAIN")
MAILGUN_SECRET=$(env_quote "${MAILGUN_SECRET:-}")
MAILGUN_ENDPOINT=$(env_quote "${MAILGUN_ENDPOINT:-api.mailgun.net}")
MAIL_FROM_ADDRESS=$(env_quote "${MAILGUN_FROM:-no-reply@${MAILGUN_DOMAIN}}")"
  elif [ -n "${SMTP_HOST:-}" ]; then
    mail_block="MAIL_MAILER=smtp
MAIL_HOST=$(env_quote "$SMTP_HOST")
MAIL_PORT=${SMTP_PORT:-587}
MAIL_USERNAME=$(env_quote "${SMTP_USER:-}")
MAIL_PASSWORD=$(env_quote "${SMTP_PASSWORD:-}")
MAIL_FROM_ADDRESS=$(env_quote "${SMTP_FROM:-no-reply@${DOMAIN}}")"
  else
    mail_block="MAIL_MAILER=log
MAIL_FROM_ADDRESS=\"no-reply@${DOMAIN}\""
  fi
  local uploads_block
  if [ -n "${OCI_BUCKET:-}" ]; then
    uploads_block="UPLOADS_DISK=oci
OCI_BUCKET=$(env_quote "$OCI_BUCKET")
OCI_NAMESPACE=$(env_quote "$OCI_NAMESPACE")
OCI_REGION=$(env_quote "$OCI_REGION")
OCI_ACCESS_KEY_ID=$(env_quote "$OCI_ACCESS_KEY_ID")
OCI_SECRET_ACCESS_KEY=$(env_quote "$OCI_SECRET_ACCESS_KEY")"
  else
    uploads_block="UPLOADS_DISK=uploads_local"
  fi
  (umask 077; cat > "$BACKEND_ENV" <<EOF
# Generated by deploy.sh — edit via the script / secrets.env, not by hand.
APP_NAME="Inventory Platform"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://${DOMAIN}
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

# Platform host(s). Subdomains of these resolve to tenants.
CENTRAL_DOMAINS=${DOMAIN}
# Custom domains: customers point a CNAME at the target host (or an A record at the IP).
CUSTOM_DOMAIN_TARGET_HOST=domains.${DOMAIN}
CUSTOM_DOMAIN_TARGET_IPS=${SERVER_IP:-}
TRUSTED_PROXIES=*

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=central
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=inventory_central
DB_USERNAME=inventory
DB_PASSWORD=${DB_APP_PASSWORD}
TENANCY_DB_PREFIX=tenant
TENANCY_DB_SUFFIX=

SESSION_DRIVER=database
SESSION_LIFETIME=120
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
BCRYPT_ROUNDS=12

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=predis
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

${mail_block}

# Uploaded files (logo, favicon, profile pictures).
${uploads_block}

# Daily database backups (central + every tenant), kept 7 days.
BACKUP_ENABLED=true
BACKUP_RETENTION_DAYS=7

AI_REPORTS_ENABLED=false
LLM_PROVIDER=none
EOF
  )

  # Compose variable file.
  (umask 077; cat > "$COMPOSE_ENV" <<EOF
SRC_DIR=${SRC_DIR}
APP_DIR=${APP_DIR}
BACKEND_ENV_FILE=${BACKEND_ENV}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
REDIS_PASSWORD=${REDIS_PASSWORD}
MYSQL_LOCAL_PORT=${MYSQL_LOCAL_PORT:-3307}
EOF
  )

  # MySQL: least-privilege app user, created on first volume initialisation.
  # It may manage the central DB and any tenant database (tenancy creates one
  # per tenant, named tenant<id>) but nothing else on the server.
  (umask 077; cat > "$MYSQL_INIT_DIR/01-app-user.sql" <<EOF
CREATE USER IF NOT EXISTS 'inventory'@'%' IDENTIFIED BY '${DB_APP_PASSWORD}';
GRANT ALL PRIVILEGES ON \`inventory\\_central\`.* TO 'inventory'@'%';
GRANT ALL PRIVILEGES ON \`tenant%\`.* TO 'inventory'@'%';
FLUSH PRIVILEGES;
EOF
  )
  chmod 755 "$MYSQL_INIT_DIR"; chmod 644 "$MYSQL_INIT_DIR/01-app-user.sql"

  cat > "$COMPOSE_FILE" <<'EOF'
# Generated by deploy.sh. Variables come from compose.env.
name: inventory

x-logging: &logging
  driver: json-file
  options:
    max-size: "10m"
    max-file: "3"

x-backend-common: &backend-common
  image: inventory-backend:latest
  restart: unless-stopped
  logging: *logging
  volumes:
    - ${BACKEND_ENV_FILE}:/app/.env:ro
    - app-storage:/app/storage
  depends_on:
    mysql:
      condition: service_healthy
    redis:
      condition: service_healthy

services:
  mysql:
    image: mysql:8.0
    restart: unless-stopped
    logging: *logging
    command: ["--character-set-server=utf8mb4", "--collation-server=utf8mb4_unicode_ci"]
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: inventory_central
    # Loopback ONLY: reachable from the server itself (e.g. through an SSH
    # tunnel from SQLyog/DBeaver), never from the internet.
    ports:
      - "127.0.0.1:${MYSQL_LOCAL_PORT}:3306"
    volumes:
      - mysql-data:/var/lib/mysql
      - ${APP_DIR}/mysql-init:/docker-entrypoint-initdb.d:ro
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h 127.0.0.1 -uroot -p\"$$MYSQL_ROOT_PASSWORD\" --silent"]
      interval: 10s
      timeout: 5s
      retries: 30
      start_period: 60s

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    logging: *logging
    command: ["redis-server", "--requirepass", "${REDIS_PASSWORD}", "--appendonly", "yes"]
    environment:
      REDIS_PASSWORD: ${REDIS_PASSWORD}
    volumes:
      - redis-data:/data
    healthcheck:
      test: ["CMD-SHELL", "redis-cli -a \"$$REDIS_PASSWORD\" --no-auth-warning ping | grep -q PONG"]
      interval: 10s
      timeout: 5s
      retries: 20

  # Laravel API. Owns the one-time migrate + seed on boot (see entrypoint.sh).
  api:
    <<: *backend-common
    build:
      context: ${SRC_DIR}/backend
    environment:
      RUN_SETUP: "true"
      PHP_CLI_SERVER_WORKERS: "6"
    ports:
      - "127.0.0.1:8000:8000"
    command: ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
    healthcheck:
      test: ["CMD-SHELL", "php -r 'exit(@file_get_contents(\"http://127.0.0.1:8000/up\") === false ? 1 : 0);'"]
      interval: 15s
      timeout: 10s
      retries: 20
      start_period: 180s

  worker:
    <<: *backend-common
    environment:
      RUN_SETUP: "false"
    command: ["php", "artisan", "queue:work", "--tries=3", "--timeout=120", "--max-time=3600"]
    depends_on:
      api:
        condition: service_healthy

  scheduler:
    <<: *backend-common
    environment:
      RUN_SETUP: "false"
    command: ["php", "artisan", "schedule:work"]
    depends_on:
      api:
        condition: service_healthy

  frontend:
    build:
      context: ${SRC_DIR}/frontend
      args:
        # Empty = the browser calls /api on whatever host it is on (the tenant
        # subdomain), so the API resolves the right tenant from the Host header.
        NEXT_PUBLIC_API_BASE_URL: ""
    image: inventory-frontend:latest
    restart: unless-stopped
    logging: *logging
    environment:
      # Used only by the Next.js SERVER (branding lookup); the browser never sees it.
      API_BASE_URL: http://api:8000
    ports:
      - "127.0.0.1:3000:3000"
    depends_on:
      api:
        condition: service_healthy

volumes:
  mysql-data:
  redis-data:
  app-storage:
EOF
}

# ── 4. Build + start ─────────────────────────────────────────────────────────
wait_http() { # wait_http URL SECONDS
  local url="$1" max="$2" waited=0
  until curl -fsS -o /dev/null --max-time 5 "$url" 2>/dev/null; do
    waited=$((waited + 5)); [ "$waited" -lt "$max" ] || return 1; sleep 5
  done
}

start_stack() {
  step "Building images (first build takes several minutes)"
  compose build --pull
  step "Starting containers"
  compose up -d --remove-orphans
  log "waiting for the API (runs database migrations on first start)…"
  if ! wait_http "http://127.0.0.1:8000/up" 900; then
    compose logs --tail=80 api || true
    die "API did not become healthy. See logs above (deploy.sh logs api)."
  fi
  wait_http "http://127.0.0.1:3000/" 120 || { compose logs --tail=60 frontend || true; die "frontend did not start"; }
  log "API and frontend are up"
}

post_deploy() {
  step "Post-deploy tasks"
  log "migrating tenant databases (no-op when there are no tenants yet)"
  compose exec -T api php artisan tenants:migrate --force \
    || warn "tenants:migrate reported a problem — check: deploy.sh logs api"

  if [ "${ADMIN_CREATED:-0}" != "1" ]; then
    log "creating the first Platform Admin ($ADMIN_EMAIL)"
    compose exec -T api php artisan platform:admin \
      --email="$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --name="$ADMIN_NAME" >/dev/null
    set_state ADMIN_CREATED 1
  else
    log "Platform Admin already created (password is not reset on re-deploys)"
  fi
}

# ── 5. nginx + TLS ───────────────────────────────────────────────────────────
nginx_reload() { nginx -t >/dev/null 2>&1 || { nginx -t; die "nginx config invalid"; }; systemctl enable nginx >/dev/null 2>&1 || true; systemctl reload nginx 2>/dev/null || systemctl restart nginx; }

write_nginx_bootstrap() {
  mkdir -p "$ACME_ROOT"
  cat > "$NGINX_CONF" <<EOF
# Temporary HTTP-only config used while the certificate is being issued.
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${DOMAIN} www.${DOMAIN} *.${DOMAIN};
    location /.well-known/acme-challenge/ { root ${ACME_ROOT}; }
    location / { return 503 "Setting up…"; }
}
EOF
  ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/inventory.conf
  nginx_reload
}

write_nginx_full() {
  mkdir -p "$ACME_ROOT"
  # Quoted heredoc + sed so nginx's own $variables need no escaping.
  sed "s/__DOMAIN__/${DOMAIN}/g; s#__ACME_ROOT__#${ACME_ROOT}#g" > "$NGINX_CONF" <<'EOF'
# Generated by deploy.sh — public entry point for the platform and every tenant.
#   /api/*   -> Laravel API  (127.0.0.1:8000)
#   /up      -> Laravel health
#   others   -> Next.js      (127.0.0.1:3000)
# The ORIGINAL Host header is passed through: the backend resolves the tenant
# from it (acme.__DOMAIN__ -> tenant "acme"; __DOMAIN__ -> platform console).

map $http_upgrade $connection_upgrade { default upgrade; '' close; }

server {
    listen 80;
    listen [::]:80;
    server_name __DOMAIN__ www.__DOMAIN__ *.__DOMAIN__;
    location /.well-known/acme-challenge/ { root __ACME_ROOT__; }
    location / { return 301 https://$host$request_uri; }
}

# Unknown hosts / direct-IP requests get nothing.
server {
    listen 443 ssl http2 default_server;
    listen [::]:443 ssl http2 default_server;
    server_name _;
    ssl_certificate     /etc/letsencrypt/live/__DOMAIN__/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/__DOMAIN__/privkey.pem;
    return 444;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name www.__DOMAIN__;
    ssl_certificate     /etc/letsencrypt/live/__DOMAIN__/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/__DOMAIN__/privkey.pem;
    return 301 https://__DOMAIN__$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name __DOMAIN__ *.__DOMAIN__;

    ssl_certificate     /etc/letsencrypt/live/__DOMAIN__/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/__DOMAIN__/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    client_max_body_size 25m;
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;

    proxy_http_version 1.1;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host  $host;
    proxy_set_header Upgrade           $http_upgrade;
    proxy_set_header Connection        $connection_upgrade;
    proxy_read_timeout 120s;

    location /api/ { proxy_pass http://127.0.0.1:8000; }
    location = /up { proxy_pass http://127.0.0.1:8000; }
    location /     { proxy_pass http://127.0.0.1:3000; }
}
EOF
  ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/inventory.conf
  nginx_reload
}

print_dns_help() {
  local ip; ip=$(curl -4 -fsS --max-time 8 https://api.ipify.org 2>/dev/null || echo "<this server's IP>")
  cat <<EOF

    DNS records required (Hostinger hPanel -> Domains -> ${DOMAIN} -> DNS / Nameservers,
    or in Cloudflare if you chose TLS_MODE=cloudflare):

        Type  Name   Value
        A     @      ${ip}
        A     *      ${ip}

EOF
}

dns_points_here() { # dns_points_here HOSTNAME
  local ip got
  ip=$(curl -4 -fsS --max-time 8 https://api.ipify.org 2>/dev/null || true)
  got=$(dig +short A "$1" @1.1.1.1 2>/dev/null | tail -n1)
  [ -n "$ip" ] && [ "$got" = "$ip" ]
}

choose_tls_mode() {
  if [ -z "${TLS_MODE:-}" ]; then
    echo
    echo "    HTTPS certificate method (tenants use subdomains, so a wildcard is best):"
    echo "      1) cloudflare  wildcard, fully automatic  (needs the domain's DNS on Cloudflare — recommended)"
    echo "      2) manual      wildcard, you add a TXT record by hand (and again every ~60 days)"
    echo "      3) http        keeps Hostinger DNS; apex+www now, add each tenant with 'deploy.sh add-host <sub>'"
    if [ -t 0 ]; then read -r -p "    Choose 1, 2 or 3 [1]: " choice; fi
    case "${choice:-1}" in 1) TLS_MODE=cloudflare ;; 2) TLS_MODE=manual ;; 3) TLS_MODE=http ;; *) die "invalid choice" ;; esac
  fi
  case "$TLS_MODE" in cloudflare|manual|http) ;; *) die "TLS_MODE must be cloudflare, manual or http" ;; esac
  set_state TLS_MODE "$TLS_MODE"
  ask LE_EMAIL "Email for Let's Encrypt expiry notices" "$ADMIN_EMAIL"
}

issue_certificate() {
  step "HTTPS certificate for ${DOMAIN}"
  local live="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
  if [ -f "$live" ]; then log "certificate already present — skipping issuance"; return; fi

  choose_tls_mode
  print_dns_help

  case "$TLS_MODE" in
    cloudflare)
      ask CF_API_TOKEN "Cloudflare API token (Zone:DNS:Edit for ${DOMAIN})" "" secret
      (umask 077; printf 'dns_cloudflare_api_token = %s\n' "$CF_API_TOKEN" > /etc/letsencrypt/cloudflare.ini)
      certbot certonly --dns-cloudflare \
        --dns-cloudflare-credentials /etc/letsencrypt/cloudflare.ini \
        --dns-cloudflare-propagation-seconds 40 \
        --cert-name "$DOMAIN" -d "$DOMAIN" -d "*.${DOMAIN}" \
        --agree-tos -m "$LE_EMAIL" --non-interactive
      ;;
    manual)
      [ -t 0 ] || die "TLS_MODE=manual needs an interactive terminal"
      echo "    certbot will show one or two TXT records for _acme-challenge.${DOMAIN}."
      echo "    Add ALL of them in Hostinger DNS (both values go on the same name), wait ~2 minutes,"
      echo "    then press Enter. This certificate will NOT auto-renew: re-run in ~60 days."
      certbot certonly --manual --preferred-challenges dns \
        --cert-name "$DOMAIN" -d "$DOMAIN" -d "*.${DOMAIN}" \
        --agree-tos -m "$LE_EMAIL"
      ;;
    http)
      write_nginx_bootstrap
      local h
      for h in "$DOMAIN" "www.${DOMAIN}"; do
        dns_points_here "$h" || { warn "$h does not resolve to this server yet (DNS may still be propagating)"; \
          [ -t 0 ] && { read -r -p "    Continue anyway? [y/N] " yn; [ "${yn:-n}" = "y" ] || die "fix DNS and re-run"; } || die "fix DNS and re-run"; }
      done
      printf '%s\n%s\n' "$DOMAIN" "www.${DOMAIN}" > "$CERT_HOSTS_FILE"
      certbot certonly --webroot -w "$ACME_ROOT" \
        --cert-name "$DOMAIN" -d "$DOMAIN" -d "www.${DOMAIN}" \
        --agree-tos -m "$LE_EMAIL" --non-interactive
      ;;
  esac
  [ -f "$live" ] || die "certificate was not issued"
}

setup_cert_renewal() {
  mkdir -p /etc/letsencrypt/renewal-hooks/deploy
  cat > /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh <<'EOF'
#!/bin/sh
systemctl reload nginx
EOF
  chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-nginx.sh
  if [ "${TLS_MODE:-}" = "manual" ]; then
    warn "TLS_MODE=manual: no automatic renewal. Re-run 'sudo ./deploy.sh' before the certificate expires."
  else
    systemctl enable --now certbot.timer >/dev/null 2>&1 || true
    log "automatic renewal is enabled (certbot.timer)"
  fi
}

# ── 6. Verification + summary ────────────────────────────────────────────────
smoke_test() {
  step "Smoke test"
  local code
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/up" || true)
  [ "$code" = "200" ] && log "https://${DOMAIN}/up -> 200 (nginx -> API ok)" || warn "https://${DOMAIN}/up returned HTTP ${code:-none}"
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/login" || true)
  [ "$code" = "200" ] && log "https://${DOMAIN}/login -> 200 (nginx -> frontend ok)" || warn "https://${DOMAIN}/login returned HTTP ${code:-none}"
}

summary() {
  cat <<EOF

${C_G}${C_B}Deployment complete.${C_0}

  Platform console   https://${DOMAIN}/login
  Admin email        ${ADMIN_EMAIL}
  Admin password     ${ADMIN_PASSWORD}      <- save it now, change it after first login
  Tenants            https://<subdomain>.${DOMAIN}

  Create your first tenant: sign in at the console above -> Tenants -> Add tenant.
  Enter just the subdomain (e.g. "acme") and leave Domain blank; the workspace
  appears at https://acme.${DOMAIN} (a wildcard DNS record must exist).

  Files (back these up — losing secrets.env/APP_KEY makes encrypted data unreadable):
    ${STATE_FILE}      generated passwords + saved answers (0600)
    ${BACKEND_ENV}     application configuration
  Daily DB backups     inside the 'app-storage' volume (storage/app/backups); copy them
                       off-server regularly:  docker compose -p ${PROJECT} cp api:/app/storage/app/backups ./backups

  Database (SQLyog etc.): MySQL listens on 127.0.0.1:${MYSQL_LOCAL_PORT:-3307} ON THE SERVER only.
    Connect through an SSH tunnel -> host 127.0.0.1, port ${MYSQL_LOCAL_PORT:-3307}, user 'inventory',
    password DB_APP_PASSWORD from ${STATE_FILE}.

  Day-2 commands:  sudo $0 update | status | logs [service] | backup
EOF
  if [ "${TLS_MODE:-}" = "http" ]; then
    echo "  TLS_MODE=http: after creating a tenant run:  sudo $0 add-host <subdomain>"
  fi
}

# ── Sub-commands ─────────────────────────────────────────────────────────────
# ── 7. Custom domains (tenant-owned domains, one certbot certificate each) ───
# The API decides WHICH domains are active (DNS ownership proven). This host-side
# step does the privileged work the container cannot: certbot + nginx server
# blocks. It only trusts hostnames that match a strict DNS pattern, so nothing
# from the app can inject nginx config or certbot arguments.
CUSTOM_CONF_DIR="/etc/nginx/custom-domains"
PROXY_SNIPPET="/etc/nginx/snippets/inventory-proxy.conf"
SYNC_MARK="$APP_DIR/.domain-sync-refresh"
valid_custom_host() {
  [ "${#1}" -le 253 ] && [[ "$1" =~ ^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$ ]]
}
write_proxy_snippet() {
  mkdir -p "$(dirname "$PROXY_SNIPPET")"
  cat > "$PROXY_SNIPPET" <<'EOF'
# Generated by deploy.sh — shared by every custom-domain server block.
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 1d;
ssl_session_tickets off;
add_header Strict-Transport-Security "max-age=31536000" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
client_max_body_size 25m;
gzip on;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml image/svg+xml;
proxy_http_version 1.1;
proxy_set_header Host              $host;
proxy_set_header X-Real-IP         $remote_addr;
proxy_set_header X-Forwarded-For   $remote_addr;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Host  $host;
proxy_set_header Upgrade           $http_upgrade;
proxy_set_header Connection        $connection_upgrade;
proxy_read_timeout 120s;
location /api/ { proxy_pass http://127.0.0.1:8000; }
location = /up { proxy_pass http://127.0.0.1:8000; }
location /     { proxy_pass http://127.0.0.1:3000; }
EOF
}
setup_custom_domains() {
  step "Custom domains: nginx include + certificate sync timer"
  mkdir -p "$CUSTOM_CONF_DIR"
  write_proxy_snippet
  if ! grep -q "$CUSTOM_CONF_DIR" "$NGINX_CONF" 2>/dev/null; then
    printf '\n# Per-tenant custom domains (managed by deploy.sh sync-domains)\ninclude %s/*.conf;\n' "$CUSTOM_CONF_DIR" >> "$NGINX_CONF"
  fi
  nginx_reload
  # Keep a stable copy of this script for the timer, regardless of where it was run from.
  install -m 700 "$(readlink -f "$0")" "$APP_DIR/deploy.sh"
  cat > /etc/systemd/system/inventory-domains.service <<EOF
[Unit]
Description=Sync HTTPS certificates for tenant custom domains
[Service]
Type=oneshot
ExecStart=$APP_DIR/deploy.sh sync-domains
EOF
  cat > /etc/systemd/system/inventory-domains.timer <<'EOF'
[Unit]
Description=Sync tenant custom-domain certificates every 2 minutes
[Timer]
OnBootSec=2min
OnUnitActiveSec=2min
AccuracySec=15s
[Install]
WantedBy=timers.target
EOF
  systemctl daemon-reload
  systemctl enable --now inventory-domains.timer >/dev/null 2>&1 || warn "could not enable inventory-domains.timer"
  # Per-domain certificates renew through certbot's own timer.
  systemctl enable --now certbot.timer >/dev/null 2>&1 || true
}
write_custom_vhost() { # write_custom_vhost HOST
  cat > "$CUSTOM_CONF_DIR/$1.conf" <<EOF
# Generated by deploy.sh sync-domains — do not edit.
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $1;
    ssl_certificate     /etc/letsencrypt/live/$1/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$1/privkey.pem;
    include $PROXY_SNIPPET;
}
EOF
}
cert_expiry_iso() { # cert_expiry_iso HOST
  local end; end=$(openssl x509 -enddate -noout -in "/etc/letsencrypt/live/$1/fullchain.pem" 2>/dev/null | cut -d= -f2)
  [ -n "$end" ] && date -u -d "$end" +%Y-%m-%dT%H:%M:%SZ
}
sync_custom_domains() {
  local out line h changed=0 refresh=0 results="" sep="" keep=() issue=() ok=0
  out=$(compose exec -T api php artisan domains:ssl-list 2>/dev/null) || { warn "domains:ssl-list failed; leaving things as they are"; return 1; }
  while IFS= read -r line; do
    line="${line%$'\r'}"
    case "$line" in
      "KEEP "*)  h="${line#KEEP }";  valid_custom_host "$h" && keep+=("$h")  || warn "ignoring invalid host from app: $h" ;;
      "ISSUE "*) h="${line#ISSUE }"; valid_custom_host "$h" && issue+=("$h") || warn "ignoring invalid host from app: $h" ;;
      END) ok=1 ;;
    esac
  done <<< "$out"
  # Without the END marker the listing may be truncated; never delete based on it.
  [ "$ok" -eq 1 ] || { warn "incomplete domain listing; skipping this run"; return 1; }
  if [ ! -f "$SYNC_MARK" ] || [ -n "$(find "$SYNC_MARK" -mmin +1440 2>/dev/null)" ]; then refresh=1; fi
  ask LE_EMAIL "Email for Let's Encrypt expiry notices" "${ADMIN_EMAIL:-admin@${DOMAIN}}" >/dev/null

  add_result() { results="${results}${sep}{\"host\":\"$1\",\"status\":\"$2\"${3:+,\"expires\":\"$3\"}}"; sep=","; }

  for h in "${issue[@]}"; do
    log "requesting certificate for $h"
    if timeout 180 certbot certonly --webroot -w "$ACME_ROOT" --cert-name "$h" -d "$h" \
         --agree-tos -m "$LE_EMAIL" --non-interactive --keep-until-expiring >/dev/null 2>&1; then
      write_custom_vhost "$h"
      if nginx -t >/dev/null 2>&1; then
        changed=1; add_result "$h" issued "$(cert_expiry_iso "$h")"
      else
        rm -f "$CUSTOM_CONF_DIR/$h.conf"; warn "nginx rejected the server block for $h"; add_result "$h" failed
      fi
    else
      warn "certbot could not issue a certificate for $h (DNS not pointing here yet?)"; add_result "$h" failed
    fi
  done

  # Active domains that already have a certificate: make sure the server block exists
  # and (once a day) refresh the stored expiry after renewals.
  for h in "${keep[@]}"; do
    [ -f "/etc/letsencrypt/live/$h/fullchain.pem" ] || continue
    if [ ! -f "$CUSTOM_CONF_DIR/$h.conf" ]; then write_custom_vhost "$h"; changed=1; add_result "$h" issued "$(cert_expiry_iso "$h")"
    elif [ "$refresh" -eq 1 ]; then add_result "$h" issued "$(cert_expiry_iso "$h")"; fi
  done

  # Remove server blocks and certificates of domains that are no longer active.
  local f
  for f in "$CUSTOM_CONF_DIR"/*.conf; do
    [ -e "$f" ] || continue
    h="$(basename "$f" .conf)"
    if ! printf '%s\n' "${keep[@]}" | grep -qxF "$h"; then
      log "removing $h"
      rm -f "$f"; changed=1
      valid_custom_host "$h" && certbot delete --cert-name "$h" --non-interactive >/dev/null 2>&1 || true
    fi
  done

  if [ "$changed" -eq 1 ]; then nginx_reload; fi
  if [ -n "$results" ]; then
    printf '[%s]' "$results" | compose exec -T api php artisan domains:ssl-report >/dev/null || warn "could not report certificate results"
  fi
  if [ "$refresh" -eq 1 ]; then touch "$SYNC_MARK"; fi
}
cmd_sync_domains() {
  [ "$(id -u)" -eq 0 ] || die "run as root: sudo $0 sync-domains"
  load_state
  DOMAIN="${DOMAIN:-inspiresoft.online}"
  command -v docker >/dev/null || die "not installed yet — run without arguments first"
  # One run at a time; a slow certbot must not stack up behind the timer.
  exec 9>/var/lock/inventory-domains.lock
  flock -n 9 || { log "another sync is running"; return 0; }
  sync_custom_domains
}
cmd_install() {
  preflight
  install_packages
  install_docker
  setup_swap
  setup_firewall
  fetch_code
  verify_app_patches
  prepare_secrets
  write_runtime_files
  start_stack
  post_deploy
  issue_certificate
  write_nginx_full
  setup_cert_renewal
  setup_custom_domains
  smoke_test
  summary
}

cmd_update() {
  preflight
  command -v docker >/dev/null || die "not installed yet — run without arguments first"
  fetch_code
  verify_app_patches
  prepare_secrets
  write_runtime_files
  start_stack
  post_deploy
  setup_custom_domains
  smoke_test
  step "Update complete (commit $(git -C "$SRC_DIR" rev-parse --short HEAD))"
}

cmd_status() {
  preflight >/dev/null
  step "Containers"; compose ps
  step "Health"
  curl -fsS -o /dev/null --max-time 5 http://127.0.0.1:8000/up && log "API       ok" || warn "API       DOWN"
  curl -fsS -o /dev/null --max-time 5 http://127.0.0.1:3000/   && log "Frontend  ok" || warn "Frontend  DOWN"
  systemctl is-active --quiet nginx && log "nginx     ok" || warn "nginx     DOWN"
  step "Certificate"
  openssl x509 -enddate -noout -in "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" 2>/dev/null || warn "no certificate found"
  step "Disk"; df -h / | tail -1; docker system df | head -5
  step "Version"; git -C "$SRC_DIR" log -1 --format='%h %ad %s' --date=short 2>/dev/null || true
}

cmd_logs()   { preflight >/dev/null; compose logs -f --tail=200 "$@"; }

cmd_backup() {
  preflight >/dev/null
  step "Running database backup now"
  compose exec -T api php artisan db:backup
  compose exec -T api sh -c 'ls -lh storage/app/backups 2>/dev/null | tail -15' || true
}

cmd_add_host() {
  preflight >/dev/null
  [ "$#" -ge 1 ] || die "usage: $0 add-host <subdomain> [subdomain ...]"
  [ "${TLS_MODE:-}" = "http" ] || { log "TLS_MODE is '${TLS_MODE:-unset}': the wildcard certificate already covers every subdomain."; return 0; }
  ask LE_EMAIL "Email for Let's Encrypt expiry notices" "${ADMIN_EMAIL:-admin@${DOMAIN}}"
  touch "$CERT_HOSTS_FILE"
  local sub host
  for sub in "$@"; do
    host="${sub%.${DOMAIN}}.${DOMAIN}"
    dns_points_here "$host" || die "$host does not resolve to this server — add an A record for it first"
    grep -qxF "$host" "$CERT_HOSTS_FILE" || echo "$host" >> "$CERT_HOSTS_FILE"
  done
  local args=() h
  while IFS= read -r h; do [ -n "$h" ] && args+=(-d "$h"); done < "$CERT_HOSTS_FILE"
  certbot certonly --webroot -w "$ACME_ROOT" --cert-name "$DOMAIN" --expand \
    --agree-tos -m "$LE_EMAIL" --non-interactive "${args[@]}"
  nginx_reload
  log "certificate now covers: $(tr '\n' ' ' < "$CERT_HOSTS_FILE")"
}

usage() { sed -n '2,/^# ──.*$/p' "$0" | sed -n '1,60p' | sed 's/^# \{0,1\}//'; }

main() {
  local cmd="${1:-install}"; shift || true
  case "$cmd" in
    install)  cmd_install ;;
    update)   cmd_update ;;
    status)   cmd_status ;;
    logs)     cmd_logs "$@" ;;
    backup)   cmd_backup ;;
    add-host) cmd_add_host "$@" ;;
    sync-domains) cmd_sync_domains ;;
    -h|--help|help) usage ;;
    *) die "unknown command '$cmd' (install | update | status | logs | backup | add-host | sync-domains)" ;;
  esac
}
# Run only when executed, so the functions can be sourced for testing.
if [ "${BASH_SOURCE[0]}" = "$0" ]; then main "$@"; fi
