# Release data model

Load this reference when initializing or validating project release data.

## Project-owned data

Copy templates from `assets/templates/` to paths selected by the target project. If no project convention exists, use:

```text
docs/release-data/product.json
docs/release-data/releases/<version>.json
docs/release-data/changelog.json
docs/release-data/archive-manifest.json
```

Do not edit the bundled templates with product facts. Re-running the workflow for a draft version must update the same project files idempotently.

## Product catalogue

Record:

- product ID, Webasyst slug/type/path/config, numeric vendor, current version, locales, and scope;
- document path patterns and discovered published versions;
- confirmed features grouped by actual UI/workflow, with `since_version`, localized public text, limitations, and evidence;
- expected distribution root and required paths.

Move a feature to public data only after evidence exists. Keep unresolved candidates in the release manifest's `unconfirmed_features`.

## Release manifest

Record only the selected version:

- version, previous version, date, status, bump type, and evidence diff range;
- categorized change facts with stable IDs, localized text, visibility, and evidence;
- data-change/meta-update decision;
- discovered locales and gettext decision;
- versioned document paths for every locale;
- archive version/path/root/existence/publication state;
- required check results and manual actions.

## Validation

Use `references/product.schema.json` and `references/release.schema.json` as portable shape contracts. Run the semantic validator for cross-file rules:

```bash
python3 scripts/validate_release.py \
  --product <product.json> \
  --release <release.json> \
  --changelog <changelog.json> \
  --archive-manifest <archive-manifest.json> \
  --dry-run
```

The validator reports `READY` only for a structurally consistent dataset. Project tests, Webasyst CLI execution, gettext compilation, archive integrity, screenshots, Store upload, and publication remain separate evidence gates.
