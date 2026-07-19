#!/usr/bin/env python3
"""Validate evidence-backed Webasyst release data without external dependencies."""

from __future__ import annotations

import argparse
import json
import re
import sys
from collections import Counter
from pathlib import Path, PurePosixPath
from typing import Any


VERSION_RE = re.compile(r"^[0-9]+\.[0-9]+\.[0-9]+$")
TIMESTAMP_UPDATE_RE = re.compile(r"(?:^|/)lib/updates/(?:[^/]+/)*[0-9]{10}\.php$")
PLACEHOLDER_MARKERS = ("{{", "}}", "__")
FORBIDDEN_ARCHIVE_PARTS = {
    ".git",
    ".env",
    ".idea",
    ".vscode",
    "node_modules",
    "vendor-bin",
    "id_rsa",
    "id_ed25519",
}
FORBIDDEN_ARCHIVE_SUFFIXES = (
    ".key",
    ".pem",
    ".log",
    ".sql",
    ".dump",
    ".tmp",
    ".bak",
    "~",
)


def _version_tuple(value: str) -> tuple[int, int, int] | None:
    if not isinstance(value, str) or not VERSION_RE.fullmatch(value):
        return None
    return tuple(int(part) for part in value.split("."))


def _has_placeholder(value: Any) -> bool:
    if not isinstance(value, str):
        return False
    return not value.strip() or any(marker in value for marker in PLACEHOLDER_MARKERS)


def _check_localized_map(
    errors: list[str],
    value: Any,
    locales: list[str],
    context: str,
) -> None:
    if not isinstance(value, dict):
        errors.append(f"{context}: отсутствует локализованный объект")
        return
    for locale in locales:
        text = value.get(locale)
        if _has_placeholder(text):
            errors.append(f"{context}: локаль {locale} пуста или содержит placeholder")


def _check_evidence(errors: list[str], evidence: Any, context: str) -> None:
    if not isinstance(evidence, list) or not evidence:
        errors.append(f"{context}: отсутствует evidence")
        return
    for index, item in enumerate(evidence, start=1):
        if not isinstance(item, dict):
            errors.append(f"{context}: evidence #{index} имеет неверный формат")
            continue
        if item.get("type") not in {"code", "test", "manual", "limitation", "documentation"}:
            errors.append(f"{context}: evidence #{index} имеет неизвестный type")
        if _has_placeholder(item.get("path")) and item.get("type") != "manual":
            errors.append(f"{context}: evidence #{index} не содержит проверяемый path")
        if _has_placeholder(item.get("detail")):
            errors.append(f"{context}: evidence #{index} не содержит detail")


def _validate_version_bump(errors: list[str], release: dict[str, Any]) -> None:
    previous = _version_tuple(release.get("previous_version"))
    current = _version_tuple(release.get("version"))
    if previous is None:
        errors.append("previous_version должен иметь формат MAJOR.MINOR.PATCH")
        return
    if current is None:
        errors.append("version должен иметь формат MAJOR.MINOR.PATCH")
        return
    change_type = release.get("change_type")
    valid = {
        "patch": current[0] == previous[0]
        and current[1] == previous[1]
        and current[2] > previous[2],
        "minor": current[0] == previous[0] and current[1] > previous[1],
        "major": current[0] > previous[0],
    }.get(change_type, False)
    if not valid:
        errors.append(
            f"тип изменения {change_type!r} не соответствует переходу "
            f"{release.get('previous_version')} -> {release.get('version')}"
        )


def _validate_changelog(
    errors: list[str],
    product: dict[str, Any],
    changelog: dict[str, Any],
    locales: list[str],
) -> None:
    expected = product.get("published_versions", [])
    expected_set = set(expected)
    declared = changelog.get("published_versions", [])
    if declared != expected:
        errors.append(
            "changelog published_versions не совпадает с обнаруженными версиями: "
            f"ожидалось {expected}, получено {declared}"
        )

    locale_entries = changelog.get("locales", {})
    reference: list[dict[str, Any]] | None = None
    reference_locale = ""
    for locale in locales:
        entries = locale_entries.get(locale)
        if not isinstance(entries, list):
            errors.append(f"changelog: отсутствует локаль {locale}")
            continue
        valid_entries = []
        for entry in entries:
            if not isinstance(entry, dict):
                errors.append(f"changelog {locale} содержит запись неверного формата")
                continue
            valid_entries.append(entry)
        versions = [entry.get("version") for entry in valid_entries]
        version_counts = Counter(versions)
        duplicates = sorted(version for version, count in version_counts.items() if count > 1)
        for version in duplicates:
            errors.append(f"changelog {locale} дублирует версию {version}")
        version_set = set(versions)
        missing = [version for version in expected if version not in version_set]
        extra = [version for version in versions if version not in expected_set]
        if missing:
            errors.append(f"changelog {locale} пропускает версии: {', '.join(missing)}")
        if extra:
            errors.append(f"changelog {locale} содержит лишние версии: {', '.join(extra)}")
        if not duplicates and not missing and not extra and versions != expected:
            errors.append(f"changelog {locale} содержит версии в неверном порядке")

        if reference is None:
            reference = valid_entries
            reference_locale = locale
            continue
        reference_shape = [
            (entry.get("version"), entry.get("date"), entry.get("fact_ids", []))
            for entry in reference
        ]
        current_shape = [
            (entry.get("version"), entry.get("date"), entry.get("fact_ids", []))
            for entry in valid_entries
        ]
        if current_shape != reference_shape:
            errors.append(
                f"changelog locales {reference_locale} и {locale} не имеют смыслового parity"
            )


