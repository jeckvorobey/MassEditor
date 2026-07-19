# Store copy, release notes, and changelog

Load this reference before generating public release documents. Read facts only from the validated product catalogue and release manifest; do not maintain a second handwritten fact list.

## Store description

Keep the current Store description cumulative. Order confirmed capabilities by the product's actual user-facing navigation or workflow, then place selection/filtering, operation controls, how-to steps, limitations, and scope statement in stable separate sections.

For every capability include a short user result, key modes, and only the limitation needed to prevent misunderstanding. Do not place a manually maintained operation list in the how-to section; use a generic instruction such as “choose the required operation”.

Render Store HTML using the target project's allowed-tag contract. When none is defined, prefer `p`, `strong`, `ul`, `ol`, `li`, `code`, and `blockquote`; do not add CSS, scripts, forms, iframes, layout containers, or unsupported attributes.

## Release note

Include only the selected version's delta. Render non-empty sections in this order: Added, Changed, Fixed, Security, Deprecated, Removed, release limitations, installed-product update, and verification. Keep engineering details in the release report unless they produce a user-visible effect.

Exclude Docker ports, internal method names, refactors without user effect, local test setup, and facts first published in an earlier version.

## Changelog

- Put newest versions first and use `MAJOR.MINOR.PATCH`.
- Include every discovered published version exactly once, including patch releases.
- Keep dates, category order, fact IDs, and semantic scope equal across locales.
- Use categories `Added`, `Changed`, `Fixed`, `Security`, `Deprecated`, `Removed`; omit empty categories.
- Update an existing version idempotently instead of appending a duplicate.
- Generate a Store HTML update block from the same release facts when needed; do not edit it independently.

## Localization

Generate natural localized text for every detected locale while preserving version, date, section order, feature set, limitations, and fact IDs. Block readiness for missing documents, placeholders, untranslated required text, or semantic drift.

Use `assets/templates/store-description.md`, `release-note.md`, `changelog-entry.md`, and `publication-checklist.md` as neutral structures. Add or remove only optional empty release-note sections; do not hardcode a target product's features in templates.
