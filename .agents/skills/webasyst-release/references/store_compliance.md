# Webasyst Store compliance

Load this reference before Store moderation review or release candidate delivery.

Use the current official Store requirements, testing guidance, and self-check list. Confirm product structure, numeric vendor, three-part version, UTF-8 text, Webasyst 2 compatibility where required, and any declared PREMIUM support contract.

Primary sources:

- https://developers.webasyst.ru/docs/store/webasyst-store-requirements/
- https://developers.webasyst.ru/docs/store/testing-before-publication/
- https://developers.webasyst.ru/docs/store/check-list/
- https://developers.webasyst.ru/docs/cookbook/plugins/

Check that the candidate:

- does not modify Webasyst, Shop-Script, or another product's core files;
- writes user data only to allowed storage or the database;
- protects applicable `lib/`, `templates/`, and `locale/` directories with the expected `.htaccess` content;
- contains no development/debug artefacts, hardcoded backend URL assumptions, unsafe external HTTP resources, or unnecessary dependencies;
- uses own-table prefixes, safe SQL, validation, escaping, idempotent install/update/uninstall, and plugin-prefixed globals;
- handles bulk operations through selected targets or an explicit filter, preview, confirmation, batches, limits, and logging;
- has no known PHP/JS errors, warnings, notices, secret leakage, or unsafe file operations.

Also verify additional PHP extensions in `lib/config/requirements.php`, behaviour when an extension or `allow_url_fopen` is unavailable, and licences/source attribution for bundled third-party code or assets. The product should not require another plugin unless an allowed dependency is explicitly justified.

Exercise numeric and text inputs with wrong types, whitespace, zeros, large values, quotes, case variants, selection/deselection of all items, and representative HTML/JavaScript/Smarty/PHP/SQL payloads. Confirm that payloads render as text and do not execute.

For updates, check meta-updates for added/removed schema and files, idempotent repeated installation, safe uninstall ownership, previous-version upgrade behaviour, cache clearing, and both configured and nearly-unused installations.

Classify evidence-backed findings as Blocker, High, Medium, or Low. Keep Store compliance separate from implementation; provide the minimal remediation plan when the request is review-only.