def _archive_path_is_forbidden(path: str) -> bool:
    pure = PurePosixPath(path)
    if pure.is_absolute() or ".." in pure.parts:
        return True
    lowered_parts = {part.lower() for part in pure.parts}
    if lowered_parts & FORBIDDEN_ARCHIVE_PARTS:
        return True
    lowered = path.lower()
    return lowered.endswith(FORBIDDEN_ARCHIVE_SUFFIXES)


def _validate_archive(
    errors: list[str],
    product: dict[str, Any],
    release: dict[str, Any],
    archive_manifest: dict[str, Any],
    force: bool,
) -> None:
    distribution = product.get("distribution", {})
    product_root = distribution.get("root")
    release_archive = release.get("archive", {})
    manifest_root = archive_manifest.get("root")
    release_root = release_archive.get("root")
    if len({product_root, release_root, manifest_root}) != 1:
        errors.append(
            "archive root не совпадает между product, release и manifest: "
            f"{product_root!r}, {release_root!r}, {manifest_root!r}"
        )

    paths = archive_manifest.get("paths", [])
    if not isinstance(paths, list) or not paths:
        errors.append("archive manifest не содержит paths")
        return
    string_paths = []
    for path in paths:
        if not isinstance(path, str):
            errors.append(f"archive path имеет неверный тип: {type(path).__name__}")
            continue
        string_paths.append(path)
    roots = {
        PurePosixPath(path).parts[0]
        for path in string_paths
        if PurePosixPath(path).parts
    }
    if roots != {product_root}:
        errors.append(f"archive должен содержать один корневой каталог {product_root!r}, получено {sorted(roots)}")
    for path in string_paths:
        if _archive_path_is_forbidden(path):
            errors.append(f"archive содержит запрещённый путь: {path}")
    path_set = set(string_paths)
    for required_path in distribution.get("required_paths", []):
        if required_path not in path_set:
            errors.append(f"archive не содержит обязательный путь: {required_path}")

    if (
        release.get("status") == "published"
        and release_archive.get("published") is True
        and release_archive.get("exists") is True
        and not force
    ):
        errors.append("published archive нельзя перезаписывать без явного --force")


