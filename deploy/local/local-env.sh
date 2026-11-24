#!/usr/bin/env bash
# Local Ubuntu 22.04 environment that mirrors production's moving parts:
#   Docker stack (MySQL, Redis, API, worker, frontend, nginx)
# + the host monitoring agent (real CPU/RAM/disk/network + Docker/MySQL/Redis metrics)
# + the pre-deployment quality gate, with reports and history in the platform console.
#
# It does not need a domain, TLS certificate or root access to the stack. Differences from the
# server: no systemd service for the agent (it runs in the background from this script), no host
# nginx or SSL, and the stack uses the repository's docker-compose.yml (APP_ENV=local).
#
# USAGE (from anywhere, as your normal user):
#   deploy/local/local-env.sh prereqs     install Docker, Compose, Python 3, git (uses sudo, once)
#   deploy/local/local-env.sh up          build + start the stack and the monitoring agent
#   deploy/local/local-env.sh admin       create a Platform Admin to log in with
#   deploy/local/local-env.sh gate        build, run the quality gate; result shows under
#                                         "Server & deployments" in the platform console
#   deploy/local/local-env.sh status | logs [service] | agent-stop | agent-start | down
#
# After `up`:  app http://localhost   console http://localhost/login   API http://localhost:8000/api/health
set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
export MON_LOCAL_DIR="${MON_LOCAL_DIR:-$ROOT/.local-monitor}"
PROJECT="inventory-local"
IMAGE="inventory-local-backend:latest"
COMPOSE=(docker compose -p "$PROJECT" -f "$ROOT/docker-compose.yml" -f "$ROOT/deploy/local/docker-compose.monitor.yml")
PID_FILE="$MON_LOCAL_DIR/agent.pid"

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

need_docker() {
  command -v docker >/dev/null || die "Docker is not installed. Run: $0 prereqs"
  docker info >/dev/null 2>&1 || die "Cannot talk to Docker. Log out and in again after 'prereqs' (docker group), or run Docker."
  command -v python3 >/dev/null || die "python3 is required. Run: $0 prereqs"
}

cmd_prereqs() {
  [ "$(id -u)" -ne 0 ] || die "run as your normal user; sudo is used where needed"
  . /etc/os-release
  [ "${ID:-}" = "ubuntu" ] || echo "Note: written for Ubuntu 22.04; detected ${PRETTY_NAME:-unknown}."
  say "Installing packages (sudo)"
  sudo apt-get update -y
  sudo apt-get install -y --no-install-recommends ca-certificates curl gnupg git openssl python3
  if ! command -v docker >/dev/null; then
    say "Installing Docker Engine + Compose plugin"
    sudo install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg
    sudo chmod a+r /etc/apt/keyrings/docker.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
      | sudo tee /etc/apt/sources.list.d/docker.list >/dev/null
    sudo apt-get update -y
    sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  fi
  sudo systemctl enable --now docker >/dev/null 2>&1 || true
  if ! id -nG "$USER" | grep -qw docker; then
    sudo usermod -aG docker "$USER"
    echo "Added $USER to the docker group. Log out and back in (or run: newgrp docker) before 'up'."
  fi
  say "Done. Next: $0 up"
}

agent_running() { [ -f "$PID_FILE" ] && kill -0 "$(cat "$PID_FILE")" 2>/dev/null; }

cmd_agent_start() {
  need_docker
  mkdir -p "$MON_LOCAL_DIR/data/deployments" "$MON_LOCAL_DIR/config"
  if agent_running; then echo "agent already running (pid $(cat "$PID_FILE"))"; return 0; fi
  MON_DIR="$MON_LOCAL_DIR" APP_DIR="$ROOT" COMPOSE_PROJECT="$PROJECT" BACKEND_ENV="$ROOT/backend/.env" \
  MON_SERVICES="mysql,redis,api,worker,frontend,nginx" MON_UNITS="docker" \
  MON_API_URL="http://127.0.0.1:8000/up" MON_FRONTEND_URL="http://127.0.0.1:3000/" \
    nohup python3 "$ROOT/deploy/monitor/agent.py" >"$MON_LOCAL_DIR/agent.log" 2>&1 &
  echo $! > "$PID_FILE"
  sleep 2
  agent_running && echo "agent running (pid $(cat "$PID_FILE")), log: $MON_LOCAL_DIR/agent.log" || die "agent exited; see $MON_LOCAL_DIR/agent.log"
}

