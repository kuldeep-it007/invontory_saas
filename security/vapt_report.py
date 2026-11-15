#!/usr/bin/env python3
"""
VAPT report + deployment gate (standard library only).

Reads the raw output of every scanner from --inputs, normalizes each finding to
CRITICAL / HIGH / MEDIUM / LOW, applies approved exceptions and an optional
authorized override, and writes:

    report.json   machine-readable findings + decision
    report.html   self-contained human report (attached to the email)
    summary.md    short summary for the GitHub job summary
    summary.html  short summary used as the email body

Exit code: 0 = deploy allowed, 2 = blocked (unexcepted findings at a blocking
severity, or an invalid override), 1 = the script itself failed.

Input file names (any missing file is reported as "not run", never as clean):
    composer-audit.json  npm-audit-prod.json  npm-audit-all.json
    gitleaks.json  semgrep.json  trivy-config.json  trivy-image-*.json
    zap-*.json  testssl-*.json  nmap-*.xml  lynis-report.dat
"""

from __future__ import annotations

import argparse
import datetime as dt
import glob
import html
import json
import os
import sys
import xml.etree.ElementTree as ET

SEVERITIES = ["CRITICAL", "HIGH", "MEDIUM", "LOW"]
IST = dt.timezone(dt.timedelta(hours=5, minutes=30))


def norm(value: str | None, default: str = "MEDIUM") -> str:
    v = (value or "").strip().upper()
    mapping = {
        "CRITICAL": "CRITICAL", "HIGH": "HIGH", "ERROR": "HIGH",
        "MODERATE": "MEDIUM", "MEDIUM": "MEDIUM", "WARNING": "MEDIUM", "WARN": "MEDIUM",
        "LOW": "LOW", "INFO": "LOW", "NOTE": "LOW", "NEGLIGIBLE": "LOW",
    }
    return mapping.get(v, default)


def load_json(path: str):
    try:
        with open(path, encoding="utf-8") as fh:
            text = fh.read().strip()
        return json.loads(text) if text else None
    except (OSError, ValueError):
        return None


def finding(tool, fid, severity, title, location="", detail="", fix="", blocking=True, category=""):
    return {
        "tool": tool, "id": f"{tool}:{fid}", "severity": severity, "title": title,
        "location": location, "detail": detail[:600], "fix": fix, "category": category,
        "blocking_eligible": blocking,
    }


# ── parsers ─────────────────────────────────────────────────────────────────

def parse_composer(data):
    out = []
    advisories = (data or {}).get("advisories") or {}
    # Composer prints "advisories": [] (a list) when there are none.
    for pkg, items in (advisories.items() if isinstance(advisories, dict) else []):
        for a in items if isinstance(items, list) else items.values():
            ident = a.get("cve") or a.get("advisoryId") or a.get("title")
            # Composer leaves severity blank for some advisories: treat as HIGH (conservative).
            out.append(finding("composer", f"{ident}:{pkg}", norm(a.get("severity"), "HIGH"), a.get("title", ""),
                               pkg, a.get("link", ""), f"update {pkg} (affected {a.get('affectedVersions', '?')})",
                               category="Dependency"))
    return out


def parse_npm(data, dev_only: bool):
    out = []
    for name, v in (data or {}).get("vulnerabilities", {}).items():
        for via in v.get("via", []):
            if not isinstance(via, dict):
                continue
            ident = (via.get("url") or "").rsplit("/", 1)[-1] or via.get("source") or via.get("title")
            out.append(finding("npm" + ("-dev" if dev_only else ""), f"{ident}:{name}", norm(via.get("severity")),
                               via.get("title", ""), name, via.get("url", ""),
                               f"fix: {json.dumps(v.get('fixAvailable'))}", blocking=not dev_only,
                               category="Dependency (dev only)" if dev_only else "Dependency"))
    return out


def parse_gitleaks(data):
    return [finding("gitleaks", leak.get("Fingerprint") or f"{leak.get('RuleID')}:{leak.get('File')}", "HIGH",
                    f"Possible secret: {leak.get('RuleID') or leak.get('Description')}",
                    f"{leak.get('File')}:{leak.get('StartLine')}", "value redacted", "remove and rotate the secret",
                    category="Exposed secret")
            for leak in (data or []) if isinstance(leak, dict)]


def parse_semgrep(data):
    out = []
    for r in (data or {}).get("results", []):
        extra = r.get("extra", {})
        meta = extra.get("metadata", {}) or {}
        owasp = meta.get("owasp")
        out.append(finding("semgrep", f"{r.get('check_id')}:{r.get('path')}:{r.get('start', {}).get('line')}",
                           norm(extra.get("severity")), (extra.get("message") or "").split("\n")[0][:200],
                           f"{r.get('path')}:{r.get('start', {}).get('line')}",
                           ", ".join(owasp) if isinstance(owasp, list) else str(owasp or ""),
                           category="Application code (SAST)"))
    return out


