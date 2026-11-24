#!/usr/bin/env python3
"""Deployment quality gate and deployment history (Python 3 standard library only).

  deployctl.py begin  --id ID --commit SHA --branch B --trigger T [--actor A] [--prev-commit SHA]
  deployctl.py stage  --id ID --stage NAME
  deployctl.py finish --id ID --status success|blocked|failed|rolled_back --reason TEXT [--rollback-to SHA]
  deployctl.py gate   --mode ci|server [--id ID] [--image IMG] ...   exit 0 = every mandatory check passed

History lives in $MON_DIR/data/deployments/<id>/ (record.json, gate-report.json, gate-report.md,
gate.log, deploy.log). A deployment is only ever allowed to proceed when `gate` exits 0.
"""
from __future__ import annotations

import argparse
import contextlib
import json
import os
import re
import secrets
import shutil
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from pathlib import Path

MON_DIR = Path(os.environ.get("MON_DIR", "/opt/inventory/monitor"))
HISTORY_KEEP = 200
ID_RE = re.compile(r"^[0-9]{8}T[0-9]{6}Z-[0-9a-f]{6}$")

REDACTIONS = [
    (re.compile(r"(?i)\b(Bearer)\s+[A-Za-z0-9._\-|]{16,}"), r"\1 ***"),
    (re.compile(r"(x-access-token:)[^@\s]+"), r"\1***"),
    (re.compile(r"(?i)((?:password|passwd|secret|token|api[_-]?key|authorization)[\"']?\s*[=:]\s*)[^\s\"',;]+"), r"\1***"),
    (re.compile(r"base64:[A-Za-z0-9+/=]{20,}"), "base64:***"),
    (re.compile(r"\b[A-Fa-f0-9]{40,}\b"), "***"),
]


def redact(text: str) -> str:
    for pattern, repl in REDACTIONS:
        text = pattern.sub(repl, text)
    return text


def now_iso() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def new_id() -> str:
    return datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ") + "-" + secrets.token_hex(3)


def dep_dir(dep_id: str, base: Path | None = None) -> Path:
    if not ID_RE.match(dep_id):
        sys.exit(f"invalid deployment id: {dep_id}")
    return (base or MON_DIR / "data" / "deployments") / dep_id


def read_record(d: Path) -> dict:
    try:
        return json.loads((d / "record.json").read_text())
    except (OSError, ValueError):
        return {}


def write_record(d: Path, rec: dict) -> None:
    d.mkdir(parents=True, exist_ok=True)
    tmp = d / "record.json.tmp"
    tmp.write_text(json.dumps(rec, indent=2))
    os.replace(tmp, d / "record.json")


# ── history commands ────────────────────────────────────────────────────────
def cmd_begin(a: argparse.Namespace) -> int:
    d = dep_dir(a.id)
    if (d / "record.json").exists():
        return 0  # re-exec of deploy.sh into the freshly pulled version: keep the original record
    write_record(d, {
        "id": a.id, "status": "running", "stage": "starting", "commit": a.commit, "prev_commit": a.prev_commit or "",
        "branch": a.branch, "trigger": a.trigger, "actor": a.actor or "", "started_at": now_iso(), "completed_at": None,
        "duration_s": None, "reason": "", "gate": None, "rollback": {"performed": False},
    })
    return 0


def cmd_stage(a: argparse.Namespace) -> int:
    d = dep_dir(a.id)
    rec = read_record(d)
    if rec:
        rec["stage"] = a.stage
        if a.commit:
            rec["commit"] = a.commit
            rec["subject"] = a.subject or ""
        write_record(d, rec)
    return 0


