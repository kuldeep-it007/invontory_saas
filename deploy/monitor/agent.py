#!/usr/bin/env python3
"""Inventory platform monitoring agent (host level, Python 3 standard library only).

Runs as its own systemd service, independent of the application containers, so
metrics and alerts keep flowing when the API, database or Docker stack fail.

Output (all under $MON_DIR/data, written atomically):
  current.json      latest full snapshot (server, services, infrastructure)
  series-1m.jsonl   one sample per minute, kept 26 hours
  series-5m.jsonl   five-minute averages, kept 31 days
  alerts.json       currently active alerts
  alerts.jsonl      alert history (fired / resolved)
Input:
  $MON_DIR/config/thresholds.json   warning/critical thresholds (edited from the dashboard)

No secrets are read or written. Database and Redis are queried with the
credentials already present inside their own containers.
"""
from __future__ import annotations

import json
import os
import re
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

MON_DIR = Path(os.environ.get("MON_DIR", "/opt/inventory/monitor"))
DATA = MON_DIR / "data"
CONFIG = MON_DIR / "config"
APP_DIR = Path(os.environ.get("APP_DIR", "/opt/inventory"))
PROJECT = os.environ.get("COMPOSE_PROJECT", "inventory")
DOMAIN = os.environ.get("DOMAIN", "")
INTERVAL = int(os.environ.get("MON_INTERVAL", "15"))
NGINX_LOG = os.environ.get("MON_NGINX_LOG", "/var/log/nginx/inventory-monitor.log")
API_URL = os.environ.get("MON_API_URL", "http://127.0.0.1:8000/up")
FRONTEND_URL = os.environ.get("MON_FRONTEND_URL", "http://127.0.0.1:3000/")
LATENCY_TARGET = os.environ.get("MON_LATENCY_TARGET", "1.1.1.1:443")
WEBHOOK = os.environ.get("MON_ALERT_WEBHOOK", "")
BACKEND_ENV = Path(os.environ.get("BACKEND_ENV", str(APP_DIR / "backend.env")))
RAW_RETENTION_S = 26 * 3600
ROLLUP_RETENTION_S = 31 * 86400

DEFAULT_THRESHOLDS = {
    # metric: [warning, critical]  (higher is worse unless listed in LOWER_IS_WORSE)
    "cpu_pct": [80, 95],
    "load_per_core": [1.0, 2.0],
    "mem_pct": [85, 95],
    "swap_pct": [50, 80],
    "disk_pct": [80, 90],
    "api_ms": [500, 2000],
    "latency_p95_ms": [1000, 3000],
    "error_rate_5xx_pct": [1, 5],
    "db_conn_pct": [70, 90],
    "queue_depth": [500, 5000],
    "ssl_days_left": [21, 7],
}
LOWER_IS_WORSE = {"ssl_days_left"}
SERVICES = ["mysql", "redis", "api", "worker", "scheduler", "frontend"]


def now() -> float:
    return time.time()


