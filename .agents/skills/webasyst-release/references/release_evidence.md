# Release evidence discovery

Load this reference before creating or changing release facts.

## Discovery order

1. Read product config and file structure to determine ID, slug, type, vendor, current version, locales, updates, runtime paths, and distribution rules.
2. Determine the previous published version from explicit release status, tags, changelog, release notes, and repository history. Stop on unresolved conflicts.
3. Inspect the previous-to-target Git diff and history. Classify changes by user effect, data/storage impact, localization impact, packaging impact, or engineering-only scope.
4. Trace candidate features through backend/UI entry points, services/models, authorization, validation, tests, localization, and limitations.
5. Reconcile existing Store descriptions, release notes, changelogs, checklists, and archive manifests against the evidence.

## Evidence levels

- `code`: concrete runtime path and behavior.
- `test`: automated test proving the stated contract.
- `manual`: named scenario with date/environment and a confirmed result.
- `limitation`: explicit boundary that prevents an overclaim.
- `documentation`: official platform rule, not product behavior by itself.

Require at least one concrete evidence item for every public claim. Prefer code plus test for mutating, security-sensitive, data-migration, or rollback behavior. Treat screenshots and UI labels only as leads.

For rollback or undo claims, verify supported operations, full/partial restoration, retention period, later-change conflict behavior, permissions, integrity checks, audit behavior, and tests. If any required boundary remains unknown, classify the capability as unconfirmed.

## Public vs engineering facts

Public facts describe a merchant-visible result, limitation, compatibility change, or security effect. Keep ports, local infrastructure, method/class names, internal refactors, test harness changes, and workflow-only changes in the engineering report unless they materially affect users.
