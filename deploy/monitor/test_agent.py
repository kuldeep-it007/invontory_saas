"""Unit tests for the pure logic of the monitoring agent. Run: python3 -m unittest deploy/monitor/test_agent.py"""
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import agent  # noqa: E402


class AgentLogicTest(unittest.TestCase):
    def test_meminfo_uses_available_memory(self):
        m = agent.parse_meminfo("MemTotal: 1000 kB\nMemAvailable: 250 kB\nSwapTotal: 400 kB\nSwapFree: 100 kB\n")
        self.assertEqual(m["mem_pct"], 75.0)
        self.assertEqual(m["swap_pct"], 75.0)

    def test_no_swap_is_zero_percent(self):
        self.assertEqual(agent.parse_meminfo("MemTotal: 10 kB\nMemAvailable: 5 kB\n")["swap_pct"], 0.0)

    def test_severity_higher_is_worse(self):
        th = [80, 95]
        self.assertIsNone(agent.severity("cpu_pct", 50, th))
        self.assertEqual(agent.severity("cpu_pct", 80, th), "warning")
        self.assertEqual(agent.severity("cpu_pct", 99, th), "critical")
        self.assertIsNone(agent.severity("cpu_pct", None, th))

    def test_severity_lower_is_worse_for_ssl(self):
        th = [21, 7]
        self.assertIsNone(agent.severity("ssl_days_left", 60, th))
        self.assertEqual(agent.severity("ssl_days_left", 10, th), "warning")
        self.assertEqual(agent.severity("ssl_days_left", 3, th), "critical")

    def test_down_service_raises_critical_alert(self):
        alerts = agent.evaluate({}, {"mysql": {"up": False, "status": "exited"}, "api": {"up": True}}, agent.DEFAULT_THRESHOLDS)
        self.assertEqual([a["key"] for a in alerts], ["service:mysql"])
        self.assertEqual(alerts[0]["severity"], "critical")

    def test_log_line_parsing(self):
        self.assertEqual(agent.parse_log_line("502 0.120"), (502, 0.12))
        self.assertIsNone(agent.parse_log_line("garbage line"))
        self.assertIsNone(agent.parse_log_line(""))

    def test_percentile(self):
        self.assertEqual(agent.percentile([10, 20, 30, 40], 0.5), 30)
        self.assertIsNone(agent.percentile([], 0.95))

    def test_rollup_sums_counters_and_averages_gauges(self):
        out = agent.rollup([{"ts": 1, "cpu_pct": 10, "req": 5}, {"ts": 2, "cpu_pct": 30, "req": 7}])
        self.assertEqual(out["cpu_pct"], 20)
        self.assertEqual(out["req"], 12)
        self.assertEqual(out["ts"], 2)

    def test_thresholds_file_overrides_defaults_and_ignores_garbage(self):
        with tempfile.TemporaryDirectory() as d:
            agent.CONFIG = Path(d)
            (Path(d) / "thresholds.json").write_text('{"cpu_pct":[50,60],"mem_pct":"bad","unknown":[1,2]}')
            th = agent.load_thresholds()
            self.assertEqual(th["cpu_pct"], [50.0, 60.0])
            self.assertEqual(th["mem_pct"], agent.DEFAULT_THRESHOLDS["mem_pct"])
            self.assertNotIn("unknown", th)

    def test_alert_lifecycle_fires_once_and_resolves(self):
        with tempfile.TemporaryDirectory() as d:
            agent.DATA = Path(d)
            mgr = agent.AlertManager()
            a = {"key": "cpu_pct", "severity": "warning", "value": 90, "threshold": 80, "message": "m"}
            mgr.update([dict(a)])
            mgr.update([dict(a)])
            mgr.update([])
            events = [__import__("json").loads(l)["event"] for l in (Path(d) / "alerts.jsonl").read_text().splitlines()]
            self.assertEqual(events, ["fired", "resolved"])
            self.assertEqual(mgr.active, {})


if __name__ == "__main__":
    unittest.main()
