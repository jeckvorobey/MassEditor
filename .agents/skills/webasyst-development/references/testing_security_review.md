# TDD, security, complexity, and completion

Load this reference for runtime implementation and before declaring any Webasyst/MassEditor change complete. For docs-only or skill-only work, apply the relevant static portions and explicitly mark runtime checks as not applicable rather than inventing tests.

## TDD loop

1. Express the next behaviour as Given/When/Then.
2. Add or change the narrowest relevant PHP or JavaScript test first.
3. Run it and confirm the expected failure. If it is already green, prove that it actually covers the new contract or regression.
4. Implement the minimum scoped change.
5. Run the targeted test and syntax/lint checks; run `php -l` for changed PHP files.
6. Refactor only while tests remain green, then run the full relevant project suites.

Keep backend actions thin. Place reusable operations in `lib/classes/`, database access in `lib/models/`, and visible strings in the plugin i18n layer with matching `ru_RU` and `en_US` behaviour.

## Mutation security

For backend and bulk writes verify:

- backend permission and object-scope authorisation;
- CSRF protection and server-side validation independent of the UI;
- safe SQL through Webasyst APIs, placeholders, integer casts, and field/sort whitelists;
- escaped output, non-disclosing error messages, limits, preview, explicit confirmation, rollback boundaries, and post-success audit logging;
- selected target/filter revalidation immediately before bounded batch writes.

Test malformed types, empty/whitespace values, zeros, oversized input, quotes, and representative HTML/JavaScript/Smarty/PHP/SQL payloads when the input surface accepts them.

## Final review order

1. Re-run the documentation-evidence audit against the diff and linked runtime files.
2. Apply `complexity-optimizer`; inspect N+1, repeated scans, nested loops, render churn, unbounded collections, and avoid speculative optimisations.
3. Apply `secure-review-loop` to the same scope.
4. For each confirmed finding return to the TDD loop, fix minimally, and repeat both reviews.
5. Run targeted and full relevant tests, `git diff --check`, and `openspec validate --strict --all`.

If a required skill, scanner, runtime, or evidence source is unavailable, name the unverified zone and residual risk. Never claim complete security or performance coverage from a partial fallback.
