"""Unit tests for the deployment gate and history logic. Run: python3 -m unittest test_deployctl"""
import argparse
import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import deployctl  # noqa: E402


def ns(**kw):
    return argparse.Namespace(**kw)


class RedactionTest(unittest.TestCase):
    def test_masks_tokens_passwords_and_keys(self):
        text = "git clone https://x-access-token:ghp_abc123@github.com/o/r.git DB_PASSWORD=hunter2 APP_KEY=base64:" + "A" * 44
        out = deployctl.redact(text)
        for leaked in ("ghp_abc123", "hunter2", "A" * 44):
            self.assertNotIn(leaked, out)

    def test_leaves_normal_text(self):
        self.assertEqual(deployctl.redact("Tests: 420 passed"), "Tests: 420 passed")


class HistoryTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        deployctl.MON_DIR = Path(self.tmp.name)
        self.id = deployctl.new_id()

    def tearDown(self):
        self.tmp.cleanup()

    def begin(self):
        deployctl.cmd_begin(ns(id=self.id, commit="abc1234", branch="main", trigger="manual", actor="me", prev_commit="000"))

    def rec(self):
        return json.loads((deployctl.dep_dir(self.id) / "record.json").read_text())

    def test_lifecycle_and_idempotent_begin(self):
        self.begin()
        self.assertEqual(self.rec()["status"], "running")
        deployctl.cmd_stage(ns(id=self.id, stage="gate", commit="", subject=""))
        self.begin()  # re-exec must not reset the record
        self.assertEqual(self.rec()["stage"], "gate")
        deployctl.cmd_finish(ns(id=self.id, status="blocked", reason="token=abc failed", rollback_to=""))
        r = self.rec()
        self.assertEqual(r["status"], "blocked")
        self.assertNotIn("abc", r["reason"].replace("abc1234", ""))
        self.assertIsNotNone(r["completed_at"])

    def test_finish_is_final(self):
        self.begin()
        deployctl.cmd_finish(ns(id=self.id, status="failed", reason="x", rollback_to=""))
        deployctl.cmd_finish(ns(id=self.id, status="success", reason="y", rollback_to=""))
        self.assertEqual(self.rec()["status"], "failed")

    def test_rollback_recorded(self):
        self.begin()
        deployctl.cmd_finish(ns(id=self.id, status="rolled_back", reason="health check failed", rollback_to="000"))
        self.assertEqual(self.rec()["rollback"]["to_commit"], "000")

    def test_rejects_path_traversal_ids(self):
        with self.assertRaises(SystemExit):
            deployctl.dep_dir("../../etc")


class VerdictTest(unittest.TestCase):
    def make_gate(self, tmp):
        deployctl.MON_DIR = Path(tmp)
        a = ns(mode="ci", src=tmp, image="", backend_env="", network="", out=str(Path(tmp) / "out"), id="", commit="c", skip_frontend=True)
        return deployctl.Gate(a)

    def test_failed_mandatory_check_blocks(self):
        with tempfile.TemporaryDirectory() as t:
            g = self.make_gate(t)
            g.add("a", "x", True, "passed")
            g.add("b", "x", True, "failed", "boom")
            self.assertEqual(g.finish(), 1)

    def test_skipped_mandatory_check_blocks(self):
        with tempfile.TemporaryDirectory() as t:
            g = self.make_gate(t)
            g.add("a", "x", True, "skipped")
            self.assertEqual(g.finish(), 1)

    def test_optional_failure_does_not_block_but_is_reported(self):
        with tempfile.TemporaryDirectory() as t:
            g = self.make_gate(t)
            g.add("a", "x", True, "passed")
            g.add("b", "x", False, "failed")
            self.assertEqual(g.finish(), 0)
            report = json.loads((Path(t) / "out" / "gate-report.json").read_text())
            self.assertEqual(report["verdict"], "pass")
            self.assertIn("b", [c["name"] for c in report["checks"] if c["status"] == "failed"])

    def test_a_crashing_check_fails_the_gate_instead_of_aborting_it(self):
        with tempfile.TemporaryDirectory() as t:
            g = self.make_gate(t)

            def boom():
                raise UnicodeDecodeError("utf-8", b"\xed", 0, 1, "bad byte")

            g.guarded("x", boom)
            self.assertEqual(g.finish(), 1)

    def test_non_utf8_command_output_does_not_crash(self):
        with tempfile.TemporaryDirectory() as t:
            g = self.make_gate(t)
            rc, out = g.sh([sys.executable, "-c", "import sys; sys.stdout.buffer.write(b'ok \\xed end')"])
            self.assertEqual(rc, 0)
            self.assertIn("end", out)
            g.log.close()
    def test_junit_parsing_counts_and_categories(self):
        with tempfile.TemporaryDirectory() as t:
            g = self.make_gate(t)
            xml = Path(t) / "j.xml"
            xml.write_text('<testsuites><testsuite>'
                           '<testcase name="a" file="tests/Feature/AuthFlowTest.php"/>'
                           '<testcase name="b" file="tests/Feature/AuthFlowTest.php"><failure>x</failure></testcase>'
                           '<testcase name="c" file="tests/Feature/Other.php"><skipped/></testcase>'
                           '</testsuite></testsuites>')
            totals, failures = g.parse_junit(xml, "Feature")
            self.assertEqual((totals["passed"], totals["failed"], totals["skipped"]), (1, 1, 1))
            self.assertEqual(len(failures), 1)
            self.assertEqual(g.categories["e2e_journey"]["total"], 2)
            self.assertIn("regression", g.categories)
            g.log.close()


if __name__ == "__main__":
    unittest.main()