def parse_trivy(data, label):
    out = []
    for res in (data or {}).get("Results", []) or []:
        target = res.get("Target", "")
        for v in res.get("Vulnerabilities", []) or []:
            out.append(finding("trivy", f"{v.get('VulnerabilityID')}:{v.get('PkgName')}", norm(v.get("Severity")),
                               v.get("Title") or v.get("VulnerabilityID"), f"{label} {target} {v.get('PkgName')} {v.get('InstalledVersion')}",
                               v.get("PrimaryURL", ""), f"fixed in {v.get('FixedVersion') or 'n/a'}", category="Container image"))
        for m in res.get("Misconfigurations", []) or []:
            if m.get("Status") == "PASS":
                continue
            out.append(finding("trivy-config", f"{m.get('ID')}:{target}", norm(m.get("Severity")), m.get("Title", ""),
                               target, m.get("Message", ""), m.get("Resolution", ""), category="Configuration"))
        for s in res.get("Secrets", []) or []:
            out.append(finding("trivy-secret", f"{s.get('RuleID')}:{target}", norm(s.get("Severity"), "HIGH"),
                               s.get("Title", "Secret in image"), target, "value redacted", category="Exposed secret"))
    return out


def parse_zap(data, label):
    risk = {3: "HIGH", 2: "MEDIUM", 1: "LOW"}
    out = []
    for site in (data or {}).get("site", []) or []:
        for a in site.get("alerts", []) or []:
            code = int(a.get("riskcode", 0))
            if code == 0:
                continue
            uris = [i.get("uri", "") for i in a.get("instances", [])][:3]
            out.append(finding("zap", f"{a.get('pluginid')}:{site.get('@host')}", risk.get(code, "LOW"), a.get("alert", ""),
                               ", ".join(uris), html.unescape(a.get("desc", ""))[:300], html.unescape(a.get("solution", ""))[:300],
                               category=f"OWASP / API ({label})"))
    return out


def parse_testssl(data, label):
    items = data if isinstance(data, list) else (data or {}).get("scanResult", [])
    out = []
    for f in items or []:
        sev = (f.get("severity") or "").upper()
        if sev in ("OK", "INFO", "DEBUG", ""):
            continue
        out.append(finding("testssl", f"{f.get('id')}:{label}", norm(sev, "LOW"), f.get("id", ""), label,
                           f.get("finding", ""), category="TLS / server"))
    return out


def parse_nmap(path, allowed_ports):
    out = []
    try:
        root = ET.parse(path).getroot()
    except (OSError, ET.ParseError):
        return None
    for host in root.findall("host"):
        addr = (host.find("address").get("addr") if host.find("address") is not None else "?")
        for port in host.findall("./ports/port"):
            state = port.find("state")
            if state is None or state.get("state") != "open":
                continue
            p = port.get("portid")
            if p not in allowed_ports:
                svc = port.find("service")
                out.append(finding("nmap", f"open-port-{p}:{addr}", "MEDIUM", f"Unexpected open port {p}/{port.get('protocol')}",
                                   addr, svc.get("name", "") if svc is not None else "", "close it or add it to VAPT_ALLOWED_PORTS",
                                   category="Server exposure"))
    return out


def parse_lynis(path):
    try:
        with open(path, encoding="utf-8", errors="replace") as fh:
            lines = fh.read().splitlines()
    except OSError:
        return None
    out = []
    for line in lines:
        for key, sev in (("warning[]=", "MEDIUM"), ("suggestion[]=", "LOW")):
            if line.startswith(key):
                parts = line[len(key):].split("|")
                out.append(finding("lynis", parts[0], sev, parts[1] if len(parts) > 1 else parts[0], "server",
                                   category="Server hardening"))
    return out


# ── main ────────────────────────────────────────────────────────────────────