def validate_dataset(
    product: dict[str, Any],
    release: dict[str, Any],
    changelog: dict[str, Any],
    archive_manifest: dict[str, Any],
    *,
    force: bool = False,
) -> list[str]:
    """Return deterministic release blockers; an empty list means structurally ready."""
    errors: list[str] = []
    product_meta = product.get("product", {})
    locales = product_meta.get("locales", [])
    if (
        not isinstance(locales, list)
        or not locales
        or not all(isinstance(locale, str) and locale for locale in locales)
        or len(locales) != len(set(locales))
    ):
        errors.append("product.locales должен содержать непустой уникальный список")
        locales = []
    if not isinstance(product_meta.get("vendor"), int) or product_meta.get("vendor", 0) <= 0:
        errors.append("product.vendor должен быть положительным integer")

    _validate_version_bump(errors, release)
    target_version = release.get("version")
    versions = {
        "product": product_meta.get("current_version"),
        "release": target_version,
        "archive": release.get("archive", {}).get("version"),
        "store_description": release.get("documents", {}).get("versions", {}).get("store_description"),
        "release_note": release.get("documents", {}).get("versions", {}).get("release_note"),
        "changelog": release.get("documents", {}).get("versions", {}).get("changelog"),
    }
    if len(set(versions.values())) != 1:
        errors.append(f"версии не совпадают: {versions}")

    release_locales = release.get("localization", {}).get("locales", [])
    document_locales = sorted(release.get("documents", {}).get("locales", {}).keys())
    if sorted(locales) != sorted(release_locales):
        errors.append("localization.locales не совпадает с локалями продукта")
    if sorted(locales) != document_locales:
        errors.append("documents.locales не совпадает с локалями продукта")
    for locale, paths in release.get("documents", {}).get("locales", {}).items():
        for document_name in ("store_description", "release_note", "changelog"):
            if _has_placeholder(paths.get(document_name) if isinstance(paths, dict) else None):
                errors.append(f"documents {locale}.{document_name} пуст или содержит placeholder")

    for feature in product.get("features", []):
        if not isinstance(feature, dict):
            errors.append("product feature имеет неверный формат")
            continue
        context = f"feature {feature.get('id', '<unknown>')}"
        _check_localized_map(errors, feature.get("public"), locales, context)
        if feature.get("limitations"):
            _check_localized_map(errors, feature.get("limitations"), locales, f"{context} limitations")
        _check_evidence(errors, feature.get("evidence"), context)

    change_ids: list[str] = []
    for change in release.get("changes", []):
        if not isinstance(change, dict):
            errors.append("release change имеет неверный формат")
            continue
        change_id = change.get("id", "<unknown>")
        change_ids.append(change_id)
        context = f"change {change_id}"
        if change.get("visibility") != "public":
            errors.append(f"{context}: engineering fact не должен находиться в публичных changes")
        _check_localized_map(errors, change.get("public"), locales, context)
        _check_evidence(errors, change.get("evidence"), context)
    change_id_counts = Counter(change_ids)
    duplicate_change_ids = sorted(
        item for item, count in change_id_counts.items() if count > 1
    )
    if duplicate_change_ids:
        errors.append(f"release changes содержат duplicate IDs: {', '.join(duplicate_change_ids)}")

    data_changes = release.get("data_changes", {})
    if data_changes.get("required") is True:
        updates = data_changes.get("meta_updates", [])
        if not updates:
            errors.append("data changes требуют meta-update")
        for update in updates:
            if not isinstance(update, dict):
                errors.append("meta-update имеет неверный формат")
                continue
            path = update.get("path", "")
            if not TIMESTAMP_UPDATE_RE.search(path):
                errors.append(f"meta-update path должен содержать timestamp: {path!r}")
            if update.get("idempotent") is not True:
                errors.append(f"meta-update должен быть идемпотентным: {path!r}")
            if _has_placeholder(update.get("test")):
                errors.append(f"meta-update должен иметь тест обновления: {path!r}")

    _validate_changelog(errors, product, changelog, locales)
    _validate_archive(errors, product, release, archive_manifest, force)
    return errors


def build_dry_run(
    product: dict[str, Any],
    release: dict[str, Any],
    blockers: list[str],
) -> dict[str, Any]:
    """Build a stable machine-readable release plan without changing files."""
    document_paths: list[str] = []
    for locale in sorted(release.get("documents", {}).get("locales", {})):
        paths = release["documents"]["locales"][locale]
        document_paths.extend(paths[name] for name in sorted(paths))
    return {
        "status": "BLOCKED" if blockers else "READY",
        "product": product.get("product", {}).get("id"),
        "previous_version": release.get("previous_version"),
        "target_version": release.get("version"),
        "change_type": release.get("change_type"),
        "diff_range": release.get("diff_range"),
        "locales": sorted(product.get("product", {}).get("locales", [])),
        "documents": sorted(document_paths),
        "checks": [
            "version-consistency",
            "evidence",
            "changelog-completeness",
            "locale-parity",
            "meta-update",
            "project-tests",
            "archive",
            "secret-scan",
        ],
        "archive_path": release.get("archive", {}).get("path"),
        "blockers": blockers,
    }


def _read_json(path: Path) -> dict[str, Any]:
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError as error:
        raise ValueError(f"Файл не найден: {path}") from error
    except json.JSONDecodeError as error:
        raise ValueError(f"Некорректный JSON {path}: {error}") from error
    if not isinstance(payload, dict):
        raise ValueError(f"Корневое значение {path} должно быть object")
    return payload


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--product", type=Path, required=True)
    parser.add_argument("--release", type=Path, required=True)
    parser.add_argument("--changelog", type=Path, required=True)
    parser.add_argument("--archive-manifest", type=Path, required=True)
    parser.add_argument("--force", action="store_true", help="Allow an explicitly authorised published archive rebuild")
    parser.add_argument("--dry-run", action="store_true", help="Print the planned release report as JSON")
    args = parser.parse_args(argv)

    try:
        product = _read_json(args.product)
        release = _read_json(args.release)
        changelog = _read_json(args.changelog)
        archive_manifest = _read_json(args.archive_manifest)
    except ValueError as error:
        print(json.dumps({"status": "BLOCKED", "blockers": [str(error)]}, ensure_ascii=False, indent=2))
        return 2

    try:
        blockers = validate_dataset(
            product,
            release,
            changelog,
            archive_manifest,
            force=args.force,
        )
    except (AttributeError, TypeError, ValueError):
        blockers = ["release dataset имеет неверную структуру"]
    if args.dry_run:
        payload = build_dry_run(product, release, blockers)
    else:
        payload = {"status": "BLOCKED" if blockers else "READY", "blockers": blockers}
    print(json.dumps(payload, ensure_ascii=False, indent=2, sort_keys=True))
    return 1 if blockers else 0


if __name__ == "__main__":
    sys.exit(main())
