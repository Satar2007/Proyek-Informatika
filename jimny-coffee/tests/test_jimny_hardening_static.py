"""Static safety regression checks for the JIMNY hardening patch."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]


class LockOrderTests(unittest.TestCase):
    def test_leave_request_store_locks_shift_before_leave_and_attendance(self):
        source = (ROOT / "app/Http/Controllers/LeaveRequestController.php").read_text(encoding="utf-8")
        block = source[source.index("public function store"):source.index("public function approve")]
        self.assertLess(block.index("Shift::where"), block.index("LeaveRequest::where"))
        self.assertLess(block.index("LeaveRequest::where"), block.index("Attendance::where"))
        self.assertIn("->lockForUpdate()", block)

    def test_leave_approval_uses_parent_first_lock_order(self):
        source = (ROOT / "app/Http/Controllers/LeaveRequestController.php").read_text(encoding="utf-8")
        block = source[source.index("public function approve"):source.index("public function reject")]
        self.assertLess(block.index("Shift::where"), block.index("LeaveRequest::with"))
        self.assertLess(block.index("LeaveRequest::with"), block.index("Attendance::where"))

    def test_shift_delete_uses_parent_first_lock_order(self):
        source = (ROOT / "app/Http/Controllers/ShiftController.php").read_text(encoding="utf-8")
        block = source[source.index("public function destroy"):]
        self.assertLess(block.index("Shift::whereKey"), block.index("LeaveRequest::where"))
        self.assertLess(block.index("LeaveRequest::where"), block.index("Attendance::where"))


class ExportSafetyTests(unittest.TestCase):
    def test_export_requires_existing_non_symlink_output_directory(self):
        source = (ROOT / "JIMNY-DB-EXCEL-EXPORT/Export-All-To-Excel.php").read_text(encoding="utf-8")
        self.assertIn("is_dir($outputDir) || is_link($outputDir)", source)
        self.assertIn("SET TRANSACTION READ ONLY", source)
        self.assertIn("$pdo->beginTransaction()", source)
        self.assertIn("$pdo->commit()", source)

    def test_default_export_excludes_common_pii(self):
        source = (ROOT / "JIMNY-DB-EXCEL-EXPORT/Export-All-To-Excel.php").read_text(encoding="utf-8")
        for field in ("email", "phone", "address", "username", "birth", "nik", "npwp"):
            self.assertIn(field, source.lower())


class TrainingSafetyTests(unittest.TestCase):
    def test_active_ollama_models_are_not_stopped_by_default(self):
        source = (ROOT / "scripts/Run-JsaQwen3Small.ps1").read_text(encoding="utf-8")
        self.assertIn("[switch]$AllowStopActiveModels", source)
        self.assertIn("if ($AllowStopActiveModels)", source)
        self.assertIn("ollama stop $Model", source)


if __name__ == "__main__":
    unittest.main()