def collect(inputs: str, allowed_ports: set[str]):
    findings, tools = [], {}

    def add(name, result):
        tools[name] = "not run" if result is None else f"{len(result)} finding(s)"
        if result:
            findings.extend(result)

    p = lambda name: os.path.join(inputs, name)  # noqa: E731
    j = lambda name: load_json(p(name))  # noqa: E731

    add("composer audit", None if j("composer-audit.json") is None else parse_composer(j("composer-audit.json")))
    add("npm audit (production)", None if j("npm-audit-prod.json") is None else parse_npm(j("npm-audit-prod.json"), False))
    prod_ids = {f["id"].replace("npm:", "") for f in findings if f["tool"] == "npm"}
    dev = j("npm-audit-all.json")
    add("npm audit (dev only)", None if dev is None else [f for f in parse_npm(dev, True) if f["id"].replace("npm-dev:", "") not in prod_ids])
    add("gitleaks", None if j("gitleaks.json") is None else parse_gitleaks(j("gitleaks.json")))
    add("semgrep", None if j("semgrep.json") is None else parse_semgrep(j("semgrep.json")))
    add("trivy config", None if j("trivy-config.json") is None else parse_trivy(j("trivy-config.json"), "config"))
    for path in sorted(glob.glob(p("trivy-image-*.json"))):
        label = os.path.basename(path)[len("trivy-image-"):-5]
        add(f"trivy image {label}", None if load_json(path) is None else parse_trivy(load_json(path), label))
    for path in sorted(glob.glob(p("zap-*.json"))):
        label = os.path.basename(path)[4:-5]
        add(f"zap {label}", None if load_json(path) is None else parse_zap(load_json(path), label))
    for path in sorted(glob.glob(p("testssl-*.json"))):
        label = os.path.basename(path)[8:-5]
        add(f"testssl {label}", None if load_json(path) is None else parse_testssl(load_json(path), label))
    for path in sorted(glob.glob(p("nmap-*.xml"))):
        add(f"nmap {os.path.basename(path)[5:-4]}", parse_nmap(path, allowed_ports))
    if os.path.exists(p("lynis-report.dat")):
        add("lynis", parse_lynis(p("lynis-report.dat")))
    return findings, tools


def apply_exceptions(findings, exceptions, today):
    used, expired = set(), []
    for ex in exceptions:
        exp = ex.get("expires", "")
        if not (ex.get("id") and ex.get("reason") and ex.get("approved_by") and exp):
            expired.append({**ex, "problem": "incomplete (id, reason, approved_by and expires are all required)"})
            continue
        if exp < today:
            expired.append({**ex, "problem": "expired"})
            continue
        for f in findings:
            if f["id"] == ex["id"] or f["id"].startswith(ex["id"].rstrip("*")) and ex["id"].endswith("*"):
                f["exception"] = {k: ex.get(k) for k in ("id", "reason", "approved_by", "expires")}
                used.add(ex["id"])
    return used, expired


