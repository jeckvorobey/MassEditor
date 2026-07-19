---
name: webasyst-release
description: This skill should be used when preparing a Webasyst or Shop-Script app, plugin, widget, or theme for a release, Webasyst Store review, packaging, bilingual Store copy, or synchronized changelog and update notes.
---

# Webasyst Release

Prepare release materials and publication candidates from verified product facts. Do not change runtime scope, claim unsupported behaviour, or publish externally without explicit authorisation.

## Start

1. Read `AGENTS.md`, the active OpenSpec change, current product metadata, git diff, version, localisation state, and relevant release documents.
2. Read `references/store_compliance.md` for a Store review or package candidate.
3. Read `references/store_copy.md` for RU/EN description, screenshots guidance, changelog, or update notes.
4. Read `references/release_packaging.md` when building or checking an archive.

## Release workflow

1. Establish the exact user-visible scope from code, tests, and completed OpenSpec work.
2. Check version, metadata, localisation, install/update/uninstall behaviour, protected directories, and runtime compatibility.
3. Run compliance review and record evidence-backed blockers before assembling a candidate.
4. Build Store copy and changelog in Russian and English from the same verified facts.
5. Validate the archive structure, safe paths, manifest, localisation, and equality with the intended production tree when packaging is in scope.
6. Run relevant checks and strict OpenSpec validation; distinguish verified results from manual or external work not performed.

## Boundaries

Treat Store publication, upload, commit, push, PR creation, and archive as separate authorised actions. Keep screenshots, pricing, marketing claims, and runtime changes outside the release task unless the user includes them.