def cmd_finish(a: argparse.Namespace) -> int:
    d = dep_dir(a.id)
    rec = read_record(d)
    if not rec or rec.get("completed_at"):
        return 0
    rec["status"], rec["reason"] = a.status, redact(a.reason or "")
    rec["completed_at"] = now_iso()
    start = datetime.strptime(rec["started_at"], "%Y-%m-%dT%H:%M:%SZ").replace(tzinfo=timezone.utc)
    rec["duration_s"] = int((datetime.now(timezone.utc) - start).total_seconds())
    if a.status == "rolled_back":
        rec["rollback"] = {"performed": True, "to_commit": a.rollback_to or rec.get("prev_commit", ""), "at": now_iso(),
                           "reason": rec["reason"]}
    write_record(d, rec)
    prune_history(d.parent)
    return 0


def prune_history(base: Path) -> None:
    dirs = sorted(p for p in base.iterdir() if p.is_dir() and ID_RE.match(p.name))
    for old in dirs[:-HISTORY_KEEP]:
        shutil.rmtree(old, ignore_errors=True)


# ── gate ────────────────────────────────────────────────────────────────────
JOURNEY_FILES = ("AuthFlow", "CartSubmission", "ChainOrder", "OrderConfirmation", "ProspectApprovalFlow",
                 "TenantProvisioning", "ImpersonationService", "NetworkOrdering", "InvoiceService", "PaymentService")
SECURITY_FILES = ("Security", "PasswordHashing", "MiddlewarePipeline", "TenantIsolation", "CatalogVisibility",
                  "ImpersonationService", "AppendOnly")


def categorize(file: str, suite: str) -> list[str]:
    cats = {"unit": ["unit"], "feature": ["integration", "regression"], "property": ["data_integrity"],
            "smoke": ["smoke"], "security": ["security"]}.get(suite.lower(), [suite.lower()])
    cats = list(cats)
    if any(k in file for k in JOURNEY_FILES):
        cats.append("e2e_journey")
    if any(k in file for k in SECURITY_FILES) and "security" not in cats:
        cats.append("security")
    return cats


