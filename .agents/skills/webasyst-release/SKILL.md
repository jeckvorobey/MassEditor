---
name: webasyst-release
description: Prepares evidence-backed releases for Webasyst apps, plugins, widgets, and themes. Use when Codex needs to analyze changes for a target version, update Store descriptions, release notes or changelogs in every supported locale, assess meta-updates, validate localization, build or inspect a Webasyst Store archive, or produce a release-readiness report.
---

# Webasyst Release

Prepare release materials from the target product's verified code, tests, history, configuration, localization, and prior release evidence. Keep product facts in the target project; keep only reusable workflow, schemas, validators, and templates in this skill.

## Workflow

1. Read root and nested `AGENTS.md`, active OpenSpec artifacts, product rules, Git status/history/tags, config version, locales, updates, tests, distribution conventions, and existing release documents.
2. Describe the current workflow, previous published version, requested target, included scope, non-goals, blockers, and trust boundaries before changing files.
3. Read `references/official_sources.md` and verify drift-prone Webasyst rules against current primary documentation. Record the check date in the release evidence.
4. Read `references/release_evidence.md` and inspect the target diff, code, tests, UI structure, localization, meta-updates, and previous documents. Treat screenshots and labels as discovery hints, not behavior evidence.
5. Read `references/data_model.md`. Copy the neutral JSON templates from `assets/templates/` into the project-selected release-data paths and fill them with target-project facts. Do not embed those facts back into this skill.
6. Run `scripts/validate_release.py --dry-run` before editing public documents. Resolve version, evidence, changelog, locale, meta-update, archive, and published-status blockers first.
7. Read `references/documentation_standard.md` and `references/store_copy.md`. Generate every localized Store description, release note, changelog entry, publication checklist, and report from the same validated dataset and bundled Markdown templates.
8. Read `references/localization_meta_updates.md`. Update gettext and meta-updates only when the target diff requires them; test repeated execution and the previous-version upgrade path when applicable.
9. Run project-specific lint/tests and release-data validation. Keep failed or unavailable checks visible and use `BLOCKED`, not `READY`.
10. Read `references/store_compliance.md` for moderation/readiness review. Read `references/release_packaging.md` only when archive work is authorized.
11. Produce the final report from `assets/templates/release-report.md`, including changed documents, checks, blockers, manual actions, archive evidence if built, and rollback steps.

## Inputs and inference

Infer product path, type, ID, slug, config file, locales, current version, previous published version, document paths, and project checks from the repository. Ask only for a value that cannot be established safely, such as publication status or an intentionally chosen target version.

Support target version, release date, `draft`/`published` status, product path, dry-run, explicit draft rebuild, explicit published rebuild, and all/specific locales. Never require the user to repeat reliably discoverable repository facts.

## Evidence rules

- Link every public feature or release claim to code, a test, a confirmed manual check, or an explicit limitation.
- Keep unconfirmed features outside Store copy and list them as blockers or follow-up evidence work.
- Keep cumulative product capabilities separate from the current version delta.
- Detect locales from the target product; do not assume Russian and English are the complete set.
- Keep engineering-only facts out of public Store text unless they have a concrete user effect.
- Regenerate documents idempotently: update an existing version, never duplicate it.

## Boundaries

Treat config version changes, runtime/meta-update implementation, archive creation, draft overwrite, published overwrite, Store upload, publication, price changes, commit, push, PR, and merge as separate scoped actions. Do not infer authority from a documentation-only request.

Do not generate or replace real product screenshots. Generate only an evidence-derived shot list and localized captions; validate supplied files when requested.

Do not silently fall back from Webasyst CLI packaging to an ad-hoc archive. Report the missing CLI or use a separately authorized fallback that satisfies the same manifest and safety gates.

## Validation

Run:

```bash
python3 -m unittest discover -s .agents/skills/webasyst-release/tests -p 'test_*.py' -v
python3 .agents/skills/webasyst-release/scripts/validate_release.py \
  --product <product.json> \
  --release <release.json> \
  --changelog <changelog.json> \
  --archive-manifest <archive-manifest.json> \
  --dry-run
```

Then run the target project's relevant checks, `git diff --check`, and strict OpenSpec validation when the project uses OpenSpec.
