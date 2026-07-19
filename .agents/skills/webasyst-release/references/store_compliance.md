# Webasyst Store compliance

Load this reference for Store moderation, readiness review, or a package candidate. Use current official sources from `official_sources.md` and separate evidence-backed blockers from optional improvements.

## Required checks

- Confirm supported product type, documented file/config structure, numeric vendor, three-part version, UTF-8 text, Webasyst 2 interface support, and declared Shop-Script PREMIUM compatibility where applicable.
- Require one tar.gz root named by the product ID, protected `lib/`, `templates/`, and `locale/` directories where present, and no unnecessary files.
- Confirm product-owned table prefixes, safe SQL, input validation, escaping, authorization, CSRF, bounded bulk writes, logs, and safe install/update/uninstall ownership.
- Check declared PHP/framework requirements, optional extensions, remote-resource behavior, third-party licenses, external services, and sensitive-data handling.
- Exercise invalid types, whitespace, zeros, large values, quotes, case variants, selection boundaries, and representative HTML/JavaScript/Smarty/PHP/SQL payloads.
- Verify product descriptions and screenshots against actual behavior; a visible control is not proof that the complete operation is supported.
- Verify the previous-version upgrade path, nearly-unused and configured installations, cache clearing, repeated meta-updates, and removed-file cleanup.

Classify findings as Blocker, High, Medium, or Low. A release is `READY` only when all required automated gates pass and every remaining manual action is explicit.