class Gate:
    def __init__(self, a: argparse.Namespace) -> None:
        self.mode = a.mode
        self.src = Path(a.src).resolve()
        self.backend = self.src / "backend"
        self.frontend = self.src / "frontend"
        self.image = a.image
        self.backend_env = Path(a.backend_env) if a.backend_env else None
        self.network = a.network
        self.out = Path(a.out or (dep_dir(a.id) if a.id else Path("gate-out"))).resolve()
        self.out.mkdir(parents=True, exist_ok=True)
        self.log = open(self.out / "gate.log", "a", encoding="utf-8")
        self.checks: list[dict] = []
        self.categories: dict[str, dict] = {}
        self.started = time.time()
        self.id = a.id
        self.commit = a.commit or ""
        self.phpunit_junit = self.out / "junit"
        self.phpunit_junit.mkdir(exist_ok=True)
        self.app_key = "base64:" + __import__("base64").b64encode(secrets.token_bytes(32)).decode()
        self.skip_frontend = a.skip_frontend

    # -- plumbing
    def say(self, text: str) -> None:
        self.log.write(redact(text) + "\n")
        self.log.flush()
        print(redact(text), flush=True)

    def sh(self, cmd: list[str], cwd: Path | None = None, env: dict | None = None, timeout: int = 1800) -> tuple[int, str]:
        self.say(f"$ {' '.join(cmd[:12])}{' …' if len(cmd) > 12 else ''}")
        cmd = [shutil.which(cmd[0]) or cmd[0], *cmd[1:]]
        try:
            p = subprocess.run(cmd, cwd=cwd, env={**os.environ, **(env or {})}, capture_output=True, text=True,
                               timeout=timeout, check=False)
            out = (p.stdout or "") + (p.stderr or "")
            rc = p.returncode
        except subprocess.TimeoutExpired as e:
            out, rc = f"timed out after {timeout}s\n{(e.stdout or b'')[-2000:] if isinstance(e.stdout, bytes) else ''}", 124
        except OSError as e:
            out, rc = str(e), 127
        tail = "\n".join(out.splitlines()[-60:])
        self.say(tail)
        return rc, out

    def add(self, name: str, category: str, mandatory: bool, status: str, detail: str = "", started: float | None = None,
            tests: dict | None = None, failures: list[str] | None = None) -> None:
        self.checks.append({"name": name, "category": category, "mandatory": mandatory, "status": status,
                            "detail": redact(detail)[:600], "duration_s": round(time.time() - (started or time.time()), 1),
                            "tests": tests, "failures": [redact(f) for f in (failures or [])][:25]})
        self.say(f"[{status.upper():7}] {'MANDATORY' if mandatory else 'optional '} {name}  {detail[:160]}")

    def simple(self, name: str, category: str, mandatory: bool, cmd: list[str], cwd: Path | None = None,
               env: dict | None = None, timeout: int = 1800) -> bool:
        t0 = time.time()
        rc, out = self.sh(cmd, cwd, env, timeout)
        ok = rc == 0
        self.add(name, category, mandatory, "passed" if ok else "failed",
                 "" if ok else f"exit {rc}: " + " | ".join(out.strip().splitlines()[-3:]), t0)
        return ok

    # -- test suites
    def parse_junit(self, path: Path, suite: str) -> tuple[dict, list[str]]:
        totals, failures = {"passed": 0, "failed": 0, "skipped": 0, "total": 0}, []
        try:
            root = ET.parse(path).getroot()
        except (OSError, ET.ParseError):
            return totals, ["junit report missing or unreadable"]
        for tc in root.iter("testcase"):
            file = tc.get("file") or tc.get("classname") or ""
            status = "failed" if (tc.find("failure") is not None or tc.find("error") is not None) else \
                     "skipped" if tc.find("skipped") is not None else "passed"
            totals[status] += 1
            totals["total"] += 1
            if status == "failed":
                failures.append(f"{Path(file).name}: {tc.get('name')}")
            for cat in categorize(file, suite):
                c = self.categories.setdefault(cat, {"passed": 0, "failed": 0, "skipped": 0, "total": 0})
                c[status] += 1
                c["total"] += 1
        return totals, failures

    def pest(self, suite: str, mandatory: bool = True, env: dict | None = None, label: str = "") -> bool:
        t0 = time.time()
        name = f"backend.{suite.lower()}{label}"
        junit = self.phpunit_junit / f"{suite.lower()}{label}.xml"
        if self.mode == "server":
            cmd = ["docker", "run", "--rm", "--entrypoint", "", "-e", f"APP_KEY={self.app_key}", "-e", "APP_ENV=testing"]
            for k, v in (env or {}).items():
                cmd += ["-e", f"{k}={v}"]
            if self.network and env:
                cmd += ["--network", self.network]
            cmd += ["-v", f"{self.backend / 'tests'}:/app/tests:ro", "-v", f"{self.phpunit_junit}:/out", self.image,
                    "php", "-d", "memory_limit=1024M", "vendor/bin/pest", f"--testsuite={suite}",
                    f"--log-junit=/out/{junit.name}", "--colors=never"]
            rc, out = self.sh(cmd, timeout=3600)
        else:
            cmd = ["php", "-d", "memory_limit=1024M", "vendor/bin/pest", f"--testsuite={suite}", f"--log-junit={junit}", "--colors=never"]
            rc, out = self.sh(cmd, self.backend, {"APP_KEY": self.app_key, **(env or {})}, 3600)
        totals, failures = self.parse_junit(junit, suite)
        if totals["total"] == 0:
            self.add(name, f"tests:{suite}", mandatory, "failed", "no tests were executed (report missing or empty)", t0, totals)
            return False
        ok = rc == 0 and totals["failed"] == 0
        self.add(name, f"tests:{suite}", mandatory, "passed" if ok else "failed",
                 f"{totals['passed']} passed, {totals['failed']} failed, {totals['skipped']} skipped", t0, totals, failures)
        return ok

    # -- checks
    def check_config(self) -> None:
        t0 = time.time()
        required = ["APP_KEY", "APP_URL", "DB_HOST", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD", "REDIS_HOST", "CENTRAL_DOMAINS"]
        if self.mode == "ci":
            keys = {l.split("=", 1)[0].strip() for l in (self.backend / ".env.example").read_text().splitlines()
                    if "=" in l and not l.lstrip().startswith("#")}
            missing = [k for k in required if k not in keys]
            self.add("config.env_template", "config", True, "failed" if missing else "passed",
                     f"missing in .env.example: {', '.join(missing)}" if missing else "all required keys documented", t0)
            return
        kv: dict[str, str] = {}
        try:
            for l in self.backend_env.read_text().splitlines():  # type: ignore[union-attr]
                if "=" in l and not l.lstrip().startswith("#"):
                    k, v = l.split("=", 1)
                    kv[k.strip()] = v.strip().strip("'\"")
        except (OSError, AttributeError):
            self.add("config.env", "config", True, "failed", "backend.env is not readable", t0)
            return
        problems = [f"{k} is empty" for k in required if not kv.get(k)]
        if kv.get("APP_ENV") != "production":
            problems.append("APP_ENV is not production")
        if kv.get("APP_DEBUG", "").lower() != "false":
            problems.append("APP_DEBUG is not false")
        if not kv.get("APP_URL", "").startswith("https://"):
            problems.append("APP_URL is not https")
        self.add("config.env", "config", True, "failed" if problems else "passed",
                 "; ".join(problems) if problems else "required variables present, production-safe values (values not logged)", t0)

    def check_migrations_static(self) -> None:
        t0 = time.time()
        files = sorted(p.name for p in (self.backend / "database" / "migrations").glob("*.php"))
        names = [re.sub(r"^\d{4}_\d{2}_\d{2}_\d{6}_", "", f) for f in files]
        bad = [f for f in files if not re.match(r"^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.php$", f)]
        dups = sorted({n for n in names if names.count(n) > 1})
        problems = [f"bad name: {b}" for b in bad] + [f"duplicate migration: {d}" for d in dups]
        self.add("db.migration_files", "database", True, "failed" if problems or not files else "passed",
                 "; ".join(problems) if problems else f"{len(files)} migrations, unique and ordered", t0)

    def check_dependencies(self) -> None:
        if self.mode == "ci":
            self.simple("deps.composer_lock", "dependencies", True, ["composer", "validate", "--no-check-publish", "--no-interaction"], self.backend)
            return
        t0 = time.time()
        rc, out = self.sh(["docker", "run", "--rm", "--entrypoint", "", self.image, "php", "-m"])
        have = {l.strip().lower() for l in out.splitlines()}
        missing = [e for e in ("pdo_mysql", "bcmath", "zip", "gd", "pcntl", "mbstring", "openssl") if e not in have]
        self.add("deps.php_extensions", "dependencies", True, "failed" if rc or missing else "passed",
                 f"missing: {', '.join(missing)}" if missing else "all required PHP extensions present in the built image", t0)
        lock_ok = (self.frontend / "package-lock.json").exists() and (self.backend / "composer.lock").exists()
        self.add("deps.lockfiles", "dependencies", True, "passed" if lock_ok else "failed",
                 "composer.lock and package-lock.json present" if lock_ok else "a lock file is missing", t0)

    def check_server_db(self) -> None:
        t0 = time.time()
        net_ok = bool(self.network) and self.sh(["docker", "network", "inspect", self.network])[0] == 0
        if not (self.backend_env and net_ok):
            self.add("db.connectivity", "database", False, "skipped", "no running stack yet (first install): nothing to connect to", t0)
            return
        rc, out = self.sh(["docker", "run", "--rm", "--network", self.network, "--entrypoint", "",
                           "-v", f"{self.backend_env}:/app/.env:ro", self.image, "php", "artisan", "migrate:status",
                           "--database=central", "--no-interaction"], timeout=120)
        pending = len(re.findall(r"\bPending\b", out))
        self.add("db.connectivity", "database", True, "passed" if rc == 0 else "failed",
                 f"new image reaches the production database read-only; {pending} migration(s) pending" if rc == 0
                 else "new image cannot read the production database: " + " | ".join(out.strip().splitlines()[-2:]), t0)

    def check_frontend(self) -> None:
        if self.skip_frontend:
            self.add("frontend.checks", "frontend", False, "skipped", "WAIVED by the operator (GATE_SKIP_FRONTEND=1); recorded in this report", time.time())
            return
        if self.mode == "ci":
            ok = self.simple("frontend.typecheck", "frontend", True, ["npm", "run", "typecheck"], self.frontend)
            self.frontend_test(self.frontend)
            self.simple("frontend.build", "frontend", True, ["npm", "run", "build"], self.frontend,
                        {"NEXT_PUBLIC_API_BASE_URL": ""}, 1800)
            return
        # Server: throwaway Node container with the source mounted read-only and an npm cache volume.
        t0 = time.time()
        script = ("set -e; cp -r /src /work; cd /work; npm ci --no-audit --no-fund --prefer-offline >/dev/null; "
                  "npm run typecheck; npx vitest run --reporter=junit --outputFile=/out/frontend.xml")
        rc, out = self.sh(["docker", "run", "--rm", "-v", f"{self.frontend}:/src:ro", "-v", f"{self.phpunit_junit}:/out",
                           "-v", "inventory-gate-npm:/root/.npm", "node:20-alpine", "sh", "-c", script], timeout=2400)
        totals, failures = self.parse_junit(self.phpunit_junit / "frontend.xml", "frontend")
        ok = rc == 0 and totals["total"] > 0 and totals["failed"] == 0
        self.add("frontend.typecheck_and_tests", "frontend", True, "passed" if ok else "failed",
                 f"{totals['passed']} passed, {totals['failed']} failed, {totals['skipped']} skipped" if totals["total"]
                 else " | ".join(out.strip().splitlines()[-3:]), t0, totals, failures)

    def frontend_test(self, cwd: Path) -> None:
        t0 = time.time()
        junit = self.phpunit_junit / "frontend.xml"
        rc, out = self.sh(["npx", "vitest", "run", "--reporter=junit", f"--outputFile={junit}"], cwd)
        totals, failures = self.parse_junit(junit, "frontend")
        ok = rc == 0 and totals["total"] > 0 and totals["failed"] == 0
        self.add("frontend.vitest", "frontend", True, "passed" if ok else "failed",
                 f"{totals['passed']} passed, {totals['failed']} failed, {totals['skipped']} skipped", t0, totals, failures)

    @contextlib.contextmanager
    def throwaway_mysql(self):
        """Yields env vars for a disposable MySQL (CI service container or a local tmpfs container)."""
        host = os.environ.get("GATE_MYSQL_HOST")
        env = {"DB_CONNECTION": "central", "DB_DATABASE": "inventory_central", "DB_USERNAME": "root"}
        if host:
            yield {**env, "DB_HOST": host, "DB_PORT": os.environ.get("GATE_MYSQL_PORT", "3306"),
                   "DB_PASSWORD": os.environ.get("GATE_MYSQL_PASSWORD", "")}
            return
        if self.mode != "server":
            yield None
            return
        name, net, pw = f"gate-mysql-{secrets.token_hex(3)}", f"gate-net-{secrets.token_hex(3)}", secrets.token_hex(8)
        self.sh(["docker", "network", "create", net])
        rc, _ = self.sh(["docker", "run", "-d", "--name", name, "--network", net, "--tmpfs", "/var/lib/mysql", "--memory", "768m",
                         "-e", f"MYSQL_ROOT_PASSWORD={pw}", "-e", "MYSQL_DATABASE=inventory_central", "mysql:8.0",
                         "--skip-log-bin", "--performance-schema=OFF", "--innodb-buffer-pool-size=128M"])
        try:
            ready = False
            for _ in range(60):
                time.sleep(3)
                r, _ = self.sh(["docker", "exec", name, "mysqladmin", "ping", "-h", "127.0.0.1", f"-uroot", f"-p{pw}", "--silent"], timeout=15)
                if r == 0:
                    ready = True
                    break
            self.network = net
            yield ({**env, "DB_HOST": name, "DB_PORT": "3306", "DB_PASSWORD": pw} if rc == 0 and ready else None)
        finally:
            self.sh(["docker", "rm", "-f", name])
            self.sh(["docker", "network", "rm", net])

    def mysql_integration(self, mandatory: bool) -> None:
        t0 = time.time()
        if os.environ.get("GATE_MYSQL", "on" if self.mode == "ci" else "off") != "on":
            self.add("backend.mysql_integration", "database", False, "skipped", "disabled (set GATE_MYSQL=on to run against a throwaway MySQL)", t0)
            return
        with self.throwaway_mysql() as env:
            if not env:
                reason = "no MySQL service configured (set GATE_MYSQL_HOST)" if self.mode == "ci" else "throwaway MySQL did not become ready"
                self.add("backend.mysql_integration", "database", mandatory, "failed" if self.mode == "server" else "skipped", reason, t0)
                return
            if self.mode == "ci":
                rc, out = self.sh(["php", "artisan", "migrate", "--force"], self.backend, {**env, "APP_KEY": self.app_key, "APP_ENV": "testing"})
            else:
                rc, out = self.sh(["docker", "run", "--rm", "--network", self.network, "--entrypoint", "", "-e", f"APP_KEY={self.app_key}",
                                   *sum((["-e", f"{k}={v}"] for k, v in env.items()), []), self.image, "php", "artisan", "migrate", "--force"])
            if rc != 0:
                self.add("backend.mysql_integration", "database", mandatory, "failed", "migrations failed on MySQL: " + " | ".join(out.strip().splitlines()[-2:]), t0)
                return
            self.add("db.migrate_fresh_mysql", "database", True, "passed", "all migrations apply cleanly on an empty MySQL", t0)
            for suite in ("Feature", "Smoke", "Property"):
                self.pest(suite, mandatory, env, "_mysql")

    def run(self) -> int:
        self.say(f"== quality gate ({self.mode}) {self.id or ''} commit {self.commit} ==")
        self.check_config()
        self.check_migrations_static()
        self.check_dependencies()
        if self.mode == "server":
            self.check_server_db()
        self.simple("monitoring.agent_tests", "monitoring", True, [sys.executable, "-m", "unittest", "test_agent", "-q"], self.src / "deploy" / "monitor")
        self.simple("gate.self_tests", "monitoring", True, [sys.executable, "-m", "unittest", "test_deployctl", "-q"], self.src / "deploy" / "gate")
        for suite in ("Unit", "Smoke", "Feature", "Property", "Security"):
            if (self.backend / "tests" / suite).is_dir():
                self.pest(suite)
        self.mysql_integration(os.environ.get("GATE_MYSQL_MANDATORY", "false") == "true")
        self.check_frontend()
        return self.finish()

    def finish(self) -> int:
        tot = {"passed": 0, "failed": 0, "skipped": 0, "total": 0}
        for c in self.checks:
            for k in tot:
                tot[k] += (c["tests"] or {}).get(k, 0)
        blockers = [c for c in self.checks if c["mandatory"] and c["status"] != "passed"]
        verdict = "pass" if not blockers else "fail"
        report = {"id": self.id, "mode": self.mode, "commit": self.commit, "started_at": datetime.fromtimestamp(self.started, timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
                  "finished_at": now_iso(), "duration_s": round(time.time() - self.started), "verdict": verdict,
                  "totals": tot, "checks_total": len(self.checks), "blocking": [c["name"] for c in blockers],
                  "categories": self.categories, "checks": self.checks}
        (self.out / "gate-report.json").write_text(json.dumps(report, indent=2))
        (self.out / "gate-report.md").write_text(markdown(report))
        if self.id:
            rec = read_record(dep_dir(self.id))
            if rec:
                rec["gate"] = {"verdict": verdict, "passed": tot["passed"], "failed": tot["failed"], "skipped": tot["skipped"],
                               "total": tot["total"], "blocking": report["blocking"], "duration_s": report["duration_s"]}
                write_record(dep_dir(self.id), rec)
        self.say(f"== gate {verdict.upper()}: {tot['passed']} passed, {tot['failed']} failed, {tot['skipped']} skipped; blocking: {report['blocking'] or 'none'} ==")
        self.log.close()
        return 0 if verdict == "pass" else 1


def markdown(r: dict) -> str:
    t = r["totals"]
    lines = [f"## Deployment quality gate: {'PASSED' if r['verdict'] == 'pass' else 'FAILED'}", "",
             f"Commit `{r['commit']}` · mode `{r['mode']}` · {r['duration_s']}s", "",
             f"**Tests:** {t['passed']} passed, {t['failed']} failed, {t['skipped']} skipped (of {t['total']})", "",
             "| Check | Required | Result | Detail |", "|---|---|---|---|"]
    for c in r["checks"]:
        lines.append(f"| {c['name']} | {'yes' if c['mandatory'] else 'no'} | {c['status']} | {c['detail'].replace('|', '/')[:140]} |")
    if r["categories"]:
        lines += ["", "| Category | Passed | Failed | Skipped |", "|---|---|---|---|"]
        lines += [f"| {k} | {v['passed']} | {v['failed']} | {v['skipped']} |" for k, v in sorted(r["categories"].items())]
    fails = [f"- {c['name']}: {f}" for c in r["checks"] for f in c["failures"]]
    if fails:
        lines += ["", "### Failed tests", *fails]
    return "\n".join(lines) + "\n"


def cmd_gate(a: argparse.Namespace) -> int:
    if a.mode == "server" and not a.image:
        sys.exit("--image is required in server mode")
    return Gate(a).run()


def main() -> int:
    p = argparse.ArgumentParser()
    sub = p.add_subparsers(dest="cmd", required=True)
    b = sub.add_parser("begin")
    b.add_argument("--id", required=True); b.add_argument("--commit", default="unknown"); b.add_argument("--branch", default="")
    b.add_argument("--trigger", default="manual"); b.add_argument("--actor", default=""); b.add_argument("--prev-commit", default="")
    b.set_defaults(fn=cmd_begin)
    s = sub.add_parser("stage")
    s.add_argument("--id", required=True); s.add_argument("--stage", required=True); s.add_argument("--commit", default="")
    s.add_argument("--subject", default="")
    s.set_defaults(fn=cmd_stage)
    f = sub.add_parser("finish")
    f.add_argument("--id", required=True); f.add_argument("--status", required=True, choices=["success", "blocked", "failed", "rolled_back"])
    f.add_argument("--reason", default=""); f.add_argument("--rollback-to", default="")
    f.set_defaults(fn=cmd_finish)
    g = sub.add_parser("gate")
    g.add_argument("--mode", required=True, choices=["ci", "server"]); g.add_argument("--id", default="")
    g.add_argument("--src", default="."); g.add_argument("--image", default=""); g.add_argument("--backend-env", default="")
    g.add_argument("--network", default=""); g.add_argument("--out", default=""); g.add_argument("--commit", default="")
    g.add_argument("--skip-frontend", action="store_true")
    g.set_defaults(fn=cmd_gate)
    a = p.parse_args()
    return a.fn(a)


if __name__ == "__main__":
    sys.exit(main())
