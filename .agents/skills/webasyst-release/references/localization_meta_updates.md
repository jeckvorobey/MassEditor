# Localization and meta-updates

Load this reference when the target diff changes visible text, data/schema, stored formats, or shipped runtime files.

## Localization

1. Detect locales from product configuration and localization directories; do not assume a fixed pair.
2. Generate Store description, release note, and changelog for every detected locale from the same fact IDs.
3. Run `php wa.php locale <slug> --debug` when Webasyst CLI is available.
4. Review new and unused gettext keys. Do not remove dynamically constructed keys without proof.
5. Compile `.mo` from `.po` when `.mo` is shipped.
6. Block readiness for compilation errors, missing user-facing translations, placeholders, absent localized documents, or semantic parity failures.

## Meta-update decision

Require a meta-update when the new release adds or changes tables/fields, changes stored data formats, or removes runtime files that existed in the previous published version.

Keep updates under `lib/updates/` with a valid UNIX timestamp filename. Make each update safe to execute repeatedly and contain expected potentially repeated operations so one failure does not stop the remaining update chain.

Test the upgrade from the previous published version with both configured data and a nearly-unused installation. Confirm cache clearing, preserved data, repeated execution, and the new runtime behavior. Do not create a parallel migration registry when the product uses Webasyst meta-updates.

Record `required: false` with evidence when no data or removed-file migration is necessary; absence of a decision is a blocker.
