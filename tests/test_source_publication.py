import importlib.util
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "scripts/check_source.py"
SPEC = importlib.util.spec_from_file_location("check_source", SCRIPT)
source_check = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(source_check)


class SourcePublicationTest(unittest.TestCase):
    def inspect(self, name, text):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            path = root / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(text, encoding="utf-8")
            return source_check.inspect_file(root, name)

    def test_examples_are_allowed_but_real_env_and_artifacts_are_rejected(self):
        self.assertEqual(self.inspect("twohost/.env.compute.example", "STORAGE_IP=192.168.1.10"), [])
        for name in ["twohost/.env.compute", "runtime/admin-credentials.json", "models/current/manifest.json"]:
            self.assertTrue(self.inspect(name, "{}"), name)

    def test_secret_findings_never_contain_secret_value(self):
        token = "ghp_" + "a" * 40
        findings = self.inspect("example.py", "TOKEN = '" + token + "'")
        self.assertEqual(findings[0]["rule"], "GitHub access token")
        self.assertNotIn(token, str(findings))

    def test_notebook_outputs_are_rejected(self):
        self.assertTrue(self.inspect("notebooks/example.ipynb",
                                    '{"cells":[{"cell_type":"code","execution_count":1,"outputs":[]}]}'))

    def test_regular_source_and_model_readme_are_allowed(self):
        self.assertEqual(self.inspect("models/README.md", "# Model packages"), [])
        self.assertEqual(self.inspect("src/example.py", "value = 42"), [])


if __name__ == "__main__":
    unittest.main()