def iso(ts: float | None = None) -> str:
    return datetime.fromtimestamp(ts or now(), timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def atomic_write(path: Path, text: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(text, encoding="utf-8")
    os.replace(tmp, path)


def run(cmd: list[str], timeout: int = 8) -> str | None:
    try:
        out = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout, check=False)
        return out.stdout if out.returncode == 0 else None
    except (OSError, subprocess.SubprocessError):
        return None


# ── host metrics ────────────────────────────────────────────────────────────
def read_cpu_times() -> tuple[int, int]:
    with open("/proc/stat") as fh:
        parts = [int(x) for x in fh.readline().split()[1:]]
    idle = parts[3] + (parts[4] if len(parts) > 4 else 0)
    return sum(parts), idle


def parse_meminfo(text: str) -> dict:
    kv = {}
    for line in text.splitlines():
        m = re.match(r"(\w+):\s+(\d+)", line)
        if m:
            kv[m.group(1)] = int(m.group(2)) * 1024
    total = kv.get("MemTotal", 0)
    avail = kv.get("MemAvailable", kv.get("MemFree", 0))
    swap_total = kv.get("SwapTotal", 0)
    swap_used = swap_total - kv.get("SwapFree", 0)
    return {
        "mem_total": total, "mem_avail": avail, "mem_used": total - avail,
        "mem_pct": round((total - avail) * 100 / total, 1) if total else 0.0,
        "swap_total": swap_total, "swap_used": swap_used,
        "swap_pct": round(swap_used * 100 / swap_total, 1) if swap_total else 0.0,
    }


REAL_FS = {"ext4", "ext3", "xfs", "btrfs", "zfs", "vfat", "overlay", "f2fs"}


def disks() -> list[dict]:
    seen, out = set(), []
    try:
        lines = Path("/proc/mounts").read_text().splitlines()
    except OSError:
        return out
    for line in lines:
        dev, mnt, fs = line.split()[:3]
        if fs not in REAL_FS or not dev.startswith("/") or dev in seen:
            continue
        if mnt.startswith(("/var/lib/docker", "/snap", "/boot/efi")):
            continue
        seen.add(dev)
        try:
            st = os.statvfs(mnt)
        except OSError:
            continue
        total = st.f_blocks * st.f_frsize
        free = st.f_bavail * st.f_frsize
        used = total - st.f_bfree * st.f_frsize
        if total:
            out.append({"mount": mnt, "device": dev, "fs": fs, "total": total, "used": used,
                        "free": free, "pct": round(used * 100 / (used + free), 1) if used + free else 0.0})
    return out


def net_counters() -> tuple[int, int]:
    rx = tx = 0
    try:
        for line in Path("/proc/net/dev").read_text().splitlines()[2:]:
            name, data = line.split(":", 1)
            name = name.strip()
            if name == "lo" or name.startswith(("docker", "br-", "veth")):
                continue
            f = data.split()
            rx += int(f[0])
            tx += int(f[8])
    except (OSError, ValueError, IndexError):
        pass
    return rx, tx


def tcp_latency_ms(target: str) -> float | None:
    host, _, port = target.partition(":")
    t0 = time.perf_counter()
    try:
        with socket.create_connection((host, int(port or 443)), timeout=3):
            return round((time.perf_counter() - t0) * 1000, 1)
    except OSError:
        return None


def http_probe(url: str) -> tuple[bool, float | None, int | None]:
    t0 = time.perf_counter()
    try:
        with urllib.request.urlopen(url, timeout=5) as r:  # noqa: S310 (fixed loopback URLs)
            code = r.status
        return 200 <= code < 400, round((time.perf_counter() - t0) * 1000, 1), code
    except urllib.error.HTTPError as e:
        return False, round((time.perf_counter() - t0) * 1000, 1), e.code
    except Exception:
        return False, None, None


class ProcSampler:
    """Top processes by CPU and memory using /proc deltas (no psutil)."""

    def __init__(self) -> None:
        self.prev: dict[int, int] = {}
        self.prev_total = 0
        self.ncpu = os.cpu_count() or 1
        self.page = os.sysconf("SC_PAGE_SIZE") if hasattr(os, "sysconf") else 4096

    def sample(self, limit: int = 10) -> list[dict]:
        total, _ = read_cpu_times()
        dt = max(total - self.prev_total, 1)
        procs, cur = [], {}
        for d in Path("/proc").iterdir():
            if not d.name.isdigit():
                continue
            try:
                raw = (d / "stat").read_text()
                rest = raw[raw.rindex(")") + 2:].split()
                name = raw[raw.index("(") + 1:raw.rindex(")")]
                ticks = int(rest[11]) + int(rest[12])
                rss = int(rest[21]) * self.page
            except (OSError, ValueError, IndexError):
                continue
            pid = int(d.name)
            cur[pid] = ticks
            delta = ticks - self.prev.get(pid, ticks)
            procs.append({"pid": pid, "name": name, "cpu_pct": round(delta * 100 * self.ncpu / dt, 1), "rss": rss})
        self.prev, self.prev_total = cur, total
        procs.sort(key=lambda p: (p["cpu_pct"], p["rss"]), reverse=True)
        return procs[:limit]


# ── nginx access log (request volume, status classes, latency) ──────────────
class NginxTail:
    """Reads new lines from the monitor log. Format: `$status $request_time`."""

    def __init__(self, path: str) -> None:
        self.path, self.pos, self.inode = path, None, None

    def read(self) -> dict:
        res = {"req": 0, "s2xx": 0, "s3xx": 0, "s4xx": 0, "s5xx": 0, "times": []}
        try:
            st = os.stat(self.path)
        except OSError:
            return res | {"available": False}
        if self.inode != st.st_ino or (self.pos or 0) > st.st_size:
            self.pos = st.st_size if self.pos is None else 0  # first run: skip history
            self.inode = st.st_ino
        with open(self.path, "rb") as fh:
            fh.seek(self.pos or 0)
            data = fh.read(8_000_000)
            self.pos = fh.tell()
        for line in data.decode("utf-8", "replace").splitlines():
            parsed = parse_log_line(line)
            if parsed:
                status, secs = parsed
                res["req"] += 1
                res[f"s{status // 100}xx"] = res.get(f"s{status // 100}xx", 0) + 1
                res["times"].append(secs * 1000)
        res["available"] = True
        return res


def parse_log_line(line: str) -> tuple[int, float] | None:
    f = line.split()
    if len(f) < 2 or not f[0].isdigit():
        return None
    try:
        return int(f[0]), float(f[1])
    except ValueError:
        return None


def percentile(values: list[float], p: float) -> float | None:
    if not values:
        return None
    s = sorted(values)
    return round(s[min(len(s) - 1, int(len(s) * p))], 1)


# ── docker / database / queue ───────────────────────────────────────────────
def container_id(service: str) -> str | None:
    out = run(["docker", "ps", "-q", "--filter", f"label=com.docker.compose.project={PROJECT}",
               "--filter", f"label=com.docker.compose.service={service}"])
    return out.split()[0] if out and out.split() else None


def services_state() -> dict:
    out = run(["docker", "ps", "-a", "--filter", f"label=com.docker.compose.project={PROJECT}", "--format",
               "{{.Label \"com.docker.compose.service\"}}|{{.State}}|{{.Status}}|{{.Ports}}"])
    state = {s: {"up": False, "status": "missing", "ports": ""} for s in SERVICES}
    for line in (out or "").splitlines():
        svc, st, status, ports = (line.split("|") + ["", "", ""])[:4]
        if svc in state:
            state[svc] = {"up": st == "running" and "unhealthy" not in status, "status": status, "ports": ports}
    for unit in ("nginx", "fail2ban", "docker"):
        r = run(["systemctl", "is-active", unit])
        state[unit] = {"up": bool(r and r.strip() == "active"), "status": (r or "inactive").strip(), "ports": ""}
    return state


def db_stats() -> dict:
    cid = container_id("mysql")
    if not cid:
        return {"up": False}
    q = ("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Questions',"
         "'Slow_queries','Uptime','Aborted_connects','Max_used_connections'); "
         "SHOW VARIABLES WHERE Variable_name IN ('max_connections','version','innodb_buffer_pool_size','slow_query_log','long_query_time');")
    out = run(["docker", "exec", cid, "sh", "-c",
               'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -B -e "$0"', q], timeout=10)
    if out is None:
        return {"up": False}
    kv = dict(line.split("\t", 1) for line in out.splitlines() if "\t" in line)
    num = lambda k: int(kv.get(k, 0) or 0)  # noqa: E731
    mx = num("max_connections") or 1
    return {
        "up": True, "version": kv.get("version", ""), "threads_connected": num("Threads_connected"),
        "threads_running": num("Threads_running"), "idle": max(num("Threads_connected") - num("Threads_running"), 0),
        "max_connections": mx, "conn_pct": round(num("Threads_connected") * 100 / mx, 1),
        "questions": num("Questions"), "slow_queries": num("Slow_queries"), "uptime": num("Uptime"),
        "aborted_connects": num("Aborted_connects"), "max_used_connections": num("Max_used_connections"),
        "innodb_buffer_pool_mb": num("innodb_buffer_pool_size") // 1048576,
        "slow_query_log": kv.get("slow_query_log", ""), "long_query_time": kv.get("long_query_time", ""),
    }


def queue_stats() -> dict:
    cid = container_id("redis")
    if not cid:
        return {"up": False, "depth": None}
    out = run(["docker", "exec", cid, "sh", "-c",
               'redis-cli -a "$REDIS_PASSWORD" --no-auth-warning llen queues:default; '
               'redis-cli -a "$REDIS_PASSWORD" --no-auth-warning zcard queues:default:delayed; '
               'redis-cli -a "$REDIS_PASSWORD" --no-auth-warning info memory | grep used_memory_human'])
    if out is None:
        return {"up": False, "depth": None}
    lines = out.split()
    try:
        return {"up": True, "depth": int(lines[0]), "delayed": int(lines[1]),
                "memory": lines[2].split(":")[-1] if len(lines) > 2 else ""}
    except (ValueError, IndexError):
        return {"up": True, "depth": None}


# ── infrastructure (changes rarely: refreshed every 10 minutes) ─────────────
def first_line(cmd: list[str]) -> str:
    out = run(cmd)
    return out.strip().splitlines()[0] if out and out.strip() else ""


def listening_ports() -> list[dict]:
    ports = {}
    for fname, v6 in (("/proc/net/tcp", False), ("/proc/net/tcp6", True)):
        try:
            lines = Path(fname).read_text().splitlines()[1:]
        except OSError:
            continue
        for line in lines:
            f = line.split()
            if f[3] != "0A":
                continue
            addr, port = f[1].rsplit(":", 1)
            loop = addr in ("0100007F", "00000000000000000000000001000000")
            ports[int(port, 16)] = {"port": int(port, 16), "scope": "loopback" if loop else "public"}
    return sorted(ports.values(), key=lambda p: p["port"])


def ssl_info() -> dict:
    if not DOMAIN:
        return {"available": False}
    cert = Path(f"/etc/letsencrypt/live/{DOMAIN}/fullchain.pem")
    out = run(["openssl", "x509", "-enddate", "-subject", "-issuer", "-noout", "-in", str(cert)]) if cert.exists() else None
    if not out:
        return {"available": False, "domain": DOMAIN}
    m = re.search(r"notAfter=(.+)", out)
    try:
        end = datetime.strptime(m.group(1).strip(), "%b %d %H:%M:%S %Y %Z").replace(tzinfo=timezone.utc)
    except (AttributeError, ValueError):
        return {"available": False, "domain": DOMAIN}
    return {"available": True, "domain": DOMAIN, "expires": end.strftime("%Y-%m-%d"),
            "days_left": (end - datetime.now(timezone.utc)).days,
            "issuer": (re.search(r"issuer=(.+)", out) or [None, ""])[1].strip()}


REQUIRED_ENV = ["APP_KEY", "APP_URL", "DB_HOST", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD", "REDIS_HOST",
                "REDIS_PASSWORD", "CENTRAL_DOMAINS"]


def env_validation() -> list[dict]:
    """Reports which required keys are set and whether safe values are used. Never returns values."""
    kv = {}
    try:
        for line in BACKEND_ENV.read_text().splitlines():
            if "=" in line and not line.lstrip().startswith("#"):
                k, v = line.split("=", 1)
                kv[k.strip()] = v.strip().strip("'\"")
    except OSError:
        return [{"check": "backend.env readable", "ok": False}]
    checks = [{"check": f"{k} is set", "ok": bool(kv.get(k))} for k in REQUIRED_ENV]
    checks.append({"check": "APP_ENV is production", "ok": kv.get("APP_ENV") == "production"})
    checks.append({"check": "APP_DEBUG is false", "ok": kv.get("APP_DEBUG", "").lower() == "false"})
    checks.append({"check": "APP_URL uses https", "ok": kv.get("APP_URL", "").startswith("https://")})
    return checks


def infrastructure() -> dict:
    osr = {}
    try:
        for line in Path("/etc/os-release").read_text().splitlines():
            if "=" in line:
                k, v = line.split("=", 1)
                osr[k] = v.strip('"')
    except OSError:
        pass
    api = container_id("api")
    php = first_line(["docker", "exec", api, "php", "-r", "echo PHP_VERSION;"]) if api else ""
    laravel = first_line(["docker", "exec", api, "php", "artisan", "--version"]) if api else ""
    fe = container_id("frontend")
    node = first_line(["docker", "exec", fe, "node", "--version"]) if fe else ""
    nginx_t = run(["nginx", "-t"])
    nginx_ok = nginx_t is not None
    try:
        cpu_model = next((l.split(":", 1)[1].strip() for l in Path("/proc/cpuinfo").read_text().splitlines()
                          if l.startswith("model name")), "")
    except (OSError, StopIteration):
        cpu_model = ""
    return {
        "os": osr.get("PRETTY_NAME", sys.platform), "kernel": os.uname().release, "arch": os.uname().machine,
        "hostname": socket.gethostname(), "cpu_cores": os.cpu_count(), "cpu_model": cpu_model,
        "docker": first_line(["docker", "--version"]), "compose": first_line(["docker", "compose", "version"]),
        "nginx": first_line(["nginx", "-v"]) or "", "nginx_config_ok": nginx_ok,
        "php": php, "laravel": laravel, "node": node, "python": sys.version.split()[0],
        "ssl": ssl_info(), "ports": listening_ports(), "env_checks": env_validation(),
        "refreshed": iso(),
    }


# ── thresholds + alerts ─────────────────────────────────────────────────────
def load_thresholds() -> dict:
    merged = {k: list(v) for k, v in DEFAULT_THRESHOLDS.items()}
    try:
        user = json.loads((CONFIG / "thresholds.json").read_text())
        for k, v in user.items():
            if k in merged and isinstance(v, list) and len(v) == 2 and all(isinstance(x, (int, float)) for x in v):
                merged[k] = [float(v[0]), float(v[1])]
    except (OSError, ValueError, AttributeError):
        pass
    return merged


def severity(metric: str, value: float | None, th: list[float]) -> str | None:
    if value is None:
        return None
    warn, crit = th
    if metric in LOWER_IS_WORSE:
        return "critical" if value <= crit else "warning" if value <= warn else None
    return "critical" if value >= crit else "warning" if value >= warn else None


def evaluate(sample: dict, services: dict, thresholds: dict) -> list[dict]:
    metrics = {
        "cpu_pct": sample.get("cpu_pct"), "load_per_core": sample.get("load_per_core"),
        "mem_pct": sample.get("mem_pct"), "swap_pct": sample.get("swap_pct"),
        "disk_pct": sample.get("disk_pct"), "api_ms": sample.get("api_ms"),
        "latency_p95_ms": sample.get("req_p95_ms"), "error_rate_5xx_pct": sample.get("err5xx_pct"),
        "db_conn_pct": sample.get("db_conn_pct"), "queue_depth": sample.get("queue_depth"),
        "ssl_days_left": sample.get("ssl_days_left"),
    }
    alerts = []
    for metric, value in metrics.items():
        sev = severity(metric, value, thresholds[metric])
        if sev:
            alerts.append({"key": metric, "severity": sev, "value": value, "threshold": thresholds[metric][0 if sev == "warning" else 1],
                           "message": f"{metric} is {value} ({sev} threshold {thresholds[metric][0 if sev == 'warning' else 1]})"})
    for name, st in services.items():
        if not st.get("up"):
            alerts.append({"key": f"service:{name}", "severity": "critical", "value": st.get("status"), "threshold": None,
                           "message": f"Service {name} is down ({st.get('status')})"})
    return alerts


class AlertManager:
    def __init__(self) -> None:
        self.active: dict[str, dict] = {}
        try:
            for a in json.loads((DATA / "alerts.json").read_text()).get("active", []):
                self.active[a["key"]] = a
        except (OSError, ValueError, KeyError):
            pass

    def update(self, current: list[dict]) -> None:
        keys = {a["key"] for a in current}
        events = []
        for a in current:
            prev = self.active.get(a["key"])
            if not prev or prev["severity"] != a["severity"]:
                a["since"] = iso()
                events.append({"event": "fired", "ts": iso(), **a})
            else:
                a["since"] = prev.get("since", iso())
            self.active[a["key"]] = a
        for key in list(self.active):
            if key not in keys:
                events.append({"event": "resolved", "ts": iso(), "key": key, "severity": self.active[key]["severity"],
                               "message": self.active[key]["message"]})
                del self.active[key]
        if events:
            with open(DATA / "alerts.jsonl", "a") as fh:
                for e in events:
                    fh.write(json.dumps(e) + "\n")
            self.notify(events)
        atomic_write(DATA / "alerts.json", json.dumps({"updated": iso(), "active": list(self.active.values())}))

    @staticmethod
    def notify(events: list[dict]) -> None:
        if not WEBHOOK.startswith("https://"):
            return
        text = "\n".join(f"[{e['event'].upper()}] {e['severity']}: {e['message']}" for e in events)
        try:
            req = urllib.request.Request(WEBHOOK, data=json.dumps({"text": text}).encode(),
                                         headers={"Content-Type": "application/json"})
            urllib.request.urlopen(req, timeout=5).close()  # noqa: S310
        except Exception:
            pass


# ── series storage ──────────────────────────────────────────────────────────
NUMERIC_KEYS_AVG = None


def rollup(samples: list[dict]) -> dict:
    """Average numeric fields, sum request counters."""
    out = {"ts": samples[-1]["ts"]}
    sums = {"req", "s2xx", "s3xx", "s4xx", "s5xx"}
    for k in samples[-1]:
        if k == "ts":
            continue
        vals = [s[k] for s in samples if isinstance(s.get(k), (int, float)) and not isinstance(s.get(k), bool)]
        if not vals:
            out[k] = samples[-1][k]
        elif k in sums:
            out[k] = sum(vals)
        elif k.startswith(("srv_", "db_up")) or isinstance(samples[-1][k], bool):
            out[k] = min(vals)
        else:
            out[k] = round(sum(vals) / len(vals), 2)
    return out


def append_line(path: Path, obj: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with open(path, "a") as fh:
        fh.write(json.dumps(obj, separators=(",", ":")) + "\n")


def prune(path: Path, keep_s: int) -> None:
    if not path.exists():
        return
    cutoff = now() - keep_s
    kept = []
    for line in path.read_text().splitlines():
        try:
            if json.loads(line)["ts"] >= cutoff:
                kept.append(line)
        except (ValueError, KeyError):
            continue
    atomic_write(path, "\n".join(kept) + ("\n" if kept else ""))


# ── main loop ───────────────────────────────────────────────────────────────
def main() -> None:
    DATA.mkdir(parents=True, exist_ok=True)
    CONFIG.mkdir(parents=True, exist_ok=True)
    procs, nginx, alerts = ProcSampler(), NginxTail(NGINX_LOG), AlertManager()
    prev_cpu = read_cpu_times()
    prev_net, prev_net_t = net_counters(), now()
    prev_db: tuple[int, int, float] | None = None
    infra, infra_t = {}, 0.0
    heavy: dict = {"db": {"up": False}, "queue": {"up": False}, "services": {}, "lat": None}
    heavy_t = 0.0
    minute: list[dict] = []
    five: list[dict] = []
    req_acc = {"req": 0, "s2xx": 0, "s3xx": 0, "s4xx": 0, "s5xx": 0, "times": []}
    last_min, last_prune = now(), 0.0
    while True:
        t = now()
        cpu_now = read_cpu_times()
        dtot, didle = cpu_now[0] - prev_cpu[0], cpu_now[1] - prev_cpu[1]
        cpu_pct = round(100 * (dtot - didle) / dtot, 1) if dtot > 0 else 0.0
        prev_cpu = cpu_now
        mem = parse_meminfo(Path("/proc/meminfo").read_text())
        load = os.getloadavg()
        ncpu = os.cpu_count() or 1
        net, dt_net = net_counters(), max(t - prev_net_t, 1)
        rx_bps, tx_bps = max(net[0] - prev_net[0], 0) / dt_net, max(net[1] - prev_net[1], 0) / dt_net
        prev_net, prev_net_t = net, t
        dsk = disks()
        root = next((d for d in dsk if d["mount"] == "/"), dsk[0] if dsk else None)
        api_ok, api_ms, api_code = http_probe(API_URL)
        fe_ok, fe_ms, _ = http_probe(FRONTEND_URL)
        lg = nginx.read()
        for k in ("req", "s2xx", "s3xx", "s4xx", "s5xx"):
            req_acc[k] += lg.get(k, 0)
        req_acc["times"] += lg["times"]
        proc_list = procs.sample()
        if t - heavy_t >= 60:
            heavy = {"db": db_stats(), "queue": queue_stats(), "services": services_state(), "lat": tcp_latency_ms(LATENCY_TARGET)}
            heavy_t = t
        if t - infra_t >= 600 or not infra:
            infra, infra_t = infrastructure(), t
        db = heavy["db"]
        qps = None
        if db.get("up"):
            if prev_db and db["questions"] >= prev_db[0]:
                qps = round((db["questions"] - prev_db[0]) / max(t - prev_db[2], 1), 1)
            slow_delta = db["slow_queries"] - prev_db[1] if prev_db and db["slow_queries"] >= prev_db[1] else 0
            prev_db = (db["questions"], db["slow_queries"], t)
        else:
            slow_delta = 0
        services = dict(heavy["services"])
        services["api"] = {**services.get("api", {}), "up": api_ok and services.get("api", {}).get("up", True)}
        services["frontend"] = {**services.get("frontend", {}), "up": fe_ok}
        err_pct = round(100 * req_acc["s5xx"] / req_acc["req"], 2) if req_acc["req"] else 0.0
        sample = {
            "ts": int(t), "cpu_pct": cpu_pct, "load1": round(load[0], 2), "load5": round(load[1], 2), "load15": round(load[2], 2),
            "load_per_core": round(load[0] / ncpu, 2), "mem_pct": mem["mem_pct"], "mem_used": mem["mem_used"],
            "swap_pct": mem["swap_pct"], "disk_pct": root["pct"] if root else None,
            "net_rx_bps": round(rx_bps), "net_tx_bps": round(tx_bps), "net_latency_ms": heavy["lat"],
            "api_ms": api_ms if api_ok else None, "api_up": api_ok, "frontend_up": fe_ok,
            "req": req_acc["req"], "s2xx": req_acc["s2xx"], "s3xx": req_acc["s3xx"], "s4xx": req_acc["s4xx"], "s5xx": req_acc["s5xx"],
            "err5xx_pct": err_pct, "req_p50_ms": percentile(req_acc["times"], 0.5), "req_p95_ms": percentile(req_acc["times"], 0.95),
            "db_up": bool(db.get("up")), "db_conn": db.get("threads_connected"), "db_running": db.get("threads_running"),
            "db_conn_pct": db.get("conn_pct"), "db_qps": qps, "db_slow": slow_delta,
            "queue_depth": heavy["queue"].get("depth"), "ssl_days_left": infra.get("ssl", {}).get("days_left"),
        }
        thresholds = load_thresholds()
        alerts.update(evaluate(sample, services, thresholds))
        current = {
            "updated": iso(t), "interval": INTERVAL, "agent_pid": os.getpid(), "sample": sample,
            "server": {"uptime_s": int(float(Path("/proc/uptime").read_text().split()[0])), "load": [*load], "cpu_cores": ncpu,
                       "memory": mem, "disks": dsk, "processes": proc_list,
                       "net": {"rx_bps": sample["net_rx_bps"], "tx_bps": sample["net_tx_bps"], "latency_ms": heavy["lat"]}},
            "application": {"api": {"up": api_ok, "ms": api_ms, "code": api_code}, "frontend": {"up": fe_ok, "ms": fe_ms},
                            "database": db, "queue": heavy["queue"], "services": services,
                            "http": {"nginx_log_available": lg.get("available", False)}},
            "infrastructure": infra, "thresholds": thresholds,
        }
        atomic_write(DATA / "current.json", json.dumps(current))
        minute.append(sample)
        if t - last_min >= 60:
            agg = rollup(minute)
            for k in ("req", "s2xx", "s3xx", "s4xx", "s5xx"):
                agg[k] = req_acc[k]
            agg["req_p50_ms"], agg["req_p95_ms"] = percentile(req_acc["times"], 0.5), percentile(req_acc["times"], 0.95)
            agg["err5xx_pct"] = err_pct
            append_line(DATA / "series-1m.jsonl", agg)
            five.append(agg)
            minute, last_min = [], t
            req_acc = {"req": 0, "s2xx": 0, "s3xx": 0, "s4xx": 0, "s5xx": 0, "times": []}
            if len(five) >= 5:
                append_line(DATA / "series-5m.jsonl", rollup(five))
                five = []
        if t - last_prune > 3600:
            prune(DATA / "series-1m.jsonl", RAW_RETENTION_S)
            prune(DATA / "series-5m.jsonl", ROLLUP_RETENTION_S)
            last_prune = t
        time.sleep(max(1, INTERVAL - (now() - t)))


if __name__ == "__main__":
    if os.name == "nt":
        sys.exit("The monitoring agent needs Linux (/proc). Run it on the server.")
    try:
        main()
    except KeyboardInterrupt:
        pass
