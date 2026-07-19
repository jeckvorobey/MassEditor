# Documentation and API evidence audit

Load this reference for a new or changed Webasyst/Shop-Script API, hook, action, setting, configuration format, install/uninstall path, or database integration.

Start with the official sources named in `AGENTS.md`. Open the specific mechanism page when behaviour depends on details that are not already confirmed by the local Shop-Script checkout or a working plugin analogue.

## Evidence standard

Classify every disputed mechanism as one of:

- `Подтверждено документацией` — name the official source.
- `Подтверждено локальным кодом` — name the local path, class, method, or working analogue.
- `требуется проверка в документации` — no evidence exists yet.

Do not treat plausible PHP or framework behaviour as evidence.

## Audit order

1. Map the plugin structure and changed runtime surfaces.
2. Verify class, handler, action, template, setting, and route conventions.
3. Verify each method's inputs, outputs, side effects, and error handling.
4. Verify hook existence separately from the `$params` shape.
5. Verify database prefixes, schema ownership, idempotent install/update/uninstall, and bounded standard-table access.
6. Verify rights, CSRF, server validation, safe SQL, escaping, logging, and non-disclosure of technical details.
7. Verify custom backend URL and subdirectory compatibility.
8. Run available syntax, targeted test, route, or package checks.

For each changed mechanism record the source URL or local path/class/method. Verify hardcoded backend URL assumptions, subdirectory installs, Webasyst 2 UI compatibility, and install/update/uninstall idempotency where applicable. Presence in a generic hook list is not evidence for hook parameters.

## Findings format

Report findings in Russian with severity, file and line where available, violated or unconfirmed contract, evidence source, minimal remediation, and required test. Treat changes to core files, unconfirmed critical write APIs, destructive install/uninstall behaviour, and data-loss risks as blockers.

Keep documentation conformance review separate from a dedicated security review and from Store publication compliance; invoke those additional workflows when their scope applies.

If the user requested review only, return findings and a minimal remediation plan without modifying code. If the user requested a fix, keep the patch limited to evidence-backed findings and verify it through the normal TDD loop.
