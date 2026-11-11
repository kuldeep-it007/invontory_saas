#!/usr/bin/env bash
# Inventory & Distribution Platform - automated Docker setup (Ubuntu/Linux).
#
# Runs the COMPLETE stack with Docker only - no PHP, Node, MySQL, or Redis
# installed on the host. It:
#   1) verifies Docker + the compose plugin are installed and running,
#   2) frees the ports the stack needs (stops any container holding them),
#   3) builds the API, worker, and frontend images,
#   4) starts everything (MySQL + Redis + API + worker + frontend),
#   5) waits for MySQL + the API to become healthy,
#   6) prints the URLs.
#
# Usage (from the repo root):
#   chmod +x docker-start.sh
#   ./docker-start.sh            # normal start
#   ./docker-start.sh --fresh    # also wipe the database volume first
#
# Stop later with:  docker compose down   (add -v to also drop the DB volume)
#
# Note: on Ubuntu, if your user is not in the `docker` group you may need sudo.
# Either run `sudo ./docker-start.sh` or add yourself: `sudo usermod -aG docker $USER`.

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

FRESH=false
[ "${1:-}" = "--fresh" ] && FRESH=true

# Ports the stack publishes on the host. If another container holds one, we
# stop that container so the stack can bind.
declare -A PORTS=( ["MySQL"]=3306 ["Redis"]=6379 ["API"]=8000 ["Frontend"]=3000 )

cyan()  { printf '\033[36m==> %s\033[0m\n' "$1"; }
green() { printf '\033[32m    %s\033[0m\n' "$1"; }
yellow(){ printf '\033[33m    %s\033[0m\n' "$1"; }

# Pick `docker compose` (v2) or legacy `docker-compose`.
if docker compose version >/dev/null 2>&1; then
  DC="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DC="docker-compose"
else
  echo "Docker Compose is not available. Install Docker Engine + the compose plugin." >&2
  exit 1
fi

# 1) Docker present + running
cyan "Checking Docker..."
if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is not installed. See https://docs.docker.com/engine/install/ubuntu/" >&2
  exit 1
fi
if ! docker info >/dev/null 2>&1; then
  echo "Docker is installed but the daemon is not reachable. Start it with: sudo systemctl start docker" >&2
  exit 1
fi
green "Docker is running."

# 2) Free the required host ports
cyan "Freeing required host ports..."
for name in "${!PORTS[@]}"; do
  port="${PORTS[$name]}"
  while read -r id; do
    [ -z "$id" ] && continue
    cname="$(docker inspect --format '{{.Name}}' "$id" 2>/dev/null || true)"
    case "$cname" in
      *invontory_saas*) ;;  # our own stack - leave it to compose
      *)
        yellow "Port $port ($name) is held by ${cname#/} - stopping it."
        docker stop "$id" >/dev/null
        ;;
    esac
  done < <(docker ps --format '{{.ID}} {{.Ports}}' 2>/dev/null | grep -E ":${port}->" | awk '{print $1}')
done
green "Ports available."

# 3) Optional fresh database
if [ "$FRESH" = true ]; then
  cyan "Removing existing stack + database volume (fresh start)..."
  $DC down -v >/dev/null 2>&1 || true
  green "Previous volumes removed."
fi

# 4) Build images
cyan "Building images (first run can take several minutes)..."
$DC build
green "Images built."

# 5) Start the stack
cyan "Starting the stack..."
$DC up -d
green "Containers started."

# 6) Wait for MySQL to be healthy
cyan "Waiting for MySQL to be healthy..."
deadline=$(( $(date +%s) + 180 ))
health=""
while [ "$(date +%s)" -lt "$deadline" ]; do
  sleep 3
  health="$(docker inspect --format '{{.State.Health.Status}}' invontory_saas-mysql-1 2>/dev/null || echo '')"
  [ "$health" = "healthy" ] && break
done
if [ "$health" = "healthy" ]; then green "MySQL is healthy."
else yellow "MySQL did not report healthy in time. Check: $DC logs mysql"; fi

# 7) Wait for the API health probe
cyan "Waiting for the API (migrations run on first boot)..."
deadline=$(( $(date +%s) + 180 ))
api_up=false
while [ "$(date +%s)" -lt "$deadline" ]; do
  sleep 4
  if curl -fsS "http://localhost:8000/api/health" >/dev/null 2>&1; then
    api_up=true; break
  fi
done
if [ "$api_up" = true ]; then green "API is responding."
else yellow "API not responding yet. Watch logs: $DC logs -f api"; fi

# 8) Summary
echo
printf '\033[32mStack is up.\033[0m\n'
echo "  Frontend     http://localhost:3000"
echo "  API health   http://localhost:8000/api/health"
echo
echo "Useful commands:"
echo "  $DC ps                 # service status"
echo "  $DC logs -f api        # tail the API"
echo "  $DC down               # stop the stack"
echo "  $DC down -v            # stop + wipe the database"
