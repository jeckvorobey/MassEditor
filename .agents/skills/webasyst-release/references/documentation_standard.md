# Release documentation standard

Load this reference before rendering release documents from validated data.

## Document roles

- Store description: cumulative current product capabilities.
- Release note: only the selected version's user-visible delta and relevant upgrade information.
- Changelog: complete published history with no missing or duplicate versions.
- Publication checklist: automated and manual gates for the selected release.
- Release report: engineering evidence, commands, results, blockers, residual risks, archive metadata, and rollback.

Never maintain the same product fact independently in multiple documents. Render documents from stable fact IDs in the product catalogue and release manifest.

## Store description structure

1. Short title without a feature list.
2. One- or two-sentence purpose and target-user summary.
3. Short end-to-end user scenario.
4. What's new in the current version.
5. Confirmed feature groups ordered by the target product's actual interface/workflow.
6. Selection and filters, when present.
7. Operation controls: confirmation, limits, server validation, batching, logs, permissions, and proven rollback boundaries.
8. How it works in no more than five stable steps without a hardcoded operation inventory.
9. Current verified limitations.
10. Verified product scope statement, including backend-only scope when true.

Each capability states the user result, main modes, and only critical limitations. Omit empty groups and never add a capability only to make the description look complete.

## Release note structure

Use summary followed by non-empty `Added`, `Changed`, `Fixed`, `Security`, `Deprecated`, and `Removed` sections, then release-specific limitations, installed-product update, and verification. Keep public and engineering details separate.

## Changelog invariants

- Newest versions first; `MAJOR.MINOR.PATCH` only.
- Every discovered published version exactly once, including patch versions.
- Same dates, fact IDs, category order, and semantic meaning across locales.
- A change belongs only to the version where it first shipped.
- Generate any Store HTML block from the same facts.

## Migration of existing documents

Inventory every version document and tag. Build the product catalogue and manifests without deleting historical files. Report missing versions, duplicate facts, stale limitations, internal public text, and locale drift. Regenerate current documents only when the user includes migration in scope; otherwise provide a migration plan.
