import copy
import importlib.util
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


SKILL_ROOT = Path(__file__).resolve().parents[1]
SCRIPT_PATH = SKILL_ROOT / "scripts" / "validate_release.py"
FIXTURE_ROOT = Path(__file__).resolve().parent / "fixtures" / "valid"


def load_json(name):
    return json.loads((FIXTURE_ROOT / name).read_text(encoding="utf-8"))


def load_validator():
    spec = importlib.util.spec_from_file_location("validate_release", SCRIPT_PATH)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class ValidateReleaseTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.validator = load_validator()

    def setUp(self):
        self.product = load_json("product.json")
        self.release = load_json("release.json")
        self.changelog = load_json("changelog.json")
        self.archive = load_json("archive-manifest.json")

    def validate(self, force=False):
        return self.validator.validate_dataset(
            self.product,
            self.release,
            self.changelog,
            self.archive,
            force=force,
        )

    def assertHasError(self, errors, fragment):
        self.assertTrue(
            any(fragment in error for error in errors),
            f"Expected error containing {fragment!r}, got: {errors}",
        )

    def test_valid_release_has_no_errors(self):
        self.assertEqual([], self.validate())

    def test_missing_patch_version_in_changelog_is_rejected(self):
        for locale_entries in self.changelog["locales"].values():
            locale_entries.pop(1)
        self.assertHasError(self.validate(), "1.1.1")

    def test_duplicate_changelog_version_is_rejected(self):
        self.changelog["locales"]["ru_RU"].append(
            copy.deepcopy(self.changelog["locales"]["ru_RU"][0])
        )
        self.assertHasError(self.validate(), "дублирует версию")

    def test_additional_locale_is_supported(self):
        self.product["product"]["locales"].append("de_DE")
        self.product["features"][0]["public"]["de_DE"] = "Bestätigte Stapelbearbeitung."
        self.product["features"][0]["limitations"]["de_DE"] = "Nur ausgewählte Produkte."
        self.release["changes"][0]["public"]["de_DE"] = "Vorschau hinzugefügt."
        self.release["localization"]["locales"].append("de_DE")
        self.release["documents"]["locales"]["de_DE"] = {
            "store_description": "docs/store-description-de_DE.md",
            "release_note": "docs/releases/1.2.0-de_DE.md",
            "changelog": "docs/CHANGELOG.de_DE.md",
        }
        self.changelog["locales"]["de_DE"] = copy.deepcopy(
            self.changelog["locales"]["en_US"]
        )
        self.assertEqual([], self.validate())

    def test_incomplete_translation_is_rejected(self):
        self.release["changes"][0]["public"]["en_US"] = "{{translate_me}}"
        self.assertHasError(self.validate(), "placeholder")

    def test_version_mismatch_is_rejected(self):
        self.release["archive"]["version"] = "1.1.9"
        self.assertHasError(self.validate(), "версии не совпадают")

    def test_required_meta_update_must_be_idempotent_and_tested(self):
        self.release["data_changes"] = {
            "required": True,
            "meta_updates": [
                {
                    "path": "lib/updates/not-a-timestamp.php",
                    "idempotent": False,
                    "test": ""
                }
            ],
        }
        errors = self.validate()
        self.assertHasError(errors, "timestamp")
        self.assertHasError(errors, "идемпотент")
        self.assertHasError(errors, "тест")

    def test_public_claim_without_evidence_is_rejected(self):
        self.release["changes"][0]["evidence"] = []
        self.assertHasError(self.validate(), "evidence")

    def test_engineering_fact_cannot_be_public(self):
        self.release["changes"][0]["visibility"] = "engineering"
        self.assertHasError(self.validate(), "engineering")

    def test_archive_with_two_roots_and_secret_is_rejected(self):
        self.archive["paths"].extend(["other/file.php", "catalogtools/.env"])
        errors = self.validate()
        self.assertHasError(errors, "корневой каталог")
        self.assertHasError(errors, "запрещённый путь")

    def test_malformed_records_become_blockers_instead_of_crashing(self):
        self.archive["paths"].append({"unexpected": "object"})
        self.release["changes"].append("unexpected-change")
        self.product["features"].append("unexpected-feature")
        self.changelog["locales"]["ru_RU"].append("unexpected-entry")
        errors = self.validate()
        self.assertHasError(errors, "archive path имеет неверный тип")
        self.assertHasError(errors, "release change имеет неверный формат")
        self.assertHasError(errors, "product feature имеет неверный формат")
        self.assertHasError(errors, "changelog ru_RU содержит запись неверного формата")

    def test_malformed_meta_update_becomes_blocker(self):
        self.release["data_changes"] = {
            "required": True,
            "meta_updates": [None],
        }
        self.assertHasError(self.validate(), "meta-update имеет неверный формат")

    def test_published_archive_overwrite_requires_force(self):
        self.release["status"] = "published"
        self.release["archive"]["published"] = True
        self.release["archive"]["exists"] = True
        self.assertHasError(self.validate(), "published")
        self.assertEqual([], self.validate(force=True))

    def test_dry_run_is_stable_and_lists_expected_actions(self):
        first = self.validator.build_dry_run(self.product, self.release, [])
        second = self.validator.build_dry_run(self.product, self.release, [])
        self.assertEqual(first, second)
        self.assertEqual("1.1.1", first["previous_version"])
        self.assertEqual("1.2.0", first["target_version"])
        self.assertIn("archive", first["checks"])
        self.assertEqual("dist/catalogtools-1.2.0.tar.gz", first["archive_path"])

    def test_cli_returns_nonzero_for_invalid_dataset(self):
        result = subprocess.run(
            [
                sys.executable,
                str(SCRIPT_PATH),
                "--product",
                str(FIXTURE_ROOT / "product.json"),
                "--release",
                str(FIXTURE_ROOT / "release.json"),
                "--changelog",
                str(FIXTURE_ROOT / "changelog.json"),
                "--archive-manifest",
                str(FIXTURE_ROOT / "archive-manifest.json"),
                "--dry-run",
            ],
            capture_output=True,
            text=True,
            check=False,
        )
        self.assertEqual(0, result.returncode, result.stderr)
        payload = json.loads(result.stdout)
        self.assertEqual("READY", payload["status"])

    def test_cli_reports_malformed_dataset_without_traceback(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            malformed_product = Path(temp_dir) / "product.json"
            malformed_product.write_text('{"product": []}', encoding="utf-8")
            result = subprocess.run(
                [
                    sys.executable,
                    str(SCRIPT_PATH),
                    "--product",
                    str(malformed_product),
                    "--release",
                    str(FIXTURE_ROOT / "release.json"),
                    "--changelog",
                    str(FIXTURE_ROOT / "changelog.json"),
                    "--archive-manifest",
                    str(FIXTURE_ROOT / "archive-manifest.json"),
                ],
                capture_output=True,
                text=True,
                check=False,
            )
        self.assertNotEqual(0, result.returncode)
        self.assertNotIn("Traceback", result.stderr)
        payload = json.loads(result.stdout)
        self.assertEqual("BLOCKED", payload["status"])
        self.assertHasError(payload["blockers"], "неверную структуру")


if __name__ == "__main__":
    unittest.main()