def render_html(report):
    esc = html.escape
    counts = report["counts"]
    color = {"CRITICAL": "#7f1d1d", "HIGH": "#b91c1c", "MEDIUM": "#b45309", "LOW": "#475569"}
    rows = "".join(
        f"<tr><td style='color:{color[f['severity']]};font-weight:600'>{esc(f['severity'])}</td><td>{esc(f['category'])}</td>"
        f"<td>{esc(f['tool'])}</td><td>{esc(f['title'])}</td><td><code>{esc(f['location'])}</code></td>"
        f"<td>{esc(f['fix'])}</td><td>{'BLOCKING' if f['blocking'] else ('exception: ' + esc(f['exception']['reason']) if f.get('exception') else 'not blocking')}</td></tr>"
        for f in report["findings"])
    tools = "".join(f"<li>{esc(k)}: {esc(v)}</li>" for k, v in report["tools"].items())
    decision = report["decision"]
    badge = "#15803d" if decision["deploy_allowed"] else "#b91c1c"
    return f"""<!doctype html><html><head><meta charset="utf-8"><title>VAPT report {esc(report['commit'][:8])}</title>
<style>body{{font:14px system-ui,sans-serif;margin:24px;color:#0f172a}}table{{border-collapse:collapse;width:100%}}
td,th{{border:1px solid #e2e8f0;padding:6px;vertical-align:top;text-align:left}}th{{background:#f1f5f9}}code{{font-size:12px}}</style></head><body>
<h1>VAPT report</h1>
<p><b>Repository:</b> {esc(report['repository'])} &nbsp; <b>Commit:</b> {esc(report['commit'])} &nbsp; <b>Stage:</b> {esc(report['stage'])}<br>
<b>Generated:</b> {esc(report['generated_at'])} (IST) &nbsp; <b>Run:</b> {esc(report['run_url'])}</p>
<p style="color:#fff;background:{badge};padding:8px 12px;display:inline-block;border-radius:6px">{esc(decision['summary'])}</p>
<p>Critical: <b>{counts['CRITICAL']}</b> &nbsp; High: <b>{counts['HIGH']}</b> &nbsp; Medium: <b>{counts['MEDIUM']}</b> &nbsp; Low: <b>{counts['LOW']}</b>
&nbsp; (blocking severities: {esc(', '.join(report['blocking_severities']))})</p>
<h2>Scanners</h2><ul>{tools}</ul>
<h2>Findings</h2><table><tr><th>Severity</th><th>Area</th><th>Tool</th><th>Finding</th><th>Where</th><th>Fix</th><th>Gate</th></tr>{rows or '<tr><td colspan=7>No findings.</td></tr>'}</table>
<h2>Exceptions</h2><pre>{esc(json.dumps(report['exceptions'], indent=2))}</pre>
</body></html>"""


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--inputs", required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--exceptions", default="security/vapt-exceptions.json")
    ap.add_argument("--stage", default="pre-deploy")
    ap.add_argument("--fail-on", default="CRITICAL,HIGH")
    ap.add_argument("--allowed-ports", default="22,80,443")
    ap.add_argument("--override-reason", default="")
    ap.add_argument("--actor", default="")
    ap.add_argument("--override-users", default="")
    args = ap.parse_args()

    blocking_sev = [s.strip().upper() for s in args.fail_on.split(",") if s.strip().upper() in SEVERITIES]
    today = dt.datetime.now(IST).date().isoformat()

    findings, tools = collect(args.inputs, {p.strip() for p in args.allowed_ports.split(",") if p.strip()})
    exceptions = (load_json(args.exceptions) or {}).get("exceptions", [])
    used, invalid = apply_exceptions(findings, exceptions, today)

    for f in findings:
        f["blocking"] = f["blocking_eligible"] and f["severity"] in blocking_sev and not f.get("exception")
    findings.sort(key=lambda f: (SEVERITIES.index(f["severity"]), f["tool"], f["id"]))

    blockers = [f for f in findings if f["blocking"]]
    allowed_overriders = {u.strip().lower() for u in args.override_users.split(",") if u.strip()}
    override = None
    if blockers and args.override_reason.strip():
        authorized = args.actor.lower() in allowed_overriders
        override = {"actor": args.actor, "reason": args.override_reason.strip(), "authorized": authorized}

    allowed = not blockers or bool(override and override["authorized"])
    if not blockers:
        summary = "PASS: no blocking findings, deployment allowed"
    elif allowed:
        summary = f"OVERRIDDEN by {args.actor}: {len(blockers)} blocking finding(s) accepted ({args.override_reason.strip()})"
    elif override:
        summary = f"BLOCKED: {args.actor} is not an authorized override user ({len(blockers)} blocking finding(s))"
    else:
        summary = f"BLOCKED: {len(blockers)} unexcepted finding(s) at {'/'.join(blocking_sev)}"

    report = {
        "repository": os.environ.get("GITHUB_REPOSITORY", "local"),
        "commit": os.environ.get("GITHUB_SHA", "local"),
        "run_url": f"{os.environ.get('GITHUB_SERVER_URL', '')}/{os.environ.get('GITHUB_REPOSITORY', '')}/actions/runs/{os.environ.get('GITHUB_RUN_ID', '')}",
        "stage": args.stage,
        "generated_at": dt.datetime.now(IST).isoformat(timespec="seconds"),
        "blocking_severities": blocking_sev,
        "counts": {s: sum(1 for f in findings if f["severity"] == s) for s in SEVERITIES},
        "tools": tools,
        "decision": {"deploy_allowed": allowed, "summary": summary, "blocking": len(blockers), "override": override},
        "exceptions": {"applied": sorted(used), "invalid_or_expired": invalid},
        "findings": findings,
    }

    os.makedirs(args.out, exist_ok=True)
    with open(os.path.join(args.out, "report.json"), "w", encoding="utf-8") as fh:
        json.dump(report, fh, indent=2)
    with open(os.path.join(args.out, "report.html"), "w", encoding="utf-8") as fh:
        fh.write(render_html(report))

    c = report["counts"]
    md = [f"### VAPT ({args.stage}): {summary}", "",
          f"| Critical | High | Medium | Low |", "|---|---|---|---|", f"| {c['CRITICAL']} | {c['HIGH']} | {c['MEDIUM']} | {c['LOW']} |", "",
          "| Scanner | Result |", "|---|---|"] + [f"| {k} | {v} |" for k, v in tools.items()]
    if blockers:
        md += ["", "**Blocking findings**", ""] + [f"- **{f['severity']}** `{f['id']}` {f['title']} ({f['location']})" for f in blockers[:50]]
    with open(os.path.join(args.out, "summary.md"), "w", encoding="utf-8") as fh:
        fh.write("\n".join(md) + "\n")
    with open(os.path.join(args.out, "summary.html"), "w", encoding="utf-8") as fh:
        fh.write(f"<p><b>{html.escape(summary)}</b></p><p>Critical {c['CRITICAL']} · High {c['HIGH']} · Medium {c['MEDIUM']} · Low {c['LOW']}</p>"
                 f"<p>Commit {html.escape(report['commit'])} · <a href=\"{html.escape(report['run_url'])}\">workflow run</a>. Full report attached.</p>")

    print(summary)
    return 0 if allowed else 2


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as exc:  # noqa: BLE001
        print(f"vapt_report failed: {exc}", file=sys.stderr)
        sys.exit(1)