cmd_agent_stop() {
  if agent_running; then kill "$(cat "$PID_FILE")" && echo "agent stopped"; else echo "agent not running"; fi
  rm -f "$PID_FILE"
}

cmd_up() {
  need_docker
  mkdir -p "$MON_LOCAL_DIR/data/deployments" "$MON_LOCAL_DIR/config"
  say "Building and starting the stack (first build takes several minutes)"
  "${COMPOSE[@]}" up -d --build
  say "Starting the monitoring agent"
  cmd_agent_start
  cat <<EOF

Ready (the API runs migrations on first start, give it a minute):
  App / console   http://localhost
  API health      http://localhost:8000/api/health
Create a login with:   $0 admin
Open the console, then the "Server & deployments" tab for live metrics.
Run the quality gate:  $0 gate
EOF
}

cmd_admin() {
  need_docker
  "${COMPOSE[@]}" exec api php artisan platform:admin
}

cmd_gate() {
  need_docker
  local id sha rc=0
  id="$(date -u +%Y%m%dT%H%M%SZ)-$(openssl rand -hex 3)"
  sha="$(git -C "$ROOT" rev-parse HEAD 2>/dev/null || echo local)"
  mkdir -p "$MON_LOCAL_DIR/data/deployments"
  export MON_DIR="$MON_LOCAL_DIR"
  python3 "$ROOT/deploy/gate/deployctl.py" begin --id "$id" --commit "$sha" --branch "$(git -C "$ROOT" branch --show-current 2>/dev/null || true)" \
    --trigger local --actor "$USER"
  say "Building the images that will be tested"
  "${COMPOSE[@]}" build api frontend
  say "Quality gate (the same checks the server runs before it starts a release)"
  python3 "$ROOT/deploy/gate/deployctl.py" gate --mode local --id "$id" --src "$ROOT" --image "$IMAGE" --commit "${sha:0:8}" || rc=$?
  if [ "$rc" -eq 0 ]; then
    python3 "$ROOT/deploy/gate/deployctl.py" finish --id "$id" --status success --reason "local gate run"
    say "Gate PASSED. Report: $MON_LOCAL_DIR/data/deployments/$id/gate-report.md"
  else
    python3 "$ROOT/deploy/gate/deployctl.py" finish --id "$id" --status blocked --reason "local quality gate failed"
    say "Gate FAILED. Report: $MON_LOCAL_DIR/data/deployments/$id/gate-report.md"
  fi
  return "$rc"
}

cmd_status() {
  need_docker
  "${COMPOSE[@]}" ps
  agent_running && echo "agent: running (pid $(cat "$PID_FILE"))" || echo "agent: stopped"
  [ -f "$MON_LOCAL_DIR/data/current.json" ] && python3 - "$MON_LOCAL_DIR/data/current.json" <<'PY'
import json, sys
s = json.load(open(sys.argv[1]))["sample"]
print(f"cpu {s['cpu_pct']}%  mem {s['mem_pct']}%  disk {s['disk_pct']}%  api {s['api_ms']} ms")
PY
}

cmd_down() {
  cmd_agent_stop
  need_docker
  "${COMPOSE[@]}" down
}

case "${1:-}" in
  prereqs) cmd_prereqs ;;
  up) cmd_up ;;
  admin) cmd_admin ;;
  gate) cmd_gate ;;
  status) cmd_status ;;
  logs) shift; need_docker; "${COMPOSE[@]}" logs -f --tail=200 "$@" ;;
  agent-start) cmd_agent_start ;;
  agent-stop) cmd_agent_stop ;;
  down) cmd_down ;;
  *) sed -n '2,/^set -Eeuo/p' "$0" | sed '$d' | sed 's/^# \{0,1\}//' ;;
esac
